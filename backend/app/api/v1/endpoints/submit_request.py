"""
Submit Request Endpoint

POST /api/v1/requests/submit  — Receives a student request from Moodle and
                                creates a Student_Requests record in Zoho CRM.
"""

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field
from typing import Optional
import logging
import base64
from datetime import datetime

from app.infra.zoho.config import create_zoho_client

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/requests", tags=["requests"])


# ─────────────────────────────────────────────────────────────────────────────
# Request / Response Models
# ─────────────────────────────────────────────────────────────────────────────

class SubmitRequestPayload(BaseModel):
    """Payload sent by Moodle's submit_request.php AJAX handler."""
    zoho_student_id:   Optional[str] = Field(None,  description="Zoho CRM Student record ID")
    student_id_local:  Optional[int] = Field(None,  description="local_mzi_students.id")
    moodle_user_id:    Optional[int] = Field(None,  description="Moodle mdl_user.id")
    moodle_request_id: Optional[int] = Field(None,  description="local_mzi_requests.id")
    request_number:    Optional[str] = Field(None,  description="Human-readable request number")
    request_type:      str           = Field(...,   description="e.g. 'Class Drop'")
    description:       str           = Field(...,   description="Student's description")
    reason:            Optional[str] = Field("",    description="Brief reason category")
    student_name:      Optional[str]  = Field(None,  description="Student full name")
    academic_email:    Optional[str]  = Field(None,  description="Student academic email")
    note:              Optional[str]  = Field(None,  description="Student's free-text note from the form")
    extra:             Optional[dict] = Field(None,  description="Type-specific details (grade_details, requested_classes)")


class SubmitRequestResponse(BaseModel):
    success:          bool
    message:          str
    zoho_request_id:  Optional[str] = None
    error:            Optional[str] = None


# ─────────────────────────────────────────────────────────────────────────────
# Endpoint
# ─────────────────────────────────────────────────────────────────────────────

@router.post("/submit", response_model=SubmitRequestResponse)
async def submit_request(payload: SubmitRequestPayload):
    """
    Creates a Student_Requests record in Zoho CRM.

    Called by Moodle's `ui/ajax/submit_request.php` after saving the request
    locally.  Returns the Zoho record ID so Moodle can store it for tracking.
    """
    if not payload.zoho_student_id:
        logger.warning(
            "submit_request: no zoho_student_id provided "
            "(moodle_user_id=%s, local_id=%s) — skipping Zoho write",
            payload.moodle_user_id, payload.moodle_request_id,
        )
        return SubmitRequestResponse(
            success=False,
            message="No Zoho student ID — request saved locally only",
            error="zoho_student_id is required to create a Zoho record",
        )

    try:
        zoho = create_zoho_client()
    except Exception as exc:
        logger.error("submit_request: failed to create Zoho client: %s", exc)
        raise HTTPException(status_code=503, detail=f"Zoho client unavailable: {exc}")

    logger.warning(
        "submit_request: payload received — student_name=%r, academic_email=%r, "
        "zoho_student_id=%r, request_type=%r, note=%r",
        payload.student_name, payload.academic_email,
        payload.zoho_student_id, payload.request_type, payload.note,
    )

    now_dt = datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%S+00:00")   # Zoho datetime format

    # Build the Zoho record — field API names must match your Student_Requests module
    record_name = payload.request_number or f"{payload.request_type} - {payload.zoho_student_id}"
    zoho_data = {
        "Name":           record_name,
        "Student":        {"id": payload.zoho_student_id},
        "Request_Type":   payload.request_type,
        "Status":         "Submitted",
        "Notes":          payload.description,
        "Reason":         payload.reason or "",
        "Request_Date":   now_dt,
    }

    if payload.student_name and payload.student_name.strip():
        zoho_data["Student_Name"]   = payload.student_name.strip()
    if payload.academic_email and payload.academic_email.strip():
        zoho_data["Academic_Email"] = payload.academic_email.strip()

    if payload.note and payload.note.strip():
        zoho_data["Note"] = payload.note.strip()

    # ── Subforms ──────────────────────────────────────────────────────────
    extra      = payload.extra or {}
    gd         = extra.get("grade_details")   or {}
    rc         = extra.get("requested_classes") or []

    if payload.request_type == "Change Information" and gd.get("change_field"):
        subform_rows = []
        row = {}
        if gd.get("change_field"):
            row["Field_Name"]    = gd["change_field"]
        if gd.get("change_current_value"):
            row["Current_Value"] = gd["change_current_value"]
        if gd.get("change_requested_value"):
            row["New_Value"] = gd["change_requested_value"]
        if row:
            subform_rows.append(row)
        # If name also changed (Nationality case), add a second row
        if gd.get("change_new_name"):
            subform_rows.append({
                "Field_Name": "Name Correction",
                "New_Value":  gd["change_new_name"],
            })
        if subform_rows:
            zoho_data["Change_Information"] = subform_rows

    if payload.request_type == "Class Drop" and rc:
        zoho_data["Requested_Classes"] = [
            {"Moodle_Class_ID": str(class_id)} for class_id in rc
        ]

    # ── Metadata fields ───────────────────────────────────────────────────
    if payload.moodle_request_id:
        zoho_data["Moodle_Request_ID"] = str(payload.moodle_request_id)
    if payload.moodle_user_id:
        zoho_data["Moodle_User_ID"] = str(payload.moodle_user_id)
    if payload.request_number:
        zoho_data["Request_Number"] = payload.request_number

    logger.warning("submit_request: zoho_data to send = %s", zoho_data)

    try:
        result = await zoho.create_record("BTEC_Student_Requests", zoho_data)
    except Exception as exc:
        logger.error(
            "submit_request: Zoho create_record failed for student %s: %s",
            payload.zoho_student_id, exc,
        )
        return SubmitRequestResponse(
            success=False,
            message="Zoho record creation failed",
            error=str(exc),
        )

    zoho_request_id = None
    try:
        # Zoho returns {data: [{details: {id: "..."}, status: "success"}]}
        zoho_request_id = result["details"]["id"]
    except (KeyError, TypeError):
        logger.warning("submit_request: unexpected Zoho response shape: %s", result)

    logger.info(
        "submit_request: created Zoho BTEC_Student_Requests record %s "
        "for student %s (type=%s)",
        zoho_request_id, payload.zoho_student_id, payload.request_type,
    )

    # ── Upload file attachment if provided ────────────────────────────────
    attachment_error = None
    if zoho_request_id:
        extra      = payload.extra or {}
        gd         = extra.get("grade_details") or {}
        file_b64   = gd.get("file_base64")
        file_name  = gd.get("file_name")
        if file_b64 and file_name:
            try:
                file_bytes = base64.b64decode(file_b64)
                logger.info(
                    "submit_request: uploading attachment '%s' (%d bytes) to record %s",
                    file_name, len(file_bytes), zoho_request_id,
                )
                await zoho.upload_attachment(
                    "BTEC_Student_Requests",
                    zoho_request_id,
                    file_bytes,
                    file_name,
                )
                logger.info(
                    "submit_request: attachment '%s' uploaded to record %s",
                    file_name, zoho_request_id,
                )
            except Exception as att_exc:
                # Attachment failure is non-fatal — record already created
                attachment_error = str(att_exc)
                logger.warning(
                    "submit_request: attachment upload failed for record %s: %s",
                    zoho_request_id, att_exc,
                )

    return SubmitRequestResponse(
        success=True,
        message="Request created in Zoho CRM",
        zoho_request_id=zoho_request_id,
        error=attachment_error,
    )
