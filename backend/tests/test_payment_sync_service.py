"""
Unit Tests for PaymentSyncService

Tests read-only operations for payment data access.
"""

import pytest
from datetime import date, datetime
from decimal import Decimal
from unittest.mock import Mock, AsyncMock, patch

from app.services.payment_sync_service import (
    PaymentData,
    PaymentSummary,
    PaymentSyncService
)


# ============================================================================
# Test PaymentData
# ============================================================================

class TestPaymentData:
    """Test PaymentData class."""
    
    def test_initialization_minimal(self):
        """Test creating PaymentData with minimal fields."""
        payment = PaymentData(
            zoho_payment_id="5843017000000111111",
            payment_name="PMT-001",
            zoho_student_id="5843017000000222222"
        )
        
        assert payment.zoho_payment_id == "5843017000000111111"
        assert payment.payment_name == "PMT-001"
        assert payment.zoho_student_id == "5843017000000222222"
        assert payment.installment_no is None
        assert payment.payment_amount is None
    
    def test_initialization_full(self):
        """Test creating PaymentData with all fields."""
        payment = PaymentData(
            zoho_payment_id="5843017000000111111",
            payment_name="PMT-001",
            zoho_student_id="5843017000000222222",
            zoho_registration_id="5843017000000333333",
            installment_no=1,
            payment_amount=Decimal("5000.00"),
            payment_date=date(2025, 9, 15),
            payment_method="Bank Transfer",
            synced_to_moodle=True
        )
        
        assert payment.installment_no == 1
        assert payment.payment_amount == Decimal("5000.00")
        assert payment.payment_date == date(2025, 9, 15)
        assert payment.payment_method == "Bank Transfer"
        assert payment.synced_to_moodle is True
    
    def test_from_zoho_dict_minimal(self):
        """Test creating PaymentData from Zoho response (minimal)."""
        zoho_data = {
            'id': '5843017000000111111',
            'Name': 'PMT-001',
            'Student_ID': {'id': '5843017000000222222', 'name': 'A01B4001C'}
        }
        
        payment = PaymentData.from_zoho_dict(zoho_data)
        
        assert payment.zoho_payment_id == "5843017000000111111"
        assert payment.payment_name == "PMT-001"
        assert payment.zoho_student_id == "5843017000000222222"
    
    def test_from_zoho_dict_full(self):
        """Test creating PaymentData from complete Zoho response."""
        zoho_data = {
            'id': '5843017000000111111',
            'Name': 'PMT-001',
            'Student_ID': {'id': '5843017000000222222', 'name': 'A01B4001C'},
            'Registration_ID': {'id': '5843017000000333333', 'name': 'REG-001'},
            'Installment_No': 1,
            'Payment_Amount': 5000.00,
            'Payment_Date': '2025-09-15',
            'Payment_Method': 'Bank Transfer',
            'Synced_to_Moodle': True
        }
        
        payment = PaymentData.from_zoho_dict(zoho_data)
        
        assert payment.zoho_payment_id == "5843017000000111111"
        assert payment.payment_name == "PMT-001"
        assert payment.zoho_student_id == "5843017000000222222"
        assert payment.zoho_registration_id == "5843017000000333333"
        assert payment.installment_no == 1
        assert payment.payment_amount == Decimal("5000.00")
        assert payment.payment_date == date(2025, 9, 15)
        assert payment.payment_method == "Bank Transfer"
        assert payment.synced_to_moodle is True
    
    def test_to_dict(self):
        """Test converting PaymentData to dictionary."""
        payment = PaymentData(
            zoho_payment_id="5843017000000111111",
            payment_name="PMT-001",
            zoho_student_id="5843017000000222222",
            payment_amount=Decimal("5000.00"),
            payment_date=date(2025, 9, 15),
            payment_method="Bank Transfer"
        )
        
        result = payment.to_dict()
        
        assert result['zoho_payment_id'] == "5843017000000111111"
        assert result['payment_name'] == "PMT-001"
        assert result['payment_amount'] == 5000.00
        assert result['payment_date'] == "2025-09-15"
        assert result['payment_method'] == "Bank Transfer"


# ============================================================================
# Test PaymentSummary
# ============================================================================

