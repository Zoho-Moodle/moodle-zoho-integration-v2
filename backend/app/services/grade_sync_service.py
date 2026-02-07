"""
Grade Sync Service - Moodle ↔ Zoho BTEC_Grades

Syncs grading data between Moodle and Zoho CRM following ZOHO_API_CONTRACT.md.

Flow:
1. Fetch grading template from BTEC (Units) module
2. Map Moodle grade data to P/M/D criteria
3. Build BTEC_Grades header + Learning_Outcomes_Assessm subform
4. Create/update in Zoho using composite key

⚠️ CRITICAL: Follows contract strictly:
- Template from BTEC module (P1_description...P19, M1...M9, D1...D6)
- Results from Moodle gradebook
- Storage in BTEC_Grades with Learning_Outcomes_Assessm subform
- Composite key: Moodle_Grade_Composite_Key = student_id + course_id
"""

import logging
from typing import Dict, List, Optional, Tuple
from datetime import datetime
from sqlalchemy.orm import Session

from app.infra.zoho.client import ZohoClient
from app.infra.moodle.users import MoodleClient
from app.infra.zoho.exceptions import ZohoNotFoundError, ZohoValidationError

logger = logging.getLogger(__name__)


class GradingTemplate:
    """
    Represents a BTEC unit grading template.
    
    Extracted from BTEC module fields:
    - P1_description...P19_description (Pass)
    - M1_description...M9_description (Merit)
    - D1_description...D6_description (Distinction)
    """
    
    def __init__(self, unit_id: str, unit_data: Dict):
        """
        Initialize from BTEC unit data.
        
        Args:
            unit_id: Zoho unit record ID
            unit_data: Full unit record from BTEC module
        """
        self.unit_id = unit_id
        self.unit_name = unit_data.get('Name', 'Unknown Unit')
        self.unit_code = unit_data.get('Unit_Code', '')
        
        # Extract criteria
        self.pass_criteria = self._extract_criteria(unit_data, 'P', range(1, 20))
        self.merit_criteria = self._extract_criteria(unit_data, 'M', range(1, 10))
        self.distinction_criteria = self._extract_criteria(unit_data, 'D', range(1, 7))
    
    def _extract_criteria(
        self,
        unit_data: Dict,
        level: str,
        number_range: range
    ) -> List[Dict]:
        """
        Extract criteria for a specific level (P/M/D).
        
        Args:
            unit_data: Unit record data
            level: 'P', 'M', or 'D'
            number_range: Range of numbers to check (e.g., range(1, 20))
        
        Returns:
            List of criteria dicts with code and description
        """
        criteria = []
        
        for i in number_range:
            field_name = f'{level}{i}_description'
            description = unit_data.get(field_name)
            
            if description:  # Only include if description exists
                criteria.append({
                    'code': f'{level}{i}',
                    'description': description,
                    'field_name': field_name
                })
        
        return criteria
    
    def get_all_criteria(self) -> List[Dict]:
        """Get all criteria (P + M + D) as flat list."""
        return self.pass_criteria + self.merit_criteria + self.distinction_criteria
    
    def get_criterion_by_code(self, code: str) -> Optional[Dict]:
        """
        Get specific criterion by code (e.g., 'P1', 'M2', 'D3').
        
        Args:
            code: Criterion code (P1-P19, M1-M9, D1-D6)
        
        Returns:
            Criterion dict or None if not found
        """
        for criterion in self.get_all_criteria():
            if criterion['code'] == code:
                return criterion
        return None
    
    def __repr__(self):
        return (
            f"GradingTemplate(unit={self.unit_name}, "
            f"P={len(self.pass_criteria)}, "
            f"M={len(self.merit_criteria)}, "
            f"D={len(self.distinction_criteria)})"
        )


