"""
Dashboard Sync Webhook Handlers — Zoho → local_mzi_* (Moodle DB)

All handlers in this module ONLY call local_mzi_* Web Service functions
using MOODLE_TOKEN (built-in service).  They update the student dashboard
tables so the Moodle plugin can read them directly from the DB.

Routes (all under prefix /webhooks/student-dashboard):
  POST /student_updated
  POST /registration_created
  POST /payment_recorded
  POST /grade_submitted
  POST /request_status_changed
  POST /submit_student_request
  POST /student_deleted
  POST /registration_deleted
  POST /payment_deleted
  POST /grade_deleted
  POST /request_deleted
"""
import json
import logging
from datetime import datetime

from fastapi import APIRouter, HTTPException, Request

from app.api.v1.endpoints.webhooks_shared import (
    call_moodle_ws,
    ensure_registration_synced,
    resync_registration_with_installments,
    read_zoho_body,
    resolve_zoho_payload,
    transform_zoho_to_moodle,
)
from app.core.config import settings
import httpx

logger = logging.getLogger(__name__)
router = APIRouter()


# ===========================================================================
# ZOHO → MOODLE DB  (CREATE / UPDATE)
# ===========================================================================

@router.post("/student_updated")
async def handle_student_updated(request: Request):
    """
    Webhook: BTEC_Students created/updated in Zoho.
    Maps Zoho api_names → Moodle columns → calls local_mzi_update_student.
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "students")
        transformed = transform_zoho_to_moodle(payload, "students")

        if not transformed.get("zoho_student_id"):
            raise HTTPException(status_code=400, detail="Missing 'zoho_student_id' after transform")

        result = await call_moodle_ws(
            "local_mzi_update_student",
            {"studentdata": json.dumps(transformed)},
        )

        logger.info(f"✅ Student synced to Moodle DB: {transformed['zoho_student_id']}")
        return {"status": "success", "zoho_student_id": transformed["zoho_student_id"], "moodle_response": result}

    except Exception as e:
        logger.error(f"❌ student_updated error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/registration_created")
async def handle_registration_created(request: Request):
    """
    Webhook: BTEC_Registrations created/updated in Zoho.
    Maps Zoho api_names → Moodle columns → calls local_mzi_create_registration.
    Also syncs Payment_Schedule subform → local_mzi_installments.
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "registrations")
        zoho_reg_id = payload.get("id") or ""
        logger.info(f"📥 registration_created webhook: zoho_id={zoho_reg_id}")

        transformed = transform_zoho_to_moodle(payload, "registrations")
        logger.debug(f"🔄 Transformed registration: {transformed}")

        # ── 1. Upsert the registration row ────────────────────────────────────
        result = await call_moodle_ws(
            "local_mzi_create_registration",
            {"registrationdata": json.dumps(transformed)},
        )

        # ── 2. Sync Payment_Schedule subform → local_mzi_installments ─────────
        # Zoho returns the subform as a list under the key "Payment_Schedule"
        payment_schedule = payload.get("Payment_Schedule") or []
        if payment_schedule:
            installments = []
            for idx, row in enumerate(payment_schedule, start=1):
                installments.append({
                    "installment_number": row.get("Installment_No") or idx,
                    "due_date":           str(row.get("Due_Date")          or ""),
                    "amount":             row.get("Installment_Amount")   or 0,
                    "status":             str(row.get("Installment_Status") or "Pending"),
                    "paid_date":          str(row.get("Paid_Date")          or ""),
                })
            try:
                inst_result = await call_moodle_ws(
                    "local_mzi_sync_installments",
                    {
                        "zoho_registration_id": zoho_reg_id,
                        "installmentsdata":     json.dumps(installments),
                    },
                )
                logger.info(
                    f"✅ Installments synced for {zoho_reg_id}: "
                    f"{inst_result.get('count', len(installments))} rows"
                )
            except Exception as inst_err:
                # Non-fatal: registration sync already succeeded.
                # Most likely cause: plugin not yet upgraded on server
                # (local_mzi_sync_installments not registered in Moodle).
                logger.warning(
                    f"⚠️  Installments sync skipped for {zoho_reg_id} "
                    f"(plugin upgrade required on server): {inst_err}"
                )
        else:
            logger.info(f"ℹ️  No Payment_Schedule subform for {zoho_reg_id} — skipping installments")

        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ registration_created error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/payment_recorded")
