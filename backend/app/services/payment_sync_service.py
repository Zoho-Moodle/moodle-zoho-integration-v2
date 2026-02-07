"""
Payment Sync Service - Read-Only Operations

This service provides READ-ONLY access to payment data from Zoho CRM.
Zoho is the source of truth for payment information - Moodle only displays it.

Module: BTEC_Payments
Purpose: Display payment history and balance on Moodle student dashboard

Key Operations:
- Get student payment history
- Calculate payment balance
- Get payment details by ID
- Search payments by date range or status

Note: NO create/update operations - this is read-only from Moodle's perspective.
"""

from typing import Dict, List, Optional, Any
from datetime import datetime, date
from decimal import Decimal
import logging

logger = logging.getLogger(__name__)


class PaymentData:
    """
    Represents a payment record from Zoho BTEC_Payments module.
    
    Read-only data structure for displaying payment information in Moodle.
    """
    
    def __init__(
        self,
        zoho_payment_id: str,
        payment_name: str,  # Payment ID (Name field in Zoho)
        zoho_student_id: str,
        zoho_registration_id: Optional[str] = None,
        installment_no: Optional[int] = None,
        payment_amount: Optional[Decimal] = None,
        payment_date: Optional[date] = None,
        payment_method: Optional[str] = None,
        synced_to_moodle: bool = False,
        raw_data: Optional[Dict] = None
    ):
        self.zoho_payment_id = zoho_payment_id
        self.payment_name = payment_name
        self.zoho_student_id = zoho_student_id
        self.zoho_registration_id = zoho_registration_id
        self.installment_no = installment_no
        self.payment_amount = payment_amount
        self.payment_date = payment_date
        self.payment_method = payment_method
        self.synced_to_moodle = synced_to_moodle
        self.raw_data = raw_data or {}
    
    @classmethod
    def from_zoho_dict(cls, zoho_data: Dict) -> 'PaymentData':
        """
        Create PaymentData from Zoho API response.
        
        Args:
            zoho_data: Raw response from Zoho BTEC_Payments module
            
        Returns:
            PaymentData instance
        """
        # Extract student ID (may be dict with id/name or just string)
        student_id = zoho_data.get('Student_ID')
        if isinstance(student_id, dict):
            student_id = student_id.get('id', '')
        
        # Extract registration ID (may be dict or string)
        registration_id = zoho_data.get('Registration_ID')
        if isinstance(registration_id, dict):
            registration_id = registration_id.get('id', '')
        
        # Parse payment amount
        amount = zoho_data.get('Payment_Amount')
        if amount is not None:
            try:
                amount = Decimal(str(amount))
            except:
                amount = None
        
        # Parse payment date
        payment_date = None
        date_str = zoho_data.get('Payment_Date')
        if date_str:
            try:
                payment_date = datetime.strptime(date_str, '%Y-%m-%d').date()
            except:
                logger.warning(f"Could not parse payment date: {date_str}")
        
        # Parse installment number
        installment = zoho_data.get('Installment_No')
        if installment is not None:
            try:
                installment = int(installment)
            except:
                installment = None
        
        return cls(
            zoho_payment_id=zoho_data.get('id', ''),
            payment_name=zoho_data.get('Name', ''),
            zoho_student_id=student_id,
            zoho_registration_id=registration_id,
            installment_no=installment,
            payment_amount=amount,
            payment_date=payment_date,
            payment_method=zoho_data.get('Payment_Method'),
            synced_to_moodle=zoho_data.get('Synced_to_Moodle', False),
            raw_data=zoho_data
        )
    
    def to_dict(self) -> Dict:
        """
        Convert to dictionary for Moodle display.
        
        Returns:
            Dictionary with payment information
        """
        return {
            'zoho_payment_id': self.zoho_payment_id,
            'payment_name': self.payment_name,
            'zoho_student_id': self.zoho_student_id,
            'zoho_registration_id': self.zoho_registration_id,
            'installment_no': self.installment_no,
            'payment_amount': float(self.payment_amount) if self.payment_amount else None,
            'payment_date': self.payment_date.isoformat() if self.payment_date else None,
            'payment_method': self.payment_method,
            'synced_to_moodle': self.synced_to_moodle
        }


