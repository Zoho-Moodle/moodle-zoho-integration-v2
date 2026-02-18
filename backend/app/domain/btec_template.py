"""
BTEC Template Domain Model

Represents a BTEC grading template from Zoho.
Used for creating Moodle grading definitions.
"""

from typing import List, Optional, Dict, Any
from pydantic import BaseModel, Field


class BtecCriterion(BaseModel):
    """
    Individual BTEC criterion (P1, M1, D1, etc.)
    """
    code: str = Field(..., description="Criterion code (e.g., P1, M1, D1)")
    description: str = Field(..., description="Criterion description text")
    level: str = Field(..., description="Level: Pass, Merit, or Distinction")
    order: int = Field(..., description="Display order within level")


class BtecTemplate(BaseModel):
    """
    Complete BTEC grading template for a unit.
    
    Contains all Pass, Merit, and Distinction criteria from Zoho BTEC module.
    """
    zoho_unit_id: str = Field(..., description="Zoho BTEC (Unit) record ID")
    unit_name: str = Field(..., description="Unit name/title")
    
    pass_criteria: List[BtecCriterion] = Field(default_factory=list, description="Pass criteria (P1-P20)")
    merit_criteria: List[BtecCriterion] = Field(default_factory=list, description="Merit criteria (M1-M8)")
    distinction_criteria: List[BtecCriterion] = Field(default_factory=list, description="Distinction criteria (D1-D6)")
    
    # Metadata
    description: Optional[str] = Field(default=None, description="Unit description")
    status: Optional[str] = Field(default=None, description="Template status in Zoho")
    
    @property
    def total_criteria_count(self) -> int:
        """Total number of criteria across all levels."""
        return len(self.pass_criteria) + len(self.merit_criteria) + len(self.distinction_criteria)
    
    @property
    def is_valid(self) -> bool:
        """Check if template has at least one criterion."""
        return self.total_criteria_count > 0
    
    def to_moodle_dict(self) -> Dict[str, Any]:
        """
        Convert to Moodle grading definition format.
        
        Returns dict suitable for Moodle External API:
        {
            'name': 'Unit Name',
            'description': 'Unit Description',
            'criteria': [
                {'shortname': 'P1', 'description': '...', 'level': 'pass'},
                {'shortname': 'M1', 'description': '...', 'level': 'merit'},
                ...
            ]
        }
        """
        criteria = []
        
        # Add Pass criteria
        for c in self.pass_criteria:
            criteria.append({
                'shortname': c.code,
                'description': c.description,
                'level': 'pass',
                'sortorder': c.order
            })
        
        # Add Merit criteria
        for c in self.merit_criteria:
            criteria.append({
                'shortname': c.code,
                'description': c.description,
                'level': 'merit',
                'sortorder': c.order + 100  # Offset to ensure correct ordering
            })
        
        # Add Distinction criteria
        for c in self.distinction_criteria:
            criteria.append({
                'shortname': c.code,
                'description': c.description,
                'level': 'distinction',
                'sortorder': c.order + 200  # Offset to ensure correct ordering
            })
        
        return {
            'name': self.unit_name,
            'description': self.description or '',
            'criteria': criteria,
            'zoho_unit_id': self.zoho_unit_id
        }
    
    @classmethod
    def from_zoho_record(cls, zoho_data: Dict[str, Any]) -> "BtecTemplate":
        """
        Parse Zoho BTEC record into BtecTemplate.
        
        Extracts P1-P10 from main record, P11-P20 from subform,
        M1-M8, D1-D6 from main record.
        Filters out empty/null descriptions.
        
        Args:
            zoho_data: Raw Zoho API response for BTEC record
        
        Returns:
            BtecTemplate instance
        """
        unit_id = zoho_data.get('id', '')
        unit_name = zoho_data.get('Name', 'Unnamed Unit')
        
        # Extract Pass criteria P1-P10 from main record
        pass_criteria = []
        for i in range(1, 11):  # P1 to P10
            field = f'P{i}_description'
            value = zoho_data.get(field)
            desc = (value or '').strip() if value is not None else ''
            if desc:
                pass_criteria.append(BtecCriterion(
                    code=f'P{i}',
                    description=desc,
                    level='Pass',
                    order=i
                ))
        
        # Extract Pass criteria P11-P20 from subform
        subform_records = zoho_data.get('BTEC_Grading_Template_P1', [])
        if subform_records and isinstance(subform_records, list):
            for record in subform_records:
                for i in range(11, 21):  # P11 to P20
                    field = f'P{i}_description'
                    value = record.get(field)
                    desc = (value or '').strip() if value is not None else ''
                    if desc:
                        pass_criteria.append(BtecCriterion(
                            code=f'P{i}',
                            description=desc,
                            level='Pass',
                            order=i
                        ))
        
        # Extract Merit criteria (M1-M8)
        merit_criteria = []
        for i in range(1, 9):  # M1 to M8
            field = f'M{i}_description'
            value = zoho_data.get(field)
            desc = (value or '').strip() if value is not None else ''
            if desc:
                merit_criteria.append(BtecCriterion(
                    code=f'M{i}',
                    description=desc,
                    level='Merit',
                    order=i
                ))
        
        # Extract Distinction criteria (D1-D6)
        distinction_criteria = []
        for i in range(1, 7):  # D1 to D6
            field = f'D{i}_description'
            value = zoho_data.get(field)
            desc = (value or '').strip() if value is not None else ''
            if desc:
                distinction_criteria.append(BtecCriterion(
                    code=f'D{i}',
                    description=desc,
                    level='Distinction',
                    order=i
                ))
        
        return cls(
            zoho_unit_id=unit_id,
            unit_name=unit_name,
            pass_criteria=pass_criteria,
            merit_criteria=merit_criteria,
            distinction_criteria=distinction_criteria,
            description=zoho_data.get('Description', None),
            status=zoho_data.get('Current_BTEC_marking_status', None)
        )