async def handle_payment_recorded(request: Request):
    """
    Webhook: BTEC_Payments created in Zoho.
    Maps Zoho api_names → Moodle columns → calls local_mzi_record_payment.
    If the parent registration doesn't exist in Moodle yet, auto-syncs it
    first then retries once.
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "payments")
        logger.info(f"📥 payment_recorded webhook: zoho_id={payload.get('id')}")

        transformed = transform_zoho_to_moodle(payload, "payments")
        # Diagnostic: always log payment_date so we can trace missing dates
        logger.info(
            f"💳 Payment fields — zoho_payment_id={transformed.get('zoho_payment_id')} "
            f"zoho_registration_id={transformed.get('zoho_registration_id')} "
            f"payment_date={transformed.get('payment_date')!r} "
            f"raw_Payment_Date={payload.get('Payment_Date')!r}"
        )

        # Guard: if Zoho full-record fetch failed (e.g. token expired), the payload
        # only contains {"id": "..."}.  zoho_registration_id will be absent, so
        # calling Moodle would fail with a misleading "Registration not found" 500.
        # Return 503 (Service Unavailable) so Zoho can retry later.
        if not transformed.get("zoho_registration_id"):
            zoho_payment_id = payload.get("id") or transformed.get("zoho_payment_id", "?")
            logger.error(
                f"❌ payment_recorded aborted: zoho_registration_id is missing for payment "
                f"{zoho_payment_id}. Zoho API record fetch likely failed (token expired?). "
                f"Returning 503 so Zoho will retry."
            )
            raise HTTPException(
                status_code=503,
                detail=f"Payment {zoho_payment_id}: Zoho record fetch failed — "
                       f"Registration_ID is missing. Check Zoho OAuth token."
            )

        try:
            result = await call_moodle_ws(
                "local_mzi_record_payment",
                {"paymentdata": json.dumps(transformed)},
            )
        except HTTPException as moodle_err:
            detail = str(moodle_err.detail).lower()
            # Auto-sync missing parent registration and retry
            if "registration" in detail and "not found" in detail:
                reg_id = transformed.get("zoho_registration_id")
                if not reg_id:
                    raise
                await ensure_registration_synced(reg_id)
                logger.info(f"🔁 Retrying payment after auto-syncing registration {reg_id}")
                result = await call_moodle_ws(
                    "local_mzi_record_payment",
                    {"paymentdata": json.dumps(transformed)},
                )
            else:
                raise

        logger.info(f"✅ Payment synced: {transformed.get('zoho_payment_id')}")

        # ── Re-sync the parent registration from Zoho ──────────────────────
        # This refreshes: Paid_Amount, Remaining_Amount, and installment
        # statuses (e.g. Pending → Paid) that Zoho updates after a payment.
        reg_id = transformed.get("zoho_registration_id")
        if reg_id:
            try:
                await resync_registration_with_installments(reg_id)
            except Exception as resync_err:
                # Non-fatal — payment is already saved
                logger.warning(
                    f"⚠️  Post-payment registration resync failed for {reg_id}: {resync_err}"
                )

        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ payment_recorded error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/grade_submitted")
async def handle_grade_submitted(request: Request):
    """
    Webhook: BTEC_Grades created/updated in Zoho.
    Maps Zoho api_names → Moodle columns → calls local_mzi_submit_grade.
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "grades")
        logger.info(f"📥 grade_submitted webhook: zoho_id={payload.get('id')}")
        logger.info(f"🔑 Zoho payload keys: {list(payload.keys())}")

        # 🔍 DEBUG: Log LO presence in the fetched Zoho record
        lo_raw = payload.get("Learning_Outcomes_Assessm", "KEY_MISSING")
        logger.info(f"🔍 LO in Zoho payload: type={type(lo_raw).__name__}, len={len(lo_raw) if isinstance(lo_raw, list) else 'N/A'}, value={lo_raw}")

        transformed = transform_zoho_to_moodle(payload, "grades")

        # 🔍 DEBUG: Log LO presence after transform
        lo_transformed = transformed.get("learning_outcomes", "KEY_MISSING")
        logger.info(f"🔍 LO in transformed: type={type(lo_transformed).__name__}, value={str(lo_transformed)[:200]}")

        result = await call_moodle_ws(
            "local_mzi_submit_grade",
            {"gradedata": json.dumps(transformed)},
        )

        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ grade_submitted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/request_status_changed")
