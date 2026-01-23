"""
Payment Service

Handles sync logic with state machine and fingerprinting.
"""

import hashlib
from typing import Dict, Any, Optional, List
from sqlalchemy.orm import Session
from app.domain.payment import CanonicalPayment
from app.infra.db.models.payment import Payment
from app.infra.db.models.registration import Registration
from app.services.payment_mapper import map_payment_to_db


def compute_fingerprint(payment: CanonicalPayment) -> str:
    """
    Compute fingerprint for change detection.
    Only includes fields that matter for sync decisions.
    """
    parts = [
        payment.registration.id or "",
        str(payment.amount) or "",
        payment.payment_status or "",
        payment.payment_date.isoformat() if payment.payment_date else "",
    ]
    raw = "|".join([p.strip().lower() for p in parts])
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


class PaymentService:
    def __init__(self, db: Session):
        self.db = db

    def sync_payment(self, payment: CanonicalPayment, tenant_id: str) -> Dict[str, Any]:
        """
        Sync a single payment. Returns decision about what happened.
        
        States:
        - NEW: Payment created
        - UNCHANGED: Payment already exists with same data
        - UPDATED: Payment data changed and updated
        - INVALID: Payment data failed validation or dependencies missing
        """
        
        # Validate registration exists
        registration_check = self.db.query(Registration).filter(
            Registration.zoho_id == payment.registration.id,
            Registration.tenant_id == tenant_id
        ).first()
        
        if not registration_check:
            return {
                "zoho_payment_id": payment.zoho_id,
                "status": "INVALID",
                "message": f"Registration {payment.registration.id} not found. Create registration first."
            }

        fp = compute_fingerprint(payment)
        
        # Query for existing payment
        existing: Optional[Payment] = (
            self.db.query(Payment)
            .filter(
                Payment.zoho_id == payment.zoho_id,
                Payment.tenant_id == tenant_id
            )
            .first()
        )

        # ============ NEW PAYMENT ============
        if existing is None:
            db_payment = map_payment_to_db(payment, tenant_id)
            db_payment.fingerprint = fp
            db_payment.sync_status = "synced"
            self.db.add(db_payment)
            self.db.commit()
            
            return {
                "zoho_payment_id": payment.zoho_id,
                "status": "NEW",
                "message": "Payment created"
            }

        # ============ EXISTING PAYMENT ============
        # Check if unchanged
        if existing.fingerprint == fp:
            return {
                "zoho_payment_id": payment.zoho_id,
                "status": "UNCHANGED",
                "message": "No changes detected"
            }

        # ============ UPDATED PAYMENT ============
        changed = {}

        if existing.amount != payment.amount:
            changed["amount"] = (existing.amount, payment.amount)
            existing.amount = payment.amount

        if existing.payment_status != payment.payment_status:
            changed["payment_status"] = (existing.payment_status, payment.payment_status)
            existing.payment_status = payment.payment_status

        pay_date_str = payment.payment_date.isoformat() if payment.payment_date else None
        if existing.payment_date != pay_date_str:
            changed["payment_date"] = (existing.payment_date, pay_date_str)
            existing.payment_date = pay_date_str

        if existing.payment_method != payment.payment_method:
            changed["payment_method"] = (existing.payment_method, payment.payment_method)
            existing.payment_method = payment.payment_method

        existing.fingerprint = fp
        existing.sync_status = "synced"
        self.db.commit()

        return {
            "zoho_payment_id": payment.zoho_id,
            "status": "UPDATED",
            "message": "Payment updated",
            "changed_fields": changed
        }

    def sync_batch(self, payments: List[CanonicalPayment], tenant_id: str) -> Dict[str, Any]:
        """
        Sync multiple payments and return summary.
        """
        results = {
            "total": len(payments),
            "new": 0,
            "unchanged": 0,
            "updated": 0,
            "invalid": 0,
            "records": []
        }

        for payment in payments:
            try:
                result = self.sync_payment(payment, tenant_id)
                results["records"].append(result)
                
                status = result.get("status", "")
                if status == "NEW":
                    results["new"] += 1
                elif status == "UNCHANGED":
                    results["unchanged"] += 1
                elif status == "UPDATED":
                    results["updated"] += 1
                elif status in ("INVALID", "SKIPPED"):
                    results["invalid"] += 1
                    
            except Exception as e:
                results["records"].append({
                    "zoho_payment_id": payment.zoho_id,
                    "status": "ERROR",
                    "message": str(e)
                })
                results["invalid"] += 1

        return results