class MoodleGradeData:
    """
    Represents Moodle grade data to be synced to Zoho.
    
    Contains individual criterion scores (P1, P2, M1, etc.)
    plus overall metadata.
    """
    
    def __init__(
        self,
        moodle_grade_id: str,
        student_id: str,
        course_id: str,
        zoho_student_id: str,
        zoho_class_id: str,
        zoho_unit_id: str,
        overall_grade: str,  # Pass, Merit, Distinction, Refer
        graded_date: str,
        feedback: str = "",
        attempt_number: int = 1
    ):
        """
        Initialize Moodle grade data.
        
        Args:
            moodle_grade_id: Moodle grade item ID
            student_id: Moodle student user ID
            course_id: Moodle course ID
            zoho_student_id: Zoho BTEC_Students record ID
            zoho_class_id: Zoho BTEC_Classes record ID
            zoho_unit_id: Zoho BTEC (Units) record ID
            overall_grade: Overall grade (Pass/Merit/Distinction/Refer)
            graded_date: Date graded (YYYY-MM-DD)
            feedback: Teacher feedback
            attempt_number: Attempt number (default 1)
        """
        self.moodle_grade_id = moodle_grade_id
        self.student_id = student_id
        self.course_id = course_id
        self.zoho_student_id = zoho_student_id
        self.zoho_class_id = zoho_class_id
        self.zoho_unit_id = zoho_unit_id
        self.overall_grade = overall_grade
        self.graded_date = graded_date
        self.feedback = feedback
        self.attempt_number = attempt_number
        
        # Criterion scores (populated separately)
        self.criteria_scores: List[Dict] = []
    
    def add_criterion_score(
        self,
        code: str,
        score: str,  # "Achieved" or "Not Achieved"
        feedback: str = ""
    ):
        """
        Add individual criterion score.
        
        Args:
            code: Criterion code (P1, P2, M1, etc.)
            score: "Achieved" or "Not Achieved"
            feedback: Specific feedback for this criterion
        """
        self.criteria_scores.append({
            'code': code,
            'score': score,
            'feedback': feedback
        })
    
    @property
    def composite_key(self) -> str:
        """Generate composite key for deduplication."""
        return f"{self.student_id}_{self.course_id}"


