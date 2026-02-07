"""
Zoho CRM API Client

Complete client following ZOHO_API_CONTRACT.md strictly.

⚠️ CRITICAL: Use ONLY module names from contract:
- Products (NOT BTEC_Programs)
- BTEC (NOT BTEC_Units)
- BTEC_Students, BTEC_Teachers, etc.
"""

import logging
from typing import Dict, List, Optional, Any
from urllib.parse import urlencode
import httpx

from .auth import ZohoAuthClient
from .exceptions import (
    ZohoAPIError,
    ZohoNotFoundError,
    ZohoRateLimitError,
    ZohoValidationError,
    ZohoInvalidModuleError
)

logger = logging.getLogger(__name__)


class ZohoClient:
    """
    Zoho CRM API v2 client.
    
    Usage:
        auth = ZohoAuthClient(client_id, client_secret, refresh_token)
        zoho = ZohoClient(auth, organization_id="12345")
        
        # Get record
        student = await zoho.get_record('BTEC_Students', record_id)
        
        # Search
        students = await zoho.search_records(
            'BTEC_Students',
            "Academic_Email = 'student@example.com'"
        )
        
        # Create
        result = await zoho.create_record('BTEC_Grades', {
            "Student": student_id,
            "Grade": "Pass",
            "Learning_Outcomes_Assessm": [...]
        })
    """
    
    # Valid module names per ZOHO_API_CONTRACT.md
    VALID_MODULES = {
        'Products',              # BTEC Programs
        'BTEC',                  # BTEC Units
        'BTEC_Students',
        'BTEC_Teachers',
        'BTEC_Registrations',
        'BTEC_Classes',
        'BTEC_Enrollments',
        'BTEC_Payments',
        'BTEC_Grades',
    }
    
    # Common module mistakes (for helpful error messages)
    MODULE_CORRECTIONS = {
        'BTEC_Programs': 'Products',
        'BTEC_Units': 'BTEC',
        'Programs': 'Products',
        'Units': 'BTEC',
    }
    
    def __init__(
        self,
        auth_client: ZohoAuthClient,
        organization_id: Optional[str] = None,
        region: str = "com",
        timeout: float = 30.0
    ):
        """
        Initialize Zoho CRM client.
        
        Args:
            auth_client: Authenticated ZohoAuthClient instance
            organization_id: Zoho organization ID (optional)
            region: Zoho data center (com, eu, in, au)
            timeout: Request timeout in seconds
        """
        self.auth = auth_client
        self.organization_id = organization_id
        self.region = region
        self.timeout = timeout
        
        # Base URL based on region
        if region == "com":
            self.base_url = "https://www.zohoapis.com/crm/v2"
        else:
            self.base_url = f"https://www.zohoapis.{region}/crm/v2"
    
    def _validate_module(self, module: str) -> None:
        """
        Validate module name against contract.
        
        Raises:
            ZohoInvalidModuleError: If module name is invalid
        """
        if module not in self.VALID_MODULES:
            # Check for common mistakes
            suggestion = self.MODULE_CORRECTIONS.get(module)
            if suggestion:
                raise ZohoInvalidModuleError(
                    f"Invalid module '{module}'. Did you mean '{suggestion}'? "
                    f"See ZOHO_API_CONTRACT.md for valid module names."
                )
            else:
                raise ZohoInvalidModuleError(
                    f"Invalid module '{module}'. "
                    f"Valid modules: {', '.join(sorted(self.VALID_MODULES))}. "
                    f"See ZOHO_API_CONTRACT.md."
                )
    
    async def _make_request(
        self,
        method: str,
        endpoint: str,
        params: Optional[Dict] = None,
        json_data: Optional[Dict] = None
    ) -> Dict:
        """
        Make authenticated request to Zoho API.
        
        Args:
            method: HTTP method (GET, POST, PUT, DELETE)
            endpoint: API endpoint (e.g., '/BTEC_Students/123456')
            params: Query parameters
            json_data: JSON body data
        
        Returns:
            Response data as dict
        
        Raises:
            ZohoAPIError: For API errors
        """
        url = f"{self.base_url}{endpoint}"
        
        # Get access token
        access_token = await self.auth.get_access_token()
        
        headers = {
            'Authorization': f'Zoho-oauthtoken {access_token}',
            'Content-Type': 'application/json'
        }
        
        # Add organization ID if provided
        if self.organization_id:
            headers['orgId'] = self.organization_id
        
        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                response = await client.request(
                    method=method,
                    url=url,
                    headers=headers,
                    params=params,
                    json=json_data
                )
                
                # Handle different status codes
                if response.status_code == 200:
                    return response.json()
                
                elif response.status_code == 201:
                    return response.json()
                
                elif response.status_code == 204:
                    return {'status': 'success'}
                
                elif response.status_code == 404:
                    raise ZohoNotFoundError(
                        f"Resource not found: {endpoint}",
                        status_code=404
                    )
                
                elif response.status_code == 429:
                    # Rate limit
                    retry_after = response.headers.get('Retry-After', 60)
                    raise ZohoRateLimitError(
                        "Rate limit exceeded",
                        retry_after=int(retry_after),
                        status_code=429
                    )
                
                elif response.status_code == 400:
                    error_data = response.json() if response.text else {}
                    raise ZohoValidationError(
                        f"Validation error: {error_data}",
                        status_code=400,
                        response_data=error_data
                    )
                
                else:
                    # Generic error
                    error_data = response.json() if response.text else {}
                    raise ZohoAPIError(
                        f"API error: {error_data}",
                        status_code=response.status_code,
                        response_data=error_data
                    )
        
        except httpx.HTTPError as e:
            logger.error(f"HTTP error calling Zoho API: {e}")
            raise ZohoAPIError(f"HTTP error: {str(e)}")
        
        except ZohoAPIError:
            # Re-raise our custom exceptions
            raise
        
        except Exception as e:
            logger.error(f"Unexpected error calling Zoho API: {e}")
            raise ZohoAPIError(f"Unexpected error: {str(e)}")
    
    async def get_record(
        self,
        module: str,
        record_id: str,
        fields: Optional[List[str]] = None
    ) -> Dict:
        """
        Get single record by ID.
        
        Args:
            module: Module API name (e.g., 'BTEC_Students', 'Products', 'BTEC')
            record_id: Zoho record ID
            fields: List of field API names to return (optional, returns all if None)
        
        Returns:
            Record data as dict
        
        Example:
            student = await zoho.get_record('BTEC_Students', '5843017000000123456')
            unit = await zoho.get_record('BTEC', '5843017000000789012')
            program = await zoho.get_record('Products', '5843017000000345678')
        
        Raises:
            ZohoInvalidModuleError: If module name is invalid
            ZohoNotFoundError: If record not found
        """
        self._validate_module(module)
        
        endpoint = f"/{module}/{record_id}"
        params = {}
        
        if fields:
            params['fields'] = ','.join(fields)
        
        logger.info(f"Fetching {module} record {record_id}")
        
        response = await self._make_request('GET', endpoint, params=params)
        
        # Zoho wraps single record in 'data' array
        if 'data' in response and len(response['data']) > 0:
            return response['data'][0]
        else:
            raise ZohoNotFoundError(f"Record {record_id} not found in {module}")
    
    async def get_records(
        self,
        module: str,
        page: int = 1,
        per_page: int = 200,
        fields: Optional[List[str]] = None,
        sort_by: Optional[str] = None,
        sort_order: str = 'asc'
    ) -> Dict:
        """
        Get multiple records with pagination.
        
        Args:
            module: Module API name
            page: Page number (1-indexed)
            per_page: Records per page (max 200)
            fields: List of field API names to return
            sort_by: Field to sort by
            sort_order: 'asc' or 'desc'
        
        Returns:
            Dict with 'data' (list of records) and 'info' (pagination info)
        
        Example:
            response = await zoho.get_records('BTEC_Students', page=1, per_page=100)
            students = response['data']
            has_more = response['info']['more_records']
        """
        self._validate_module(module)
        
        endpoint = f"/{module}"
        params = {
            'page': page,
            'per_page': min(per_page, 200)  # Max 200 per Zoho docs
        }
        
        if fields:
            params['fields'] = ','.join(fields)
        
        if sort_by:
            params['sort_by'] = sort_by
            params['sort_order'] = sort_order
        
        logger.info(f"Fetching {module} records (page {page}, {per_page} per page)")
        
        return await self._make_request('GET', endpoint, params=params)
    
    async def search_records(
        self,
        module: str,
        criteria: str,
        fields: Optional[List[str]] = None,
        page: int = 1,
        per_page: int = 200
    ) -> List[Dict]:
        """
        Search records with criteria.
        
        Args:
            module: Module API name
            criteria: Search criteria (e.g., "(Academic_Email:equals:john@example.com)")
            fields: List of field API names to return
            page: Page number
            per_page: Records per page
        
        Returns:
            List of matching records
        
        Example:
            students = await zoho.search_records(
                'BTEC_Students',
                "(Academic_Email:equals:student@example.com)"
            )
            
            # Composite key search
            grades = await zoho.search_records(
                'BTEC_Grades',
                "(Moodle_Grade_Composite_Key:equals:123_456)"
            )
        """
        self._validate_module(module)
        
        endpoint = f"/{module}/search"
        params = {
            'criteria': criteria,
            'page': page,
            'per_page': min(per_page, 200)
        }
        
        if fields:
            params['fields'] = ','.join(fields)
        
        logger.info(f"Searching {module} with criteria: {criteria}")
        
        response = await self._make_request('GET', endpoint, params=params)
        
        return response.get('data', [])
    
    async def create_record(self, module: str, data: Dict) -> Dict:
        """
        Create new record.
        
        Args:
            module: Module API name
            data: Record data with field API names
        
        Returns:
            Created record info (id, status, etc.)
        
        Example:
            result = await zoho.create_record('BTEC_Grades', {
                "Student": "5843017000000123456",
                "Class": "5843017000000789012",
                "BTEC_Unit": "5843017000000345678",
                "Grade": "Pass",
                "Moodle_Grade_Composite_Key": "123_456",
                "Learning_Outcomes_Assessm": [
                    {
                        "LO_Code": "P1",
                        "LO_Score": "Achieved",
                        "LO_Title": "Explain...",
                        "LO_Definition": "...",
                        "LO_Feedback": "Good work"
                    }
                ]
            })
            
            record_id = result['details']['id']
        
        Raises:
            ZohoValidationError: If data validation fails
        """
        self._validate_module(module)
        
        endpoint = f"/{module}"
        payload = {'data': [data]}  # Zoho expects array
        
        logger.info(f"Creating {module} record")
        
        response = await self._make_request('POST', endpoint, json_data=payload)
        
        # Return first result
        if 'data' in response and len(response['data']) > 0:
            result = response['data'][0]
            if result.get('code') == 'SUCCESS':
                logger.info(f"Created {module} record: {result['details']['id']}")
                return result
            else:
                raise ZohoValidationError(
                    f"Create failed: {result.get('message', 'Unknown error')}",
                    response_data=result
                )
        else:
            raise ZohoAPIError("Invalid create response", response_data=response)
    
    async def update_record(
        self,
        module: str,
        record_id: str,
        data: Dict
    ) -> Dict:
        """
        Update existing record.
        
        Args:
            module: Module API name
            record_id: Zoho record ID
            data: Updated fields (field API names only)
        
        Returns:
            Update result
        
        Example:
            await zoho.update_record('BTEC_Students', record_id, {
                "Student_Moodle_ID": "12345",
                "Synced_to_Moodle": True
            })
        """
        self._validate_module(module)
        
        endpoint = f"/{module}/{record_id}"
        payload = {'data': [data]}
        
        logger.info(f"Updating {module} record {record_id}")
        
        response = await self._make_request('PUT', endpoint, json_data=payload)
        
        if 'data' in response and len(response['data']) > 0:
            result = response['data'][0]
            if result.get('code') == 'SUCCESS':
                logger.info(f"Updated {module} record {record_id}")
                return result
            else:
                raise ZohoValidationError(
                    f"Update failed: {result.get('message', 'Unknown error')}",
                    response_data=result
                )
        else:
            raise ZohoAPIError("Invalid update response", response_data=response)
    
    async def delete_record(self, module: str, record_id: str) -> Dict:
        """
        Delete record.
        
        Args:
            module: Module API name
            record_id: Zoho record ID
        
        Returns:
            Delete result
        """
        self._validate_module(module)
        
        endpoint = f"/{module}/{record_id}"
        
        logger.info(f"Deleting {module} record {record_id}")
        
        response = await self._make_request('DELETE', endpoint)
        
        if 'data' in response and len(response['data']) > 0:
            result = response['data'][0]
            if result.get('code') == 'SUCCESS':
                logger.info(f"Deleted {module} record {record_id}")
                return result
            else:
                raise ZohoAPIError(
                    f"Delete failed: {result.get('message', 'Unknown error')}",
                    response_data=result
                )
        else:
            raise ZohoAPIError("Invalid delete response", response_data=response)
    
    async def upsert_record(
        self,
        module: str,
        data: Dict,
        duplicate_check_fields: List[str]
    ) -> Dict:
        """
        Create or update record based on duplicate check fields.
        
        Args:
            module: Module API name
            data: Record data
            duplicate_check_fields: Fields to check for duplicates
        
        Returns:
            Upsert result
        
        Example:
            # Upsert grade by composite key
            await zoho.upsert_record(
                'BTEC_Grades',
                grade_data,
                duplicate_check_fields=['Moodle_Grade_Composite_Key']
            )
        """
        self._validate_module(module)
        
        endpoint = f"/{module}/upsert"
        payload = {
            'data': [data],
            'duplicate_check_fields': duplicate_check_fields
        }
        
        logger.info(f"Upserting {module} record")
        
        response = await self._make_request('POST', endpoint, json_data=payload)
        
        if 'data' in response and len(response['data']) > 0:
            return response['data'][0]
        else:
            raise ZohoAPIError("Invalid upsert response", response_data=response)
