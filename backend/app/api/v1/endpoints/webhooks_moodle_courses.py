"""
Moodle Course Management Webhook Handlers — Zoho Classes → Moodle Courses

Handles the full class lifecycle (triggered by Edit/Delete only — no Create trigger):

  class_updated — decision tree:
    ┌─ moodle_class_id already set?
    │     YES → UPDATE existing Moodle course fields
    │            + sync all BTEC_Enrollments (enrol/unenrol)
    └─ NO
          └─ status == Active? (transition: inactive → active)
                YES → CREATE new Moodle course
                       + enrol defaults/teacher
                       + write Moodle_Class_ID back to Zoho
                       + sync all BTEC_Enrollments
                NO  → skip Moodle, upsert local_mzi_classes only

  class_deleted — soft-deletes from local_mzi_classes.
                  (Moodle course archiving can be added when needed.)

Routes (all under prefix /webhooks/student-dashboard):
  POST /class_updated
  POST /class_deleted
"""
import json
import logging
from datetime import datetime, timezone, timedelta

from fastapi import APIRouter, HTTPException, Request

from app.api.v1.endpoints.webhooks_shared import (
    call_moodle_ws,
    fetch_zoho_full_record,
    read_zoho_body,
    resolve_zoho_payload,
    transform_zoho_to_moodle,
)
from app.core.config import settings

logger = logging.getLogger(__name__)
router = APIRouter()

_TZ3 = timezone(timedelta(hours=3))   # Zoho / school timezone (GMT+3)