class PaymentSummary:
    """
    Summary of payment information for a student.
    Used for displaying financial status on Moodle dashboard.
    """
    
    def __init__(
        self,
        zoho_student_id: str,
        total_fees: Decimal = Decimal('0'),
        total_paid: Decimal = Decimal('0'),
        payment_count: int = 0,
        payments: Optional[List[PaymentData]] = None,
        last_payment_date: Optional[date] = None
    ):
        self.zoho_student_id = zoho_student_id
        self.total_fees = total_fees
        self.total_paid = total_paid
        self.payment_count = payment_count
        self.payments = payments or []
        self.last_payment_date = last_payment_date
    
    @property
    def balance(self) -> Decimal:
        """Calculate outstanding balance."""
        return self.total_fees - self.total_paid
    
    @property
    def is_fully_paid(self) -> bool:
        """Check if all fees are paid."""
        return self.balance <= 0
    
    def to_dict(self) -> Dict:
        """Convert to dictionary for API response."""
        return {
            'zoho_student_id': self.zoho_student_id,
            'total_fees': float(self.total_fees),
            'total_paid': float(self.total_paid),
            'balance': float(self.balance),
            'payment_count': self.payment_count,
            'is_fully_paid': self.is_fully_paid,
            'last_payment_date': self.last_payment_date.isoformat() if self.last_payment_date else None,
            'payments': [p.to_dict() for p in self.payments]
        }


