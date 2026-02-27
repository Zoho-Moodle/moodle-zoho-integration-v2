"""
Moodle Enrollment Management Webhook Handlers — Zoho Enrollments → Moodle enrol_manual_*

Enrollment reaches Moodle through TWO paths:

  Case 1 — New BTEC_Enrollment record created/edited in Zoho:
    → Zoho fires MZI - BTEC_Enrollments - create edit rule
    → POST /enrollment_updated (this file)
    → upserts local_mzi_enrollments + calls enrol_manual_enrol_users

  Case 2 — Class activated when students were already enrolled via
             the "Enrolled Students" (Enrolled_Students) Multi-Select Lookup field:
    → Zoho fires MZI - BTEC_Classes - create edit rule
    → POST /class_updated (webhooks_moodle_courses.py)
    → after creating the Moodle course, fetches all BTEC_Enrollments for the class
       and syncs each one (same logic as enrollment_updated below)

Full lifecycle:
  - enrollment_updated: upserts local_mzi_enrollments, then enrolls student in Moodle course.
  - enrollment_deleted: soft-deletes local_mzi_enrollments, then unenrolls student.

All calls use MOODLE_TOKEN (single unified service containing all functions).

Routes (all under prefix /webhooks/student-dashboard):
  POST /enrollment_updated
  POST /enrollment_deleted
"""
import json
import logging

from fastapi import APIRouter, HTTPException, Request

from app.api.v1.endpoints.webhooks_shared import (
    call_moodle_ws,
    read_zoho_body,
    resolve_zoho_payload,
    transform_zoho_to_moodle,
)

logger = logging.getLogger(__name__)
router = APIRouter()


@router.post("/enrollment_updated")
async def handle_enrollment_updated(request: Request):
    """
    Webhook: BTEC_Enrollments created/updated in Zoho.

    local_mzi_update_enrollment handles BOTH:
      1. DB upsert into local_mzi_enrollments
      2. Actual Moodle course enrolment via enrol_get_plugin('manual') internally
         — resolves moodle_user_id via academic_email/student_id fallbacks,
           moodle_course_id via local_mzi_classes — no enrol_manual_enrol_users
           WS permission required.
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "enrollments")
        zoho_id = payload.get("id")
        logger.info(f"📥 enrollment_updated webhook: zoho_id={zoho_id}")

        transformed = transform_zoho_to_moodle(payload, "enrollments")
        logger.debug(f"🔄 Transformed enrollment: {transformed}")

        # Guard: if Zoho full record fetch failed (token issue etc.),
        # we only have the id — abort gracefully instead of crashing.
        if not transformed.get("zoho_student_id") and not transformed.get("student_id"):
            logger.warning(
                f"⚠️ enrollment_updated {zoho_id}: missing student data "
                f"(Zoho full record not available — token issue?). Skipping Moodle call."
            )
            return {
                "status": "skipped",
                "reason": "missing_student_data",
                "zoho_id": zoho_id,
            }

        result = await call_moodle_ws(
            "local_mzi_update_enrollment",
            {"enrollmentdata": json.dumps(transformed)},
        )
        enrol_status = result.get("enrol_status", "?") if isinstance(result, dict) else "?"
        logger.info(
            f"✅ enrollment_updated: db={result.get('action') if isinstance(result, dict) else '?'}, "
            f"enrol={enrol_status}, user={result.get('moodle_user_id') if isinstance(result, dict) else '?'}"
        )

        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ enrollment_updated error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/enrollment_deleted")
async def handle_enrollment_deleted(request: Request):
    """
    Webhook: Enrollment withdrawn/deleted in Zoho.

    Steps:
    1. Soft-delete local_mzi_enrollments via local_mzi_delete_enrollment  (MOODLE_TOKEN)
    2. Unenroll student from Moodle course via enrol_manual_unenrol_users.
       (non-fatal — skipped if Moodle IDs are not available yet)
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "enrollments")
        transformed = transform_zoho_to_moodle(payload, "enrollments")

        zoho_id = transformed.get("zoho_enrollment_id") or payload.get("id")

        if not zoho_id:
            raise HTTPException(status_code=400, detail="Missing zoho_enrollment_id")

        # local_mzi_delete_enrollment marks as Withdrawn in DB AND unenrols the
        # student from the Moodle course via enrol_get_plugin('manual') internally.
        result = await call_moodle_ws(
            "local_mzi_delete_enrollment",
            {"zoho_enrollment_id": zoho_id},
        )
        unenrol_status = result.get("message", "") if isinstance(result, dict) else ""
        logger.info(f"✅ enrollment_deleted: zoho_id={zoho_id}, {unenrol_status}")

        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ enrollment_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
