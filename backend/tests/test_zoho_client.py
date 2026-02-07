"""
Unit tests for Zoho CRM API client
"""

import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from datetime import datetime, timedelta

from app.infra.zoho.auth import ZohoAuthClient
from app.infra.zoho.client import ZohoClient
from app.infra.zoho.exceptions import (
    ZohoAuthError,
    ZohoNotFoundError,
    ZohoRateLimitError,
    ZohoValidationError,
    ZohoInvalidModuleError
)


class TestZohoAuthClient:
    """Test OAuth authentication client."""
    
    @pytest.fixture
    def auth_client(self):
        """Create auth client for testing."""
        return ZohoAuthClient(
            client_id="test_client_id",
            client_secret="test_secret",
            refresh_token="test_refresh_token"
        )
    
    def test_init_validates_credentials(self):
        """Test that init validates required credentials."""
        with pytest.raises(ValueError):
            ZohoAuthClient(client_id="", client_secret="", refresh_token="")
    
    def test_token_caching(self, auth_client):
        """Test that tokens are cached when valid."""
        # Set cached token
        auth_client._access_token = "cached_token"
        auth_client._expires_at = datetime.now() + timedelta(hours=1)
        
        # Should be valid
        assert auth_client._is_token_valid() is True
        
        # Expired token
        auth_client._expires_at = datetime.now() - timedelta(minutes=1)
        assert auth_client._is_token_valid() is False
    
    @pytest.mark.asyncio
    async def test_get_access_token_uses_cache(self, auth_client):
        """Test that cached tokens are reused."""
        # Set valid cached token
        auth_client._access_token = "cached_token"
        auth_client._expires_at = datetime.now() + timedelta(hours=1)
        
        token = await auth_client.get_access_token()
        
        assert token == "cached_token"
    
    @pytest.mark.asyncio
    async def test_token_refresh_success(self, auth_client):
        """Test successful token refresh."""
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = {
            'access_token': 'new_token',
            'expires_in': 3600
        }
        
        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.post = AsyncMock(
                return_value=mock_response
            )
            
            token = await auth_client.get_access_token(force_refresh=True)
            
            assert token == 'new_token'
            assert auth_client._access_token == 'new_token'
            assert auth_client._expires_at is not None
    
    @pytest.mark.asyncio
    async def test_token_refresh_failure(self, auth_client):
        """Test token refresh error handling."""
        mock_response = MagicMock()
        mock_response.status_code = 400
        mock_response.json.return_value = {'error': 'invalid_grant'}
        mock_response.text = '{"error": "invalid_grant"}'
        
        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.post = AsyncMock(
                return_value=mock_response
            )
            
            with pytest.raises(ZohoAuthError):
                await auth_client.get_access_token(force_refresh=True)


class TestZohoClient:
    """Test Zoho API client."""
    
    @pytest.fixture
    def mock_auth(self):
        """Create mock auth client."""
        auth = MagicMock(spec=ZohoAuthClient)
        auth.get_access_token = AsyncMock(return_value="test_token")
        return auth
    
    @pytest.fixture
    def zoho_client(self, mock_auth):
        """Create Zoho client for testing."""
        return ZohoClient(auth_client=mock_auth)
    
    def test_module_validation_valid(self, zoho_client):
        """Test that valid module names pass validation."""
        # Should not raise
        zoho_client._validate_module('BTEC_Students')
        zoho_client._validate_module('Products')
        zoho_client._validate_module('BTEC')
    
    def test_module_validation_invalid(self, zoho_client):
        """Test that invalid module names raise error."""
        with pytest.raises(ZohoInvalidModuleError) as exc_info:
            zoho_client._validate_module('BTEC_Programs')
        
        assert 'Products' in str(exc_info.value)  # Suggestion
    
    @pytest.mark.asyncio
    async def test_get_record_success(self, zoho_client):
        """Test successful record fetch."""
        mock_response = {
            'data': [{
                'id': '123456',
                'Name': 'Test Student',
                'Academic_Email': 'test@example.com'
            }]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_response)):
            record = await zoho_client.get_record('BTEC_Students', '123456')
            
            assert record['id'] == '123456'
            assert record['Name'] == 'Test Student'
    
    @pytest.mark.asyncio
    async def test_get_record_not_found(self, zoho_client):
        """Test record not found handling."""
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value={'data': []})):
            with pytest.raises(ZohoNotFoundError):
                await zoho_client.get_record('BTEC_Students', '999999')
    
    @pytest.mark.asyncio
    async def test_search_records(self, zoho_client):
        """Test search functionality."""
        mock_response = {
            'data': [
                {'id': '123', 'Academic_Email': 'test@example.com'},
                {'id': '456', 'Academic_Email': 'test2@example.com'}
            ]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_response)):
            results = await zoho_client.search_records(
                'BTEC_Students',
                "(Academic_Email:equals:test@example.com)"
            )
            
            assert len(results) == 2
            assert results[0]['id'] == '123'
    
    @pytest.mark.asyncio
    async def test_create_record_success(self, zoho_client):
        """Test successful record creation."""
        mock_response = {
            'data': [{
                'code': 'SUCCESS',
                'details': {
                    'id': '123456'
                },
                'message': 'record added'
            }]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_response)):
            result = await zoho_client.create_record('BTEC_Students', {
                'Name': 'New Student',
                'Academic_Email': 'new@example.com'
            })
            
            assert result['code'] == 'SUCCESS'
            assert result['details']['id'] == '123456'
    
    @pytest.mark.asyncio
    async def test_create_record_validation_error(self, zoho_client):
        """Test validation error handling."""
        mock_response = {
            'data': [{
                'code': 'INVALID_DATA',
                'message': 'Email is required'
            }]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_response)):
            with pytest.raises(ZohoValidationError):
                await zoho_client.create_record('BTEC_Students', {
                    'Name': 'Test'
                })
    
    @pytest.mark.asyncio
    async def test_update_record(self, zoho_client):
        """Test record update."""
        mock_response = {
            'data': [{
                'code': 'SUCCESS',
                'details': {
                    'id': '123456'
                }
            }]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_response)):
            result = await zoho_client.update_record(
                'BTEC_Students',
                '123456',
                {'Student_Moodle_ID': '789'}
            )
            
            assert result['code'] == 'SUCCESS'
    
    @pytest.mark.asyncio
    async def test_rate_limit_handling(self, zoho_client):
        """Test rate limit exception."""
        with patch.object(
            zoho_client,
            '_make_request',
            new=AsyncMock(side_effect=ZohoRateLimitError("Rate limit", retry_after=60))
        ):
            with pytest.raises(ZohoRateLimitError) as exc_info:
                await zoho_client.get_record('BTEC_Students', '123')
            
            assert exc_info.value.retry_after == 60