@router.post("/class_updated")
async def handle_class_updated(request: Request):
    """
    Webhook: BTEC_Classes edited in Zoho (Edit trigger only — no Create).

    Decision tree:
      ┌─ moodle_class_id already set?
      │     YES → UPDATE existing course fields in Moodle
      │            + sync all enrollments (enrol/unenrol)
      └─ NO
            └─ status == Active? (class just activated)
                  YES → CREATE new Moodle course
                         + enrol defaults/teacher
                         + write Moodle_Class_ID back to Zoho
                         + sync all enrollments
                  NO  → skip Moodle entirely, upsert local_mzi_classes only
    """
    try:
        raw = await read_zoho_body(request)
        payload = await resolve_zoho_payload(raw, "classes")
        zoho_id = payload.get("id")

        raw_status = payload.get("Class_Status", "")
        class_status = raw_status.get("value", "") if isinstance(raw_status, dict) else str(raw_status)

        logger.info(
            f"📥 class_updated webhook: zoho_id={zoho_id}, "
            f"name={payload.get('Class_Name')}, status={class_status}"
        )

        transformed = transform_zoho_to_moodle(payload, "classes")
        existing_moodle_class_id = str(transformed.get("moodle_class_id") or "").strip()
        is_active = str(transformed.get("class_status", "")).strip().lower() == "active"

        course_action = "none"  # "created" | "updated" | "none"

        # ===========================================================
        # Helper: resolve category_id from BTEC_Program.MoodleID
        # ===========================================================
        async def _get_category():
            default_cat = getattr(settings, "MOODLE_DEFAULT_CATEGORY_ID", 1)
            prog_zoho_id = transformed.get("program_zoho_id")
            if not prog_zoho_id:
                return default_cat
            try:
                prog = await fetch_zoho_full_record("Products", prog_zoho_id)
                mid  = int(prog.get("MoodleID") or 0) if prog else 0
                if mid > 0:
                    logger.info(f"  Category from BTEC_Program ({prog_zoho_id}): {mid}")
                    return mid
            except Exception as pe:
                logger.warning(f"  Could not fetch BTEC_Program {prog_zoho_id}: {pe}")
            logger.warning(f"  BTEC_Program {prog_zoho_id} has no MoodleID → fallback cat {default_cat}")
            return default_cat

        # ===========================================================
        # Helper: convert start_date to epoch (GMT+3)
        # ===========================================================
        def _get_start_epoch():
            raw_start = transformed.get("start_date", "")
            if not raw_start:
                return 0
            try:
                return int(
                    datetime.strptime(str(raw_start)[:10], "%Y-%m-%d")
                    .replace(tzinfo=_TZ3)
                    .timestamp()
                )
            except Exception:
                return 0

        # ===========================================================
        # Helper: enrol default users + teacher into a course
        # ===========================================================
        async def _enrol_defaults(moodle_course_id: str):
            try:
                enrol_list = list(
                    json.loads(getattr(settings, "MOODLE_DEFAULT_COURSE_ENROLMENTS", None) or "[]")
                )
                if str(transformed.get("class_major", "")).strip().upper() == "IT":
                    enrol_list += json.loads(
                        getattr(settings, "MOODLE_COURSE_ENROLMENTS_IT", None) or "[]"
                    )
                teacher_id = transformed.get("teacher_zoho_id")
                if teacher_id:
                    try:
                        t_rec   = await fetch_zoho_full_record("BTEC_Teachers", teacher_id)
                        t_email = (t_rec or {}).get("Academic_Email", "")
                        if t_email:
                            lu = await call_moodle_ws(
                                "core_user_get_users_by_field",
                                {"field": "username", "values[0]": t_email.lower()},
                            )
                            if isinstance(lu, list) and lu:
                                tmid = lu[0].get("id")
                                if tmid:
                                    enrol_list.append({"userid": tmid, "roleid": 3})
                                    logger.info(f"  Teacher {t_email} → Moodle id={tmid}")
                    except Exception as te:
                        logger.warning(f"  ⚠️ Teacher lookup failed (non-fatal): {te}")
                if enrol_list:
                    res = await call_moodle_ws(
                        "local_mzi_enrol_users",
                        {"courseid": moodle_course_id, "enrolmentsdata": json.dumps(enrol_list)},
                    )
                    logger.info(
                        f"  ✅ Enrolled defaults into course {moodle_course_id}: "
                        f"{res.get('message') if isinstance(res, dict) else res}"
                    )
            except Exception as de:
                logger.warning(f"⚠️ Could not enrol default/teacher users: {de}")

        # ===========================================================
        # Helper: sync all BTEC_Enrollments for this class
        # ===========================================================
        async def _sync_enrollments(moodle_course_id: str):
            try:
                from app.infra.zoho.client import ZohoClient
                from app.infra.zoho.auth import ZohoAuthClient
                _auth = ZohoAuthClient(
                    client_id=settings.ZOHO_CLIENT_ID,
                    client_secret=settings.ZOHO_CLIENT_SECRET,
                    refresh_token=settings.ZOHO_REFRESH_TOKEN,
                    region=settings.ZOHO_REGION,
                )
                zoho = ZohoClient(_auth)
                enrollments = await zoho.search_records(
                    "BTEC_Enrollments", f"(Classes:equals:{zoho_id})"
                )
                logger.info(f"  Found {len(enrollments)} enrollment(s) to sync for class {zoho_id}")
                for enr in enrollments:
                    try:
                        enr_t = transform_zoho_to_moodle(enr, "enrollments")
                        enr_t["moodle_course_id"] = moodle_course_id
                        enr_r = await call_moodle_ws(
                            "local_mzi_update_enrollment",
                            {"enrollmentdata": json.dumps(enr_t)},
                        )
                        logger.info(
                            f"  ✅ Enrollment {enr.get('id')}: "
                            f"enrol={enr_r.get('enrol_status','?') if isinstance(enr_r,dict) else '?'}, "
                            f"user={enr_r.get('moodle_user_id','?') if isinstance(enr_r,dict) else '?'}"
                        )
                    except Exception as enr_e:
                        logger.warning(f"  ⚠️ Could not sync enrollment {enr.get('id')}: {enr_e}")
            except Exception as se:
                logger.warning(f"⚠️ Could not fetch/sync enrollments for class {zoho_id}: {se}")

        # ===========================================================
        # BRANCH A: Course already exists in Moodle → UPDATE
        # ===========================================================
        if existing_moodle_class_id:
            logger.info(
                f"🔄 Course {existing_moodle_class_id} already exists "
                f"— updating fields in Moodle"
            )
            try:
                class_name  = transformed.get("class_name", "")
                class_short = transformed.get("class_short_name") or (class_name[:50] if class_name else "")
                update_params = {"courses[0][id]": existing_moodle_class_id}
                if class_name:
                    update_params["courses[0][fullname]"]  = class_name
                    update_params["courses[0][shortname]"] = class_short
                cat = await _get_category()
                update_params["courses[0][categoryid]"] = cat
                se = _get_start_epoch()
                if se:
                    update_params["courses[0][startdate]"] = str(se)

                await call_moodle_ws("core_course_update_courses", update_params)
                logger.info(f"✅ Moodle course {existing_moodle_class_id} updated")
                course_action = "updated"
            except Exception as ue:
                logger.warning(f"⚠️ Could not update Moodle course fields: {ue}")

            # Always sync enrollments on update (handles Enrolled_Students changes)
            await _sync_enrollments(existing_moodle_class_id)

        # ===========================================================
        # BRANCH B: No course yet + Active → CREATE
        # ===========================================================
        elif is_active and transformed.get("class_name"):
            class_name  = transformed["class_name"]
            class_short = transformed.get("class_short_name") or class_name[:50]
            category_id = await _get_category()
            start_epoch = _get_start_epoch()

            try:
                params = {
                    "courses[0][fullname]":    class_name,
                    "courses[0][shortname]":   class_short,
                    "courses[0][categoryid]":  category_id,
                    "courses[0][format]":      "weeks",
                    "courses[0][numsections]": "12",
                }
                if start_epoch:
                    params["courses[0][startdate]"] = str(start_epoch)

                course_result = await call_moodle_ws("core_course_create_courses", params)

                if isinstance(course_result, list) and course_result:
                    moodle_course_id = str(course_result[0].get("id", ""))
                    transformed["moodle_class_id"] = moodle_course_id
                    logger.info(f"✅ Moodle course created: id={moodle_course_id} for '{class_name}'")
                    course_action = "created"

                    await _enrol_defaults(moodle_course_id)

                    # Upsert local_mzi_classes FIRST (FK needed for enrollments)
                    await call_moodle_ws(
                        "local_mzi_create_class",
                        {"classdata": json.dumps(transformed)},
                    )

                    # Write Moodle_Class_ID back to Zoho
                    try:
                        from app.infra.zoho.client import ZohoClient
                        from app.infra.zoho.auth import ZohoAuthClient
                        _a = ZohoAuthClient(
                            client_id=settings.ZOHO_CLIENT_ID,
                            client_secret=settings.ZOHO_CLIENT_SECRET,
                            refresh_token=settings.ZOHO_REFRESH_TOKEN,
                            region=settings.ZOHO_REGION,
                        )
                        await ZohoClient(_a).update_record(
                            module="BTEC_Classes",
                            record_id=zoho_id,
                            data={"Moodle_Class_ID": moodle_course_id},
                        )
                        logger.info(f"✅ Zoho BTEC_Classes {zoho_id} ← Moodle_Class_ID={moodle_course_id}")
                    except Exception as ze:
                        logger.warning(f"⚠️ Could not write Moodle_Class_ID back to Zoho: {ze}")

                    await _sync_enrollments(moodle_course_id)

            except Exception as ce:
                logger.warning(f"⚠️ Moodle course creation failed (non-fatal): {ce}")

        # ===========================================================
        # BRANCH C: No course + not Active → skip Moodle
        # ===========================================================
        else:
            logger.info(
                f"⏭️ Class {zoho_id} status='{transformed.get('class_status')}' "
                f"and no existing Moodle course — skipping Moodle (upsert DB only)"
            )

        # ===========================================================
        # ALWAYS: upsert local_mzi_classes
        # (already done inside Branch B before enrollment sync)
        # ===========================================================
        if course_action != "created":
            class_result = await call_moodle_ws(
                "local_mzi_create_class",
                {"classdata": json.dumps(transformed)},
            )
        else:
            class_result = {"action": "created"}

        return {
            "status": "success",
            "course_action": course_action,
            "moodle_class_id": transformed.get("moodle_class_id"),
            "moodle_response": class_result,
        }

    except Exception as e:
        logger.error(f"❌ class_updated error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/class_deleted")
async def handle_class_deleted(request: Request):
    """
    Webhook: Class cancelled in Zoho.
    Soft-deletes from local_mzi_classes.
    (Add core_course_delete_courses here when Moodle course archiving is needed.)
    """
    try:
        payload = await request.json()
        transformed = transform_zoho_to_moodle(payload, "classes")
        zoho_id = transformed.get("zoho_class_id") or payload.get("id")
        if not zoho_id:
            raise HTTPException(status_code=400, detail="Missing zoho_class_id")
        result = await call_moodle_ws("local_mzi_delete_class", {"zoho_class_id": zoho_id})
        return {"status": "success", "moodle_response": result}
    except Exception as e:
        logger.error(f"❌ class_deleted error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