class GradeSyncService:
    """
    Service for syncing grades from Moodle to Zoho BTEC_Grades.
    
    Handles:
    - Template extraction from BTEC module
    - Mapping Moodle grades to BTEC criteria
    - Building Learning_Outcomes_Assessm subform
    - Creating/updating BTEC_Grades records
    - Composite key deduplication
    
    Usage:
        service = GradeSyncService(zoho_client, moodle_client, db_session)
        await service.sync_grade(moodle_grade_data)
    """
    
    def __init__(
        self,
        zoho_client: ZohoClient,
        moodle_client: Optional[MoodleClient] = None,
        db: Optional[Session] = None
    ):
        """
        Initialize grade sync service.
        
        Args:
            zoho_client: Zoho API client
            moodle_client: Moodle API client (optional)
            db: Database session (optional, for logging)
        """
        self.zoho = zoho_client
        self.moodle = moodle_client
        self.db = db
        
        # Template cache (unit_id -> GradingTemplate)
        self._template_cache: Dict[str, GradingTemplate] = {}
    
    async def get_grading_template(
        self,
        unit_id: str,
        use_cache: bool = True
    ) -> GradingTemplate:
        """
        Fetch grading template from BTEC (Units) module.
        
        Args:
            unit_id: Zoho BTEC record ID
            use_cache: Use cached template if available
        
        Returns:
            GradingTemplate instance
        
        Raises:
            ZohoNotFoundError: If unit not found
        """
        # Check cache
        if use_cache and unit_id in self._template_cache:
            logger.debug(f"Using cached template for unit {unit_id}")
            return self._template_cache[unit_id]
        
        # Fetch from Zoho
        logger.info(f"Fetching grading template for unit {unit_id}")
        
        try:
            # ⚠️ IMPORTANT: Use 'BTEC' module, NOT 'BTEC_Units'
            unit_data = await self.zoho.get_record('BTEC', unit_id)
            
            # Create template
            template = GradingTemplate(unit_id, unit_data)
            
            # Cache it
            self._template_cache[unit_id] = template
            
            logger.info(
                f"Loaded template for {template.unit_name}: "
                f"{len(template.pass_criteria)}P + {len(template.merit_criteria)}M + "
                f"{len(template.distinction_criteria)}D"
            )
            
            return template
        
        except ZohoNotFoundError:
            logger.error(f"Unit {unit_id} not found in Zoho BTEC module")
            raise
    
    def build_learning_outcomes_subform(
        self,
        moodle_grade: MoodleGradeData,
        template: GradingTemplate
    ) -> List[Dict]:
        """
        Build Learning_Outcomes_Assessm subform.
        
        Creates one row per criterion (P1, P2, M1, etc.) with:
        - LO_Code: Criterion code
        - LO_Title: First 100 chars of description
        - LO_Score: Achieved/Not Achieved (from Moodle)
        - LO_Definition: Full description (from template)
        - LO_Feedback: Criterion-specific feedback (from Moodle)
        
        Args:
            moodle_grade: Moodle grade data with criterion scores
            template: BTEC unit grading template
        
        Returns:
            List of subform row dicts
        """
        subform_rows = []
        
        for criterion_score in moodle_grade.criteria_scores:
            code = criterion_score['code']
            score = criterion_score['score']
            feedback = criterion_score.get('feedback', '')
            
            # Find template definition
            template_criterion = template.get_criterion_by_code(code)
            
            if not template_criterion:
                logger.warning(
                    f"Criterion {code} not found in template for unit {template.unit_id}. "
                    f"Skipping."
                )
                continue
            
            description = template_criterion['description']
            
            # Build subform row (field names from ZOHO_API_CONTRACT.md)
            subform_rows.append({
                'LO_Code': code,
                'LO_Title': description[:100],  # First 100 chars
                'LO_Score': score,  # "Achieved" or "Not Achieved"
                'LO_Definition': description,  # Full description
                'LO_Feedback': feedback
            })
        
        logger.debug(f"Built {len(subform_rows)} subform rows for grade {moodle_grade.moodle_grade_id}")
        
        return subform_rows
    
    async def sync_grade(self, moodle_grade: MoodleGradeData) -> Dict:
        """
        Sync single grade from Moodle to Zoho.
        
        Flow:
        1. Fetch grading template
        2. Build Learning_Outcomes_Assessm subform
        3. Check if grade exists (by composite key)
        4. Create or update BTEC_Grades record
        
        Args:
            moodle_grade: Moodle grade data to sync
        
        Returns:
            Result dict with status and Zoho record ID
        
        Example:
            grade_data = MoodleGradeData(
                moodle_grade_id="12345",
                student_id="101",
                course_id="202",
                zoho_student_id="5843017000000111111",
                zoho_class_id="5843017000000222222",
                zoho_unit_id="5843017000000333333",
                overall_grade="Pass",
                graded_date="2026-01-25",
                feedback="Good work overall"
            )
            grade_data.add_criterion_score("P1", "Achieved", "Clear explanation")
            grade_data.add_criterion_score("P2", "Achieved", "Good detail")
            grade_data.add_criterion_score("M1", "Not Achieved", "Needs more analysis")
            
            result = await service.sync_grade(grade_data)
            print(f"Grade synced: {result['zoho_record_id']}")
        """
        logger.info(
            f"Syncing grade for student {moodle_grade.student_id}, "
            f"course {moodle_grade.course_id}"
        )
        
        # 1. Get grading template
        template = await self.get_grading_template(moodle_grade.zoho_unit_id)
        
        # 2. Build subform
        subform_rows = self.build_learning_outcomes_subform(moodle_grade, template)
        
        if not subform_rows:
            logger.warning(
                f"No valid criteria found for grade {moodle_grade.moodle_grade_id}. "
                f"Cannot sync."
            )
            return {
                'status': 'error',
                'message': 'No valid criteria scores'
            }
        
        # 3. Prepare BTEC_Grades data (field names from contract)
        composite_key = moodle_grade.composite_key
        
        grade_data = {
            # Header fields
            "Student": moodle_grade.zoho_student_id,
            "Class": moodle_grade.zoho_class_id,
            "BTEC_Unit": moodle_grade.zoho_unit_id,
            "Grade": moodle_grade.overall_grade,  # Pass/Merit/Distinction/Refer
            "Grade_Status": "Submitted",
            "Attempt_Date": moodle_grade.graded_date,
            "Attempt_Number": moodle_grade.attempt_number,
            "Feedback": moodle_grade.feedback,
            "Moodle_Grade_ID": str(moodle_grade.moodle_grade_id),
            "Moodle_Grade_Composite_Key": composite_key,
            
            # Subform: Learning_Outcomes_Assessm
            "Learning_Outcomes_Assessm": subform_rows
        }
        
        # 4. Upsert grade (create or update based on composite key)
        try:
            logger.info(f"Upserting grade with composite key: {composite_key}")
            
            # Use Zoho's upsert API to automatically handle create vs update
            result = await self.zoho.upsert_record(
                'BTEC_Grades',
                grade_data,
                duplicate_check_fields=['Moodle_Grade_Composite_Key']
            )
            
            if result.get('code') == 'SUCCESS':
                grade_id = result['details']['id']
                action = result.get('action', 'unknown')  # 'insert' or 'update'
                
                logger.info(
                    f"Grade {action}d successfully: {grade_id} "
                    f"(composite key: {composite_key})"
                )
                
                return {
                    'status': 'created' if action == 'insert' else 'updated',
                    'zoho_record_id': grade_id,
                    'composite_key': composite_key,
                    'criteria_count': len(subform_rows)
                }
            else:
                raise ZohoAPIError(f"Upsert failed: {result}")
        
        except ZohoValidationError as e:
            logger.error(f"Validation error syncing grade: {e}")
            logger.error(f"Grade data: {grade_data}")
            raise
        
        except Exception as e:
            logger.error(f"Unexpected error syncing grade: {e}")
            raise
    
    async def sync_grade_simple(
        self,
        moodle_grade_id: str,
        student_zoho_id: str,
        class_zoho_id: str,
        unit_zoho_id: str,
        overall_grade: str,
        criteria_scores: List[Tuple[str, str, str]],  # [(code, score, feedback), ...]
        student_moodle_id: str,
        course_moodle_id: str,
        graded_date: Optional[str] = None,
        feedback: str = ""
    ) -> Dict:
        """
        Simplified interface for syncing a grade.
        
        Args:
            moodle_grade_id: Moodle grade item ID
            student_zoho_id: Zoho BTEC_Students ID
            class_zoho_id: Zoho BTEC_Classes ID
            unit_zoho_id: Zoho BTEC (Units) ID
            overall_grade: Pass/Merit/Distinction/Refer
            criteria_scores: List of (code, score, feedback) tuples
            student_moodle_id: Moodle student user ID
            course_moodle_id: Moodle course ID
            graded_date: Date graded (defaults to today)
            feedback: Overall feedback
        
        Returns:
            Sync result
        
        Example:
            result = await service.sync_grade_simple(
                moodle_grade_id="12345",
                student_zoho_id="5843017000000111111",
                class_zoho_id="5843017000000222222",
                unit_zoho_id="5843017000000333333",
                overall_grade="Pass",
                criteria_scores=[
                    ("P1", "Achieved", "Good"),
                    ("P2", "Achieved", "Well done"),
                    ("M1", "Not Achieved", "Needs work")
                ],
                student_moodle_id="101",
                course_moodle_id="202",
                feedback="Good work overall"
            )
        """
        # Build MoodleGradeData
        grade_data = MoodleGradeData(
            moodle_grade_id=moodle_grade_id,
            student_id=student_moodle_id,
            course_id=course_moodle_id,
            zoho_student_id=student_zoho_id,
            zoho_class_id=class_zoho_id,
            zoho_unit_id=unit_zoho_id,
            overall_grade=overall_grade,
            graded_date=graded_date or datetime.now().strftime('%Y-%m-%d'),
            feedback=feedback
        )
        
        # Add criteria scores
        for code, score, criterion_feedback in criteria_scores:
            grade_data.add_criterion_score(code, score, criterion_feedback)
        
        # Sync
        return await self.sync_grade(grade_data)
    
    def clear_template_cache(self):
        """Clear cached grading templates."""
        self._template_cache.clear()
        logger.info("Grading template cache cleared")