class TestZohoGradingIntegration:
    """Test grading-specific integration."""
    
    @pytest.fixture
    def mock_auth(self):
        auth = MagicMock(spec=ZohoAuthClient)
        auth.get_access_token = AsyncMock(return_value="test_token")
        return auth
    
    @pytest.fixture
    def zoho_client(self, mock_auth):
        return ZohoClient(auth_client=mock_auth)
    
    @pytest.mark.asyncio
    async def test_fetch_unit_template(self, zoho_client):
        """Test fetching grading template from BTEC (Units) module."""
        mock_unit = {
            'data': [{
                'id': '123456',
                'Name': 'Unit 1 - Programming',
                'P1_description': 'Explain components',
                'P2_description': 'Describe process',
                'M1_description': 'Analyze impact',
                'D1_description': 'Evaluate effectiveness'
            }]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_unit)):
            # Fetch unit (correct module name: BTEC, not BTEC_Units)
            unit = await zoho_client.get_record('BTEC', '123456')
            
            assert unit['Name'] == 'Unit 1 - Programming'
            assert 'P1_description' in unit
            assert 'M1_description' in unit
    
    @pytest.mark.asyncio
    async def test_create_grade_with_subform(self, zoho_client):
        """Test creating grade record with Learning_Outcomes_Assessm subform."""
        grade_data = {
            "Student": "5843017000000111111",
            "Class": "5843017000000222222",
            "BTEC_Unit": "5843017000000333333",
            "Grade": "Pass",
            "Moodle_Grade_Composite_Key": "123_456",
            "Learning_Outcomes_Assessm": [
                {
                    "LO_Code": "P1",
                    "LO_Title": "Explain components",
                    "LO_Score": "Achieved",
                    "LO_Definition": "Explain the components...",
                    "LO_Feedback": "Good work"
                },
                {
                    "LO_Code": "P2",
                    "LO_Score": "Not Achieved",
                    "LO_Feedback": "Needs improvement"
                }
            ]
        }
        
        mock_response = {
            'data': [{
                'code': 'SUCCESS',
                'details': {'id': '5843017000000444444'}
            }]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_response)):
            result = await zoho_client.create_record('BTEC_Grades', grade_data)
            
            assert result['code'] == 'SUCCESS'
            assert result['details']['id'] == '5843017000000444444'
    
    @pytest.mark.asyncio
    async def test_search_by_composite_key(self, zoho_client):
        """Test searching grades by composite key."""
        mock_response = {
            'data': [{
                'id': '123456',
                'Moodle_Grade_Composite_Key': '123_456',
                'Grade': 'Pass'
            }]
        }
        
        with patch.object(zoho_client, '_make_request', new=AsyncMock(return_value=mock_response)):
            results = await zoho_client.search_records(
                'BTEC_Grades',
                "(Moodle_Grade_Composite_Key:equals:123_456)"
            )
            
            assert len(results) == 1
            assert results[0]['Moodle_Grade_Composite_Key'] == '123_456'


if __name__ == '__main__':
    pytest.main([__file__, '-v'])