async def handle_request_status_changed(request: Request):
    """
    Webhook: BTEC_Student_Requests status changed in Zoho (or new request).
    Maps Zoho api_names → Moodle columns → calls local_mzi_update_request_status.
    For Photo Update Requests that are Approved / Rejected, also calls
    local_mzi_approve_photo so the pending photo is promoted or discarded.
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "requests")
        logger.info(f"📥 request_status_changed webhook: zoho_id={payload.get('id')}")

        transformed = transform_zoho_to_moodle(payload, "requests")
        logger.debug(f"🔄 Transformed request: {transformed}")

        result = await call_moodle_ws(
            "local_mzi_update_request_status",
            {"requestdata": json.dumps(transformed)},
        )

        # ── Photo approval side-effect ────────────────────────────────────────
        request_type   = transformed.get("request_type", "")
        request_status = transformed.get("request_status", "")

        if request_type == "Photo Update Request" and request_status in ("Approved", "Rejected"):
            # Moodle_User_ID is stored on the Zoho request record
            moodle_user_id = payload.get("Moodle_User_ID") or payload.get("Student_Moodle_ID")
            if moodle_user_id:
                approved = (request_status == "Approved")
                try:
                    photo_result = await call_moodle_ws(
                        "local_mzi_approve_photo",
                        {
                            "moodle_user_id": int(moodle_user_id),
                            "approved":       approved,
                        },
                    )
                    logger.info(
                        f"📸 photo {'approved' if approved else 'rejected'} "
                        f"for moodle_user_id={moodle_user_id}: {photo_result}"
                    )
                except Exception as pe:
                    logger.error(f"❌ local_mzi_approve_photo failed: {pe}", exc_info=True)
            else:
                logger.warning(
                    "⚠️ Photo Update Request approved/rejected but Moodle_User_ID missing in payload"
                )

        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ request_status_changed error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


# ===========================================================================
# MOODLE → ZOHO: Student submits a request from the Moodle dashboard
# ===========================================================================

@router.post("/submit_student_request")
async def handle_submit_student_request(request: Request):
    """
    Moodle → Middleware → Zoho
    Student submits a new request from the Moodle dashboard.
    Creates a record in Zoho module: BTEC_Student_Requests.
    Also mirrors the request in local_mzi_requests for immediate display.
    """
    try:
        payload = await request.json()
        logger.info(f"📥 submit_student_request: moodle_user_id={payload.get('moodle_user_id')}")

        moodle_user_id  = payload.get("moodle_user_id")
        request_type    = payload.get("request_type") or payload.get("Request_Type")
        reason          = payload.get("reason") or payload.get("notes") or payload.get("description", "")
        zoho_student_id = payload.get("zoho_student_id")

        if not moodle_user_id or not request_type:
            raise HTTPException(status_code=400, detail="Missing required fields: moodle_user_id, request_type")

        zoho_payload = {
            "data": [
                {
                    "Student":        {"id": zoho_student_id} if zoho_student_id else None,
                    "Request_Type":   request_type,
                    "Reason":         reason,
                    "Moodle_User_ID": str(moodle_user_id),
                    "Status":         "Submitted",
                    "Request_Date":   datetime.utcnow().strftime("%Y-%m-%d"),
                }
            ]
        }

        zoho_base  = getattr(settings, "ZOHO_API_BASE_URL", "https://www.zohoapis.com/crm/v2")
        zoho_token = getattr(settings, "ZOHO_ACCESS_TOKEN", "")

        if not zoho_token:
            raise HTTPException(status_code=503, detail="Zoho OAuth token not configured")

        async with httpx.AsyncClient(timeout=30.0) as client:
            resp = await client.post(
                f"{zoho_base}/BTEC_Student_Requests",
                json=zoho_payload,
                headers={"Authorization": f"Zoho-oauthtoken {zoho_token}"},
            )
            resp.raise_for_status()
            zoho_result = resp.json()

        zoho_request_id = None
        if isinstance(zoho_result.get("data"), list) and zoho_result["data"]:
            zoho_request_id = zoho_result["data"][0].get("details", {}).get("id")

        # Mirror into local_mzi_requests immediately (optimistic display)
        if zoho_request_id:
            mirror_data = {
                "zoho_request_id": zoho_request_id,
                "zoho_student_id": zoho_student_id,
                "request_type":    request_type,
                "description":     reason,
                "request_status":  "Submitted",
                "request_date":    datetime.utcnow().strftime("%Y-%m-%d"),
            }
            try:
                await call_moodle_ws(
                    "local_mzi_update_request_status",
                    {"requestdata": json.dumps(mirror_data)},
                )
            except Exception as me:
                logger.warning(f"⚠️ Local mirror failed (non-fatal): {me}")

        logger.info(f"✅ Student request created in Zoho: {zoho_request_id}")
        return {
            "status": "success",
            "zoho_request_id": zoho_request_id,
            "message": "Request submitted to Zoho successfully",
        }

    except Exception as e:
        logger.error(f"❌ submit_student_request error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


# ===========================================================================
# DELETE HANDLERS  (soft-delete local_mzi_* records)
# ===========================================================================

@router.post("/student_deleted")
async def handle_student_deleted(request: Request):
    """Webhook: Student deleted/archived in Zoho."""
    try:
        payload = await request.json()
        transformed = transform_zoho_to_moodle(payload, "students")
        zoho_student_id = transformed.get("zoho_student_id") or payload.get("id")
        if not zoho_student_id:
            raise HTTPException(status_code=400, detail="Missing zoho_student_id")
        result = await call_moodle_ws("local_mzi_delete_student", {"zoho_student_id": zoho_student_id})
        logger.info(f"✅ Student soft-deleted: {zoho_student_id}")
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ student_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/registration_deleted")
async def handle_registration_deleted(request: Request):
    """Webhook: Registration cancelled in Zoho."""
    try:
        payload = await request.json()
        transformed = transform_zoho_to_moodle(payload, "registrations")
        zoho_id = transformed.get("zoho_registration_id") or payload.get("id")
        if not zoho_id:
            raise HTTPException(status_code=400, detail="Missing zoho_registration_id")
        result = await call_moodle_ws("local_mzi_delete_registration", {"zoho_registration_id": zoho_id})
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ registration_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/payment_deleted")
async def handle_payment_deleted(request: Request):
    """Webhook: Payment voided in Zoho."""
    try:
        payload = await request.json()
        transformed = transform_zoho_to_moodle(payload, "payments")
        zoho_id = transformed.get("zoho_payment_id") or payload.get("id")
        if not zoho_id:
            raise HTTPException(status_code=400, detail="Missing zoho_payment_id")
        result = await call_moodle_ws("local_mzi_delete_payment", {"zoho_payment_id": zoho_id})
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ payment_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/grade_deleted")
async def handle_grade_deleted(request: Request):
    """Webhook: Grade deleted in Zoho."""
    try:
        payload = await request.json()
        transformed = transform_zoho_to_moodle(payload, "grades")
        zoho_id = transformed.get("zoho_grade_id") or payload.get("id")
        if not zoho_id:
            raise HTTPException(status_code=400, detail="Missing zoho_grade_id")
        result = await call_moodle_ws("local_mzi_delete_grade", {"zoho_grade_id": zoho_id})
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ grade_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/request_deleted")
async def handle_request_deleted(request: Request):
    """Webhook: Request cancelled in Zoho."""
    try:
        payload = await request.json()
        transformed = transform_zoho_to_moodle(payload, "requests")
        zoho_id = transformed.get("zoho_request_id") or payload.get("id")
        if not zoho_id:
            raise HTTPException(status_code=400, detail="Missing zoho_request_id")
        result = await call_moodle_ws("local_mzi_delete_request", {"zoho_request_id": zoho_id})
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ request_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
