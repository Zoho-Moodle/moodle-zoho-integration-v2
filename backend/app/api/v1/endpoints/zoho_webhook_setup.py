"""
Admin endpoints for managing Zoho CRM Workflow Rules.

Workflow Rules are permanent (never expire) and work with Custom Modules
(BTEC_*). They replace the old Notification Channels approach.

Routes:
  POST   /api/v1/admin/setup-zoho-automations   Delete old + create 14 Workflow Rules
  GET    /api/v1/admin/zoho-automations          List current rules (state + live Zoho)
  DELETE /api/v1/admin/zoho-automations          Delete all MZI-managed Workflow Rules
"""

import logging
from typing import Optional

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from app.core.config import settings
from app.services.zoho_workflow_service import ZohoWorkflowService

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/admin", tags=["admin"])



# ---------------------------------------------------------------------------
# Request model
# ---------------------------------------------------------------------------


class WorkflowSetupRequest(BaseModel):
    """
    webhook_base_url: The public base URL of this backend server.

    Examples:
      - "https://xxxx-xx-xx.ngrok-free.app"   (ngrok tunnel)
      - "https://api.yourdomain.com"           (production)

    The service appends /api/v1/webhooks/student-dashboard/<endpoint> for each
    Zoho module. If omitted, falls back to WEBHOOK_BASE_URL in .env.
    """

    webhook_base_url: Optional[str] = None

    def resolved_url(self) -> str:
        """Return webhook_base_url from request body or fall back to .env setting."""
        url = self.webhook_base_url or settings.WEBHOOK_BASE_URL
        if not url:
            raise ValueError(
                "webhook_base_url is required: provide it in the request body "
                "or set WEBHOOK_BASE_URL in .env"
            )
        return url.rstrip("/")


# ---------------------------------------------------------------------------
# POST /admin/setup-zoho-automations
# ---------------------------------------------------------------------------


@router.post("/setup-zoho-automations")
async def setup_zoho_automations(request: Optional[WorkflowSetupRequest] = None):
    """
    Create 14 Workflow Rules in Zoho CRM (one pair per BTEC module: create/edit + delete).
    Any existing MZI rules are deleted first — idempotent.

    Requires Zoho OAuth scope: ZohoCRM.settings.workflow_rules.ALL
    """
    if request is None:
        request = WorkflowSetupRequest()
    try:
        svc = ZohoWorkflowService()
        result = await svc.setup_all_rules(webhook_base_url=request.resolved_url())

        if not result.get("success"):
            logger.warning(
                f"Partial creation: {result['created']}/{result['total']} rules created, "
                f"errors: {result.get('errors', [])}"
            )

        return {
            "status": "ok" if result.get("success") else "partial",
            "message": (
                f"Successfully created {result['created']}/{result['total']} Workflow Rules."
                if result.get("success")
                else f"Partial setup: {result['created']}/{result['total']} rules created. "
                     f"Check logs for details."
            ),
            "webhook_base_url":  result["webhook_base_url"],
            "rules_created":     result["created"],
            "rules_total":       result["total"],
            "rules_deleted_old": result.get("deleted_old", 0),
            "errors":            result.get("errors", []),
            "rules":             result.get("rules", []),
        }

    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except RuntimeError as e:
        raise HTTPException(status_code=502, detail=str(e))
    except Exception as e:
        logger.exception("Unexpected error setting up Zoho automations")
        raise HTTPException(status_code=500, detail=f"Unexpected error: {e}")


# ---------------------------------------------------------------------------
# GET /admin/zoho-automations
# ---------------------------------------------------------------------------


@router.get("/zoho-automations")
async def list_zoho_automations():
    """
    Return the saved state (rule IDs + names) and a live count from Zoho.
    """
    try:
        svc = ZohoWorkflowService()
        return await svc.list_rules()
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        logger.exception("Error listing Zoho automations")
        raise HTTPException(status_code=500, detail=f"Error fetching rules: {e}")


# ---------------------------------------------------------------------------
# DELETE /admin/zoho-automations
# ---------------------------------------------------------------------------


@router.delete("/zoho-automations")
async def delete_zoho_automations():
    """
    Delete all MZI-managed Workflow Rules from Zoho CRM and clear the local state file.
    Uses saved rule IDs; falls back to searching Zoho by the 'MZI - ' name prefix.
    """
    try:
        svc = ZohoWorkflowService()
        result = await svc.delete_all_rules()
        return {
            "status":  "ok",
            "message": (
                f"Deleted {result['deleted']} Workflow Rule(s)."
                if result["deleted"]
                else result.get("message", "No rules found to delete.")
            ),
            "deleted": result["deleted"],
            "failed":  result.get("failed", 0),
        }
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        logger.exception("Error deleting Zoho automations")
        raise HTTPException(status_code=500, detail=f"Error deleting rules: {e}")