class PaymentSyncService:
    """
    Read-only service for accessing payment data from Zoho CRM.
    
    Zoho is the source of truth - Moodle only displays payment information.
    This service provides methods to retrieve and calculate payment data
    for display on the Moodle student dashboard.
    """
    
    def __init__(self, zoho_client):
        """
        Initialize PaymentSyncService.
        
        Args:
            zoho_client: Authenticated ZohoCRMClient instance
        """
        self.zoho = zoho_client
        self.module_name = 'BTEC_Payments'
        logger.info("PaymentSyncService initialized (read-only mode)")
    
    async def get_student_payments(
        self,
        zoho_student_id: str,
        page: int = 1,
        per_page: int = 200
    ) -> List[PaymentData]:
        """
        Get all payments for a specific student.
        
        Args:
            zoho_student_id: Zoho student record ID
            page: Page number for pagination
            per_page: Number of records per page (max 200)
            
        Returns:
            List of PaymentData objects
            
        Raises:
            ValueError: If student_id is invalid
        """
        if not zoho_student_id:
            raise ValueError("Student ID is required")
        
        logger.info(f"Getting payments for student {zoho_student_id}")
        
        try:
            # Search for payments by student
            # Using search instead of get_records for efficiency
            criteria = f"(Student_ID:equals:{zoho_student_id})"
            
            results = await self.zoho.search_records(
                self.module_name,
                criteria,
                page=page,
                per_page=per_page
            )
            
            payments = [PaymentData.from_zoho_dict(p) for p in results]
            
            logger.info(f"Found {len(payments)} payments for student {zoho_student_id}")
            return payments
            
        except Exception as e:
            logger.error(f"Error getting student payments: {e}")
            raise
    
    async def get_payment_by_id(self, zoho_payment_id: str) -> Optional[PaymentData]:
        """
        Get a specific payment record by its Zoho ID.
        
        Args:
            zoho_payment_id: Zoho payment record ID
            
        Returns:
            PaymentData object or None if not found
        """
        if not zoho_payment_id:
            raise ValueError("Payment ID is required")
        
        logger.info(f"Getting payment {zoho_payment_id}")
        
        try:
            payment_data = await self.zoho.get_record(self.module_name, zoho_payment_id)
            
            if payment_data:
                return PaymentData.from_zoho_dict(payment_data)
            
            logger.warning(f"Payment {zoho_payment_id} not found")
            return None
            
        except Exception as e:
            logger.error(f"Error getting payment: {e}")
            raise
    
    async def calculate_payment_summary(
        self,
        zoho_student_id: str,
        total_fees: Optional[Decimal] = None
    ) -> PaymentSummary:
        """
        Calculate payment summary for a student.
        
        This aggregates all payments and calculates totals and balance.
        If total_fees is not provided, it will be fetched from registration.
        
        Args:
            zoho_student_id: Zoho student record ID
            total_fees: Optional total fees (if known), otherwise fetched from registration
            
        Returns:
            PaymentSummary object with totals and balance
        """
        logger.info(f"Calculating payment summary for student {zoho_student_id}")
        
        # Get all payments for student
        payments = await self.get_student_payments(zoho_student_id)
        
        # Calculate total paid
        total_paid = sum(
            (p.payment_amount for p in payments if p.payment_amount),
            Decimal('0')
        )
        
        # Find last payment date
        payment_dates = [p.payment_date for p in payments if p.payment_date]
        last_payment_date = max(payment_dates) if payment_dates else None
        
        # If total_fees not provided, try to get from registration
        if total_fees is None:
            total_fees = await self._get_total_fees_from_registration(zoho_student_id)
        
        summary = PaymentSummary(
            zoho_student_id=zoho_student_id,
            total_fees=total_fees,
            total_paid=total_paid,
            payment_count=len(payments),
            payments=payments,
            last_payment_date=last_payment_date
        )
        
        logger.info(
            f"Payment summary for {zoho_student_id}: "
            f"Fees={total_fees}, Paid={total_paid}, Balance={summary.balance}"
        )
        
        return summary
    
    async def _get_total_fees_from_registration(
        self,
        zoho_student_id: str
    ) -> Decimal:
        """
        Get total fees from student's registration record.
        
        This searches BTEC_Registrations module and sums up the payment schedule.
        
        Args:
            zoho_student_id: Zoho student record ID
            
        Returns:
            Total fees as Decimal, or 0 if not found
        """
        try:
            # Search for active registration
            criteria = f"((Student_ID:equals:{zoho_student_id})AND(Registration_Status:equals:Active))"
            
            registrations = await self.zoho.search_records(
                'BTEC_Registrations',
                criteria,
                page=1,
                per_page=1
            )
            
            if not registrations:
                logger.warning(f"No active registration found for student {zoho_student_id}")
                return Decimal('0')
            
            registration = registrations[0]
            
            # Get payment schedule subform
            payment_schedule = registration.get('Payment_Schedule', [])
            
            if not payment_schedule:
                logger.warning(f"No payment schedule found for student {zoho_student_id}")
                return Decimal('0')
            
            # Sum all installment amounts
            total_fees = sum(
                Decimal(str(installment.get('Installment_Amount', 0)))
                for installment in payment_schedule
                if installment.get('Installment_Amount')
            )
            
            logger.info(f"Total fees from registration: {total_fees}")
            return total_fees
            
        except Exception as e:
            logger.error(f"Error getting total fees from registration: {e}")
            return Decimal('0')
    
    async def search_payments_by_date_range(
        self,
        start_date: date,
        end_date: date,
        zoho_student_id: Optional[str] = None,
        page: int = 1,
        per_page: int = 200
    ) -> List[PaymentData]:
        """
        Search payments within a date range.
        
        Optionally filter by student ID.
        
        Args:
            start_date: Start date (inclusive)
            end_date: End date (inclusive)
            zoho_student_id: Optional student ID to filter
            page: Page number
            per_page: Records per page
            
        Returns:
            List of PaymentData objects
        """
        logger.info(f"Searching payments from {start_date} to {end_date}")
        
        # Build search criteria
        criteria_parts = [
            f"(Payment_Date:greater_equal:{start_date.isoformat()})",
            f"(Payment_Date:less_equal:{end_date.isoformat()})"
        ]
        
        if zoho_student_id:
            criteria_parts.append(f"(Student_ID:equals:{zoho_student_id})")
        
        criteria = "(" + "AND".join(criteria_parts) + ")"
        
        try:
            results = await self.zoho.search_records(
                self.module_name,
                criteria,
                page=page,
                per_page=per_page
            )
            
            payments = [PaymentData.from_zoho_dict(p) for p in results]
            
            logger.info(f"Found {len(payments)} payments in date range")
            return payments
            
        except Exception as e:
            logger.error(f"Error searching payments by date: {e}")
            raise
    
    async def search_payments_by_method(
        self,
        payment_method: str,
        zoho_student_id: Optional[str] = None,
        page: int = 1,
        per_page: int = 200
    ) -> List[PaymentData]:
        """
        Search payments by payment method (Cash, Bank Transfer, Card, etc.).
        
        Args:
            payment_method: Payment method to search for
            zoho_student_id: Optional student ID to filter
            page: Page number
            per_page: Records per page
            
        Returns:
            List of PaymentData objects
        """
        logger.info(f"Searching payments by method: {payment_method}")
        
        # Build search criteria
        criteria_parts = [f"(Payment_Method:equals:{payment_method})"]
        
        if zoho_student_id:
            criteria_parts.append(f"(Student_ID:equals:{zoho_student_id})")
        
        criteria = "(" + "AND".join(criteria_parts) + ")"
        
        try:
            results = await self.zoho.search_records(
                self.module_name,
                criteria,
                page=page,
                per_page=per_page
            )
            
            payments = [PaymentData.from_zoho_dict(p) for p in results]
            
            logger.info(f"Found {len(payments)} payments with method {payment_method}")
            return payments
            
        except Exception as e:
            logger.error(f"Error searching payments by method: {e}")
            raise
    
    async def get_recent_payments(
        self,
        limit: int = 10,
        zoho_student_id: Optional[str] = None
    ) -> List[PaymentData]:
        """
        Get most recent payments (sorted by payment date descending).
        
        Args:
            limit: Maximum number of payments to return
            zoho_student_id: Optional student ID to filter
            
        Returns:
            List of PaymentData objects, sorted by date descending
        """
        logger.info(f"Getting {limit} most recent payments")
        
        # Get payments (with or without student filter)
        if zoho_student_id:
            payments = await self.get_student_payments(
                zoho_student_id,
                page=1,
                per_page=min(limit, 200)
            )
        else:
            # Get all recent payments
            response = await self.zoho.get_records(
                self.module_name,
                page=1,
                per_page=min(limit, 200)
            )
            payments = [
                PaymentData.from_zoho_dict(p)
                for p in response.get('data', [])
            ]
        
        # Sort by payment date descending (most recent first)
        sorted_payments = sorted(
            payments,
            key=lambda p: p.payment_date if p.payment_date else date.min,
            reverse=True
        )
        
        # Return top N
        return sorted_payments[:limit]
    
    async def verify_payment_sync_status(
        self,
        zoho_payment_id: str,
        mark_synced: bool = False
    ) -> bool:
        """
        Check if payment is synced to Moodle.
        
        Optionally mark it as synced (updates Zoho record).
        
        Args:
            zoho_payment_id: Zoho payment record ID
            mark_synced: If True, update Synced_to_Moodle to true
            
        Returns:
            True if synced, False otherwise
        """
        payment = await self.get_payment_by_id(zoho_payment_id)
        
        if not payment:
            logger.warning(f"Payment {zoho_payment_id} not found")
            return False
        
        # If already synced, return True
        if payment.synced_to_moodle:
            return True
        
        # If mark_synced requested, update Zoho
        if mark_synced:
            logger.info(f"Marking payment {zoho_payment_id} as synced to Moodle")
            
            update_data = {
                'Synced_to_Moodle': True
            }
            
            try:
                await self.zoho.update_record(
                    self.module_name,
                    zoho_payment_id,
                    update_data
                )
                logger.info(f"Payment {zoho_payment_id} marked as synced")
                return True
                
            except Exception as e:
                logger.error(f"Error marking payment as synced: {e}")
                raise
        
        return payment.synced_to_moodle