class TestPaymentSummary:
    """Test PaymentSummary class."""
    
    def test_balance_calculation(self):
        """Test balance calculation."""
        summary = PaymentSummary(
            zoho_student_id="5843017000000222222",
            total_fees=Decimal("15000.00"),
            total_paid=Decimal("10000.00")
        )
        
        assert summary.balance == Decimal("5000.00")
        assert not summary.is_fully_paid
    
    def test_fully_paid(self):
        """Test fully paid status."""
        summary = PaymentSummary(
            zoho_student_id="5843017000000222222",
            total_fees=Decimal("15000.00"),
            total_paid=Decimal("15000.00")
        )
        
        assert summary.balance == Decimal("0")
        assert summary.is_fully_paid
    
    def test_overpaid(self):
        """Test overpaid scenario."""
        summary = PaymentSummary(
            zoho_student_id="5843017000000222222",
            total_fees=Decimal("15000.00"),
            total_paid=Decimal("16000.00")
        )
        
        assert summary.balance == Decimal("-1000.00")
        assert summary.is_fully_paid  # Negative balance = fully paid
    
    def test_to_dict(self):
        """Test converting PaymentSummary to dictionary."""
        payments = [
            PaymentData(
                zoho_payment_id="111",
                payment_name="PMT-001",
                zoho_student_id="222",
                payment_amount=Decimal("5000.00"),
                payment_date=date(2025, 9, 15)
            )
        ]
        
        summary = PaymentSummary(
            zoho_student_id="5843017000000222222",
            total_fees=Decimal("15000.00"),
            total_paid=Decimal("10000.00"),
            payment_count=3,
            payments=payments,
            last_payment_date=date(2025, 12, 1)
        )
        
        result = summary.to_dict()
        
        assert result['total_fees'] == 15000.00
        assert result['total_paid'] == 10000.00
        assert result['balance'] == 5000.00
        assert result['payment_count'] == 3
        assert result['is_fully_paid'] is False
        assert result['last_payment_date'] == "2025-12-01"
        assert len(result['payments']) == 1


# ============================================================================
# Test PaymentSyncService
# ============================================================================

