"""Quick probe script to find correct Zoho v8 workflow rule payload format."""
import asyncio
import json
import sys

sys.path.insert(0, ".")
from app.services.zoho_workflow_service import ZohoWorkflowService, ZOHO_WORKFLOW_URL
import httpx

NGROK = "https://polyphyletically-unnagged-amare.ngrok-free.app"
WEBHOOK_URL = f"{NGROK}/api/v1/zoho/webhook"
MODULE = "BTEC_Students"

webhook_action = {
    "type": "webhook",
    "name": "MZI Test Webhook",
    "method": "POST",
    "custom_url": WEBHOOK_URL,
    "parameters": {
        "param_type": "3",
        "body": json.dumps({"ids": ["${id}"], "module": MODULE}),
    },
}

_svc = None

async def probe(label: str, rule: dict):
    global _svc
    if _svc is None:
        _svc = ZohoWorkflowService()
    headers = await _svc._headers()
    payload = {"workflow_rules": [rule]}
    async with httpx.AsyncClient() as client:
        r = await client.post(ZOHO_WORKFLOW_URL, headers=headers, json=payload)
        code = r.status_code
        body = r.text[:500]
        print(f"\n[{label}] HTTP {code}")
        print(body)
        return code in (200, 201)

async def main():
    # "webhooks" type accepts non-empty id — test if id-only (by-reference) works
    # and what other fields are required inside the action

    # Probe: minimal action with just type + id (no other webhook fields)
    await probe('A: minimal {type,id} only', {
        "name": "MZI TEST A",
        "module": {"api_name": MODULE},
        "active": True,
        "execute_when": {
            "type": "create_or_edit",
            "details": {"type": "all_records", "repeat": True},
        },
        "conditions": [
            {
                "sequence_number": 1,
                "criteria_pattern": "1",
                "criteria": [
                    {
                        "criteria_group_id": 1,
                        "comparator": "is_not_empty",
                        "field": {"api_name": "id"},
                        "value": "",
                    }
                ],
                "instant_actions": {
                    "actions": [{"type": "webhooks", "id": "999999999999"}],
                },
            }
        ],
    })

    # Also test: what if we skip conditions entirely for create_or_edit?
    await probe('B: no conditions, top-level actions', {
        "name": "MZI TEST B",
        "module": {"api_name": MODULE},
        "active": True,
        "execute_when": {
            "type": "create_or_edit",
            "details": {"type": "all_records", "repeat": False},
        },
        "actions": {"webhooks": [{
            "name": "MZI Test Webhook",
            "method": "POST",
            "custom_url": WEBHOOK_URL,
            "parameters": {
                "param_type": "3",
                "body": json.dumps({"ids": ["${id}"], "module": MODULE}),
            },
        }]},
    })

asyncio.run(main())
