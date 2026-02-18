"""
BTEC Template Sync Service

Syncs BTEC grading templates from Zoho to Moodle.

Flow:
1. Fetch templates from Zoho BTEC module
2. Parse criteria (P1-P20, M1-M8, D1-D6)
3. Transform to Moodle format
4. Call Moodle External API to create grading definitions
5. Track sync status in database

Usage:
    service = BtecTemplateService(zoho_client, moodle_client, db)
    results = await service.sync_templates_from_zoho()
"""

import logging
from typing import List, Dict, Any, Optional
from datetime import datetime, timezone
from sqlalchemy.orm import Session

from app.infra.zoho.client import ZohoClient
from app.infra.moodle.users import MoodleClient
from app.domain.btec_template import BtecTemplate, BtecCriterion
from app.infra.zoho.exceptions import ZohoNotFoundError, ZohoAPIError

logger = logging.getLogger(__name__)


class BtecTemplateService:
    """
    Service for syncing BTEC grading templates from Zoho to Moodle.
    """
    
    def __init__(
        self,
        zoho_client: ZohoClient,
        moodle_client: Optional[MoodleClient] = None,
        db: Optional[Session] = None
    ):
        self.zoho = zoho_client
        self.moodle = moodle_client
        self.db = db
        
        # Cache for already processed templates
        self._processed_units = set()
    
    async def _update_zoho_sync_status(
        self,
        zoho_unit_id: str,
        synced: bool
    ) -> bool:
        """
        Update Zoho BTEC record with sync status and timestamp.
        
        Args:
            zoho_unit_id: Zoho BTEC record ID
            synced: True if synced successfully, False if failed
        
        Returns:
            True if update succeeded, False otherwise
        """
        try:
            # Zoho datetime format: YYYY-MM-DDTHH:MM:SS without timezone
            sync_time = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%S')
            
            update_data = {
                'Last_Sync_with_Moodle': sync_time,
                'Moodle_Grading_Template': 'Synced' if synced else 'Failed'
            }
            
            logger.info(f"Updating Zoho record {zoho_unit_id} with sync status: {update_data}")
            
            # Update Zoho record
            result = await self.zoho.update_record('BTEC', zoho_unit_id, update_data)
            
            if result:
                logger.info(f"✅ Updated Zoho sync status for {zoho_unit_id}")
                return True
            else:
                logger.warning(f"⚠️ Failed to update Zoho sync status for {zoho_unit_id}")
                return False
                
        except Exception as e:
            logger.error(f"❌ Error updating Zoho sync status for {zoho_unit_id}: {e}")
            return False
    
    async def fetch_template_from_zoho(self, unit_id: str) -> BtecTemplate:
        """
        Fetch single template from Zoho BTEC module.
        
        Args:
            unit_id: Zoho BTEC record ID
        
        Returns:
            BtecTemplate instance
        
        Raises:
            ZohoNotFoundError: If unit not found
            ZohoAPIError: If API call fails
        """
        logger.info(f"Fetching BTEC template for unit {unit_id}")
        
        try:
            # Fetch from Zoho BTEC module
            unit_data = await self.zoho.get_record('BTEC', unit_id)
            
            # Parse into template
            template = BtecTemplate.from_zoho_record(unit_data)
            
            if not template.is_valid:
                logger.warning(f"Unit {unit_id} has no valid criteria")
            
            logger.info(
                f"Fetched template: {template.unit_name} "
                f"({template.total_criteria_count} criteria)"
            )
            
            return template
            
        except ZohoNotFoundError:
            logger.error(f"Unit {unit_id} not found in Zoho BTEC module")
            raise
        except Exception as e:
            logger.error(f"Error fetching template {unit_id}: {e}")
            raise ZohoAPIError(f"Failed to fetch template: {str(e)}")
    
    async def fetch_all_templates_from_zoho(
        self,
        only_ready: bool = True
    ) -> List[BtecTemplate]:
        """
        Fetch all templates from Zoho BTEC module.
        
        Args:
            only_ready: Only fetch templates with status "Ready for use"
        
        Returns:
            List of BtecTemplate instances
        """
        print(f"🔍 FETCH_ALL_TEMPLATES_FROM_ZOHO called, only_ready={only_ready}")
        
        logger.info("🔍 Starting: Fetching all BTEC templates from Zoho")
        logger.info(f"Parameters: only_ready={only_ready}")
        
        try:
            # Fetch ALL records with pagination (like old script)
            all_records = []
            page = 1
            per_page = 200
            max_pages = 100  # Safety limit
            
            print("📡 Starting pagination loop to fetch all BTEC units...")
            logger.info(f"📡 Fetching BTEC units with pagination: per_page={per_page}, max_pages={max_pages}")
            
            for page_num in range(1, max_pages + 1):
                print(f"📄 Fetching page {page_num}...")
                logger.info(f"📄 Fetching page {page_num} from Zoho")
                
                response = await self.zoho.get_records(
                    module='BTEC',
                    per_page=per_page,
                    page=page_num
                )
                
                page_records = response.get('data', [])
                record_count = len(page_records)
                
                print(f"✅ Page {page_num}: Got {record_count} records")
                logger.info(f"✅ Page {page_num}: Received {record_count} records")
                
                if record_count == 0:
                    print(f"🏁 No more records at page {page_num}, stopping pagination")
                    logger.info(f"🏁 Pagination complete: No records on page {page_num}")
                    break
                
                all_records.extend(page_records)
                
                # If we got less than per_page, this is the last page
                if record_count < per_page:
                    print(f"🏁 Last page reached (got {record_count} < {per_page})")
                    logger.info(f"🏁 Last page {page_num}: Got {record_count} records (less than {per_page})")
                    break
            
            records = all_records
            
            print(f"📊 Total records from all pages: {len(records)}")
            logger.info(f"📊 Pagination complete: Total {len(records)} BTEC units fetched")
            
            if len(records) == 0:
                logger.warning("⚠️  No records returned from Zoho BTEC module!")
            else:
                # Log first record for inspection
                print(f"🔍 First record keys: {list(records[0].keys())[:10]}")
                print(f"🔍 First record Name: {records[0].get('Name')}")
                print(f"🔍 First record P1_description: {records[0].get('P1_description')}")
                print(f"🔍 First record status: {records[0].get('Current_BTEC_marking_status')}")
            
            # Parse into templates
            templates = []
            skipped_p1 = 0
            skipped_invalid = 0
            for idx, record in enumerate(records):
                try:
                    # Skip if P1_description is empty (mandatory field - like old script)
                    p1_value = record.get('P1_description')
                    if not p1_value or (isinstance(p1_value, str) and not p1_value.strip()):
                        skipped_p1 += 1
                        if idx < 3:  # Log first 3 skipped
                            print(f"⏭️  Skipping record {idx}: {record.get('Name')} - P1 is empty")
                        continue
                    
                    template = BtecTemplate.from_zoho_record(record)
                    
                    # Status filter removed - accept all units like old script
                    # Old script behavior: no status filtering at all
                    
                    # Skip templates with no criteria
                    if template.is_valid:
                        templates.append(template)
                    else:
                        skipped_invalid += 1
                        logger.warning(
                            f"Skipping unit {template.unit_name} - no valid criteria"
                        )
                
                except Exception as e:
                    logger.error(f"Error parsing template: {e}")
                    continue
            
            logger.info(
                f"Parsed {len(templates)} valid templates from {len(records)} total records "
                f"(skipped {skipped_p1} without P1, {skipped_invalid} without criteria)"
            )
            return templates
            
        except Exception as e:
            logger.error(f"Error fetching templates: {e}")
            raise ZohoAPIError(f"Failed to fetch templates: {str(e)}")
    
    async def create_template_in_moodle(
        self,
        template: BtecTemplate,
        area_id: Optional[int] = None
    ) -> Dict[str, Any]:
        """
        Create BTEC grading definition in Moodle.
        
        Calls Moodle External API: local_moodle_zoho_sync_create_btec_definition
        
        Args:
            template: BtecTemplate to create
            area_id: Optional grading area ID to associate with
        
        Returns:
            Result dict with definition_id, status, message
        
        Raises:
            Exception: If Moodle API call fails
        """
        if not self.moodle:
            raise ValueError("Moodle client not configured")
        
        logger.info(f"Creating template in Moodle: {template.unit_name}")
        
        try:
            # Convert to Moodle format
            moodle_data = template.to_moodle_dict()
            
            # Flatten criteria array for Moodle API
            # Moodle expects: criteria[0][shortname]=P1, criteria[0][description]=..., etc.
            # No areaid - Moodle plugin will create unique area per unit
            params = {
                'name': moodle_data['name'],
                'description': moodle_data['description'],
                'zoho_unit_id': moodle_data['zoho_unit_id']
            }
            
            for idx, criterion in enumerate(moodle_data['criteria']):
                params[f'criteria[{idx}][shortname]'] = criterion['shortname']
                params[f'criteria[{idx}][description]'] = criterion['description']
                params[f'criteria[{idx}][level]'] = criterion['level']
                params[f'criteria[{idx}][sortorder]'] = criterion['sortorder']
            
            # Call Moodle External API
            # Function: local_moodle_zoho_sync_create_btec_definition
            result = self.moodle._call_api(
                'local_moodle_zoho_sync_create_btec_definition',
                params
            )
            
            if result.get('success'):
                logger.info(
                    f"✅ Created definition in Moodle: {template.unit_name} "
                    f"(ID: {result.get('definition_id')})"
                )
                
                # Update Zoho with sync status (commented out for now - format issue)
                # await self._update_zoho_sync_status(
                #     template.zoho_unit_id,
                #     synced=True
                # )
            else:
                logger.error(
                    f"❌ Failed to create definition: {result.get('message')}"
                )
            
            return result
            
        except Exception as e:
            logger.error(f"Error creating template in Moodle: {e}")
            raise
    
    async def sync_template(
        self,
        unit_id: str,
        force: bool = False
    ) -> Dict[str, Any]:
        """
        Sync single template from Zoho to Moodle.
        
        Args:
            unit_id: Zoho BTEC record ID
            force: Force sync even if already processed
        
        Returns:
            Result dict with status, message, details
        """
        # Check if already processed
        if not force and unit_id in self._processed_units:
            logger.info(f"Template {unit_id} already processed (skipping)")
            return {
                'success': True,
                'status': 'skipped',
                'message': 'Already processed',
                'unit_id': unit_id
            }
        
        try:
            # Fetch from Zoho
            template = await self.fetch_template_from_zoho(unit_id)
            
            # Create in Moodle
            moodle_result = await self.create_template_in_moodle(template)
            
            # Mark as processed
            self._processed_units.add(unit_id)
            
            return {
                'success': moodle_result.get('success', False),
                'status': 'synced' if moodle_result.get('success') else 'failed',
                'message': moodle_result.get('message', 'Unknown error'),
                'unit_id': unit_id,
                'unit_name': template.unit_name,
                'definition_id': moodle_result.get('definition_id'),
                'criteria_count': template.total_criteria_count
            }
            
        except ZohoNotFoundError:
            return {
                'success': False,
                'status': 'not_found',
                'message': f'Unit {unit_id} not found in Zoho',
                'unit_id': unit_id
            }
        except Exception as e:
            logger.error(f"Error syncing template {unit_id}: {e}")
            return {
                'success': False,
                'status': 'error',
                'message': str(e),
                'unit_id': unit_id
            }
    
    async def sync_all_templates(
        self,
        only_ready: bool = True,
        force: bool = False
    ) -> Dict[str, Any]:
        """
        Sync all templates from Zoho to Moodle.
        
        Args:
            only_ready: Only sync templates with status "Ready for use"
            force: Force sync even if already processed
        
        Returns:
            Summary dict with total, success, failed counts
        """
        print("🚀 SYNC_ALL_TEMPLATES CALLED")
        print(f"Parameters: only_ready={only_ready}, force={force}")
        
        logger.info("🚀 Starting bulk template sync")
        logger.info(f"Parameters: only_ready={only_ready}, force={force}")
        
        try:
            # Fetch all templates
            print("Step 1: Fetching templates...")
            logger.info("Step 1: Fetching templates from Zoho...")
            templates = await self.fetch_all_templates_from_zoho(only_ready=only_ready)
            
            print(f"Step 2: Got {len(templates)} templates")
            logger.info(f"Step 2: Processing {len(templates)} templates for Moodle sync")
            
            results = {
                'total': len(templates),
                'success': 0,
                'failed': 0,
                'skipped': 0,
                'details': []
            }
            
            # Sync each template
            for template in templates:
                result = await self.sync_template(
                    template.zoho_unit_id,
                    force=force
                )
                
                results['details'].append(result)
                
                if result['status'] == 'synced':
                    results['success'] += 1
                elif result['status'] == 'skipped':
                    results['skipped'] += 1
                else:
                    results['failed'] += 1
            
            logger.info(
                f"✅ Template sync complete: "
                f"{results['success']}/{results['total']} synced, "
                f"{results['failed']} failed, "
                f"{results['skipped']} skipped"
            )
            
            return results
            
        except Exception as e:
            logger.error(f"Error in bulk template sync: {e}")
            raise