class TestPaymentSyncService:
    """Test PaymentSyncService class."""
    
    @pytest.fixture
    def mock_zoho(self):
        """Create mock Zoho client."""
        mock = Mock()
        mock.search_records = AsyncMock()
        mock.get_record = AsyncMock()
        mock.get_records = AsyncMock()
        mock.update_record = AsyncMock()
        return mock
    
    @pytest.fixture
    def service(self, mock_zoho):
        """Create PaymentSyncService with mock client."""
        return PaymentSyncService(mock_zoho)
    
    @pytest.mark.asyncio
    async def test_get_student_payments(self, service, mock_zoho):
        """Test getting all payments for a student."""
        # Mock search results
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000111111',
                'Name': 'PMT-001',
                'Student_ID': {'id': '5843017000000222222', 'name': 'A01B4001C'},
                'Payment_Amount': 5000.00,
                'Payment_Date': '2025-09-15',
                'Payment_Method': 'Bank Transfer'
            },
            {
                'id': '5843017000000111112',
                'Name': 'PMT-002',
                'Student_ID': {'id': '5843017000000222222', 'name': 'A01B4001C'},
                'Payment_Amount': 3000.00,
                'Payment_Date': '2025-12-01',
                'Payment_Method': 'Cash'
            }
        ]
        
        payments = await service.get_student_payments("5843017000000222222")
        
        assert len(payments) == 2
        assert payments[0].payment_name == "PMT-001"
        assert payments[0].payment_amount == Decimal("5000.00")
        assert payments[1].payment_name == "PMT-002"
        assert payments[1].payment_amount == Decimal("3000.00")
        
        # Verify search was called correctly
        mock_zoho.search_records.assert_called_once_with(
            'BTEC_Payments',
            '(Student_ID:equals:5843017000000222222)',
            page=1,
            per_page=200
        )
    
    @pytest.mark.asyncio
    async def test_get_student_payments_invalid_id(self, service):
        """Test error handling for invalid student ID."""
        with pytest.raises(ValueError, match="Student ID is required"):
            await service.get_student_payments("")
    
    @pytest.mark.asyncio
    async def test_get_payment_by_id(self, service, mock_zoho):
        """Test getting a specific payment by ID."""
        mock_zoho.get_record.return_value = {
            'id': '5843017000000111111',
            'Name': 'PMT-001',
            'Student_ID': {'id': '5843017000000222222', 'name': 'A01B4001C'},
            'Payment_Amount': 5000.00,
            'Payment_Date': '2025-09-15'
        }
        
        payment = await service.get_payment_by_id("5843017000000111111")
        
        assert payment is not None
        assert payment.zoho_payment_id == "5843017000000111111"
        assert payment.payment_name == "PMT-001"
        
        mock_zoho.get_record.assert_called_once_with(
            'BTEC_Payments',
            '5843017000000111111'
        )
    
    @pytest.mark.asyncio
    async def test_get_payment_by_id_not_found(self, service, mock_zoho):
        """Test getting payment that doesn't exist."""
        mock_zoho.get_record.return_value = None
        
        payment = await service.get_payment_by_id("invalid_id")
        
        assert payment is None
    
    @pytest.mark.asyncio
    async def test_calculate_payment_summary(self, service, mock_zoho):
        """Test calculating payment summary for a student."""
        # Mock search_records for get_student_payments
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000111111',
                'Name': 'PMT-001',
                'Student_ID': '5843017000000222222',
                'Payment_Amount': 5000.00,
                'Payment_Date': '2025-09-15'
            },
            {
                'id': '5843017000000111112',
                'Name': 'PMT-002',
                'Student_ID': '5843017000000222222',
                'Payment_Amount': 3000.00,
                'Payment_Date': '2025-12-01'
            }
        ]
        
        summary = await service.calculate_payment_summary(
            "5843017000000222222",
            total_fees=Decimal("15000.00")
        )
        
        assert summary.total_fees == Decimal("15000.00")
        assert summary.total_paid == Decimal("8000.00")
        assert summary.balance == Decimal("7000.00")
        assert summary.payment_count == 2
        assert summary.last_payment_date == date(2025, 12, 1)
        assert not summary.is_fully_paid
    
    @pytest.mark.asyncio
    async def test_search_payments_by_date_range(self, service, mock_zoho):
        """Test searching payments by date range."""
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000111111',
                'Name': 'PMT-001',
                'Student_ID': '5843017000000222222',
                'Payment_Amount': 5000.00,
                'Payment_Date': '2025-10-15'
            }
        ]
        
        payments = await service.search_payments_by_date_range(
            start_date=date(2025, 10, 1),
            end_date=date(2025, 10, 31)
        )
        
        assert len(payments) == 1
        assert payments[0].payment_date == date(2025, 10, 15)
        
        # Verify criteria includes date range
        call_args = mock_zoho.search_records.call_args
        criteria = call_args[0][1]
        assert 'Payment_Date:greater_equal:2025-10-01' in criteria
        assert 'Payment_Date:less_equal:2025-10-31' in criteria
    
    @pytest.mark.asyncio
    async def test_search_payments_by_date_range_with_student(self, service, mock_zoho):
        """Test searching payments by date range for specific student."""
        mock_zoho.search_records.return_value = []
        
        await service.search_payments_by_date_range(
            start_date=date(2025, 10, 1),
            end_date=date(2025, 10, 31),
            zoho_student_id="5843017000000222222"
        )
        
        # Verify criteria includes student ID
        call_args = mock_zoho.search_records.call_args
        criteria = call_args[0][1]
        assert 'Student_ID:equals:5843017000000222222' in criteria
    
    @pytest.mark.asyncio
    async def test_search_payments_by_method(self, service, mock_zoho):
        """Test searching payments by payment method."""
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000111111',
                'Name': 'PMT-001',
                'Student_ID': '5843017000000222222',
                'Payment_Method': 'Bank Transfer'
            }
        ]
        
        payments = await service.search_payments_by_method("Bank Transfer")
        
        assert len(payments) == 1
        assert payments[0].payment_method == "Bank Transfer"
        
        # Verify criteria
        call_args = mock_zoho.search_records.call_args
        criteria = call_args[0][1]
        assert 'Payment_Method:equals:Bank Transfer' in criteria
    
    @pytest.mark.asyncio
    async def test_get_recent_payments(self, service, mock_zoho):
        """Test getting most recent payments."""
        mock_zoho.search_records.return_value = [
            {
                'id': '5843017000000111111',
                'Name': 'PMT-001',
                'Student_ID': '5843017000000222222',
                'Payment_Date': '2025-09-15'
            },
            {
                'id': '5843017000000111112',
                'Name': 'PMT-002',
                'Student_ID': '5843017000000222222',
                'Payment_Date': '2025-12-01'
            },
            {
                'id': '5843017000000111113',
                'Name': 'PMT-003',
                'Student_ID': '5843017000000222222',
                'Payment_Date': '2025-10-15'
            }
        ]
        
        payments = await service.get_recent_payments(
            limit=2,
            zoho_student_id="5843017000000222222"
        )
        
        # Should return 2 most recent (sorted by date desc)
        assert len(payments) == 2
        assert payments[0].payment_date == date(2025, 12, 1)  # Most recent
        assert payments[1].payment_date == date(2025, 10, 15)  # Second most recent
    
    @pytest.mark.asyncio
    async def test_verify_payment_sync_status(self, service, mock_zoho):
        """Test checking payment sync status."""
        mock_zoho.get_record.return_value = {
            'id': '5843017000000111111',
            'Name': 'PMT-001',
            'Student_ID': '5843017000000222222',
            'Synced_to_Moodle': True
        }
        
        is_synced = await service.verify_payment_sync_status("5843017000000111111")
        
        assert is_synced is True
    
    @pytest.mark.asyncio
    async def test_verify_payment_sync_status_mark_synced(self, service, mock_zoho):
        """Test marking payment as synced."""
        # First call returns not synced
        mock_zoho.get_record.return_value = {
            'id': '5843017000000111111',
            'Name': 'PMT-001',
            'Student_ID': '5843017000000222222',
            'Synced_to_Moodle': False
        }
        
        # Mock update success
        mock_zoho.update_record.return_value = {
            'code': 'SUCCESS',
            'details': {'id': '5843017000000111111'}
        }
        
        is_synced = await service.verify_payment_sync_status(
            "5843017000000111111",
            mark_synced=True
        )
        
        assert is_synced is True
        
        # Verify update was called
        mock_zoho.update_record.assert_called_once_with(
            'BTEC_Payments',
            '5843017000000111111',
            {'Synced_to_Moodle': True}
        )
