"""
Export Zoho CRM field API names for selected modules.

Usage:
  python tools/export_zoho_api_names.py --modules BTEC_Students,BTEC_Registrations

Output:
  backend/zoho_api_names.json

Note:
  Reads Zoho credentials from backend/.env file (CLIENT_ID, CLIENT_SECRET, REFRESH_TOKEN)
"""

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from pathlib import Path

DEFAULT_MODULES = [
    "BTEC_Students",
    "BTEC_Registrations",
    "BTEC_Enrollments",
    "BTEC_Classes",
    "BTEC_Payments",
    "BTEC_Student_Requests",
    "BTEC",
    "BTEC_Grades",
    "Products",
    "BTEC_Teachers",
]


def fetch_fields(base_url: str, token: str, module: str) -> dict:
    url = f"{base_url}/settings/fields?module={module}"
    req = Request(url, headers={"Authorization": f"Zoho-oauthtoken {token}"})
    with urlopen(req, timeout=30) as resp:
        raw = resp.read().decode("utf-8")
        return json.loads(raw)


def normalize_fields(payload: dict) -> list:
    fields = payload.get("fields") or payload.get("data") or []
    normalized = []
    for f in fields:
        normalized.append(
            {
                "api_name": f.get("api_name"),
                "field_label": f.get("field_label"),
                "data_type": f.get("data_type"),
                "length": f.get("length"),
                "required": f.get("system_mandatory") or f.get("required"),
                "read_only": f.get("read_only"),
                "custom_field": f.get("custom_field"),
                "lookup": f.get("lookup"),
                "pick_list_values": f.get("pick_list_values"),
            }
        )
    return normalized


def load_env_file():
    """Load variables from backend/.env file"""
    env_path = Path(__file__).parent.parent / '.env'
    if not env_path.exists():
        print(f"Error: .env file not found at {env_path}", file=sys.stderr)
        return {}
    
    env_vars = {}
    with open(env_path, 'r') as f:
        for line in f:
            line = line.strip()
            if line and not line.startswith('#') and '=' in line:
                key, value = line.split('=', 1)
                env_vars[key.strip()] = value.strip()
    return env_vars


def get_access_token(client_id: str, client_secret: str, refresh_token: str, region: str = "com") -> str:
    """Get access token using refresh token"""
    token_url = f"https://accounts.zoho.{region}/oauth/v2/token"
    data = {
        "refresh_token": refresh_token,
        "client_id": client_id,
        "client_secret": client_secret,
        "grant_type": "refresh_token"
    }
    
    req = Request(token_url, data=urlencode(data).encode(), method='POST')
    try:
        with urlopen(req, timeout=30) as resp:
            result = json.loads(resp.read().decode('utf-8'))
            return result.get('access_token')
    except HTTPError as e:
        error_body = e.read().decode('utf-8')
        print(f"Error getting access token: {e.code} {e.reason}", file=sys.stderr)
        print(f"Response: {error_body}", file=sys.stderr)
        raise


def main() -> int:
    parser = argparse.ArgumentParser(description="Export Zoho CRM field API names")
    parser.add_argument(
        "--modules",
        help="Comma-separated module API names",
        default=",".join(DEFAULT_MODULES),
    )
    parser.add_argument(
        "--out",
        help="Output JSON path",
        default=os.path.join(os.path.dirname(__file__), "..", "zoho_api_names.json"),
    )

    args = parser.parse_args()

    # Load environment variables from .env file
    env_vars = load_env_file()
    
    client_id = env_vars.get('ZOHO_CLIENT_ID')
    client_secret = env_vars.get('ZOHO_CLIENT_SECRET')
    refresh_token = env_vars.get('ZOHO_REFRESH_TOKEN')
    region = env_vars.get('ZOHO_REGION', 'com')
    
    if not all([client_id, client_secret, refresh_token]):
        print("Error: Missing Zoho credentials in .env file", file=sys.stderr)
        print("Required: ZOHO_CLIENT_ID, ZOHO_CLIENT_SECRET, ZOHO_REFRESH_TOKEN", file=sys.stderr)
        return 2
    
    # Get access token from refresh token
    print("Getting access token from Zoho...")
    try:
        token = get_access_token(client_id, client_secret, refresh_token, region)
        if not token:
            print("Error: Failed to get access token", file=sys.stderr)
            return 2
        print("✓ Access token obtained successfully")
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return 2

    base_url = f"https://www.zohoapis.{region}/crm/v2"
    modules = [m.strip() for m in args.modules.split(",") if m.strip()]

    result = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "base_url": base_url,
        "modules": {},
        "errors": {},
    }

    print(f"\nFetching fields for {len(modules)} modules...")
    for module in modules:
        try:
            print(f"  - {module}...", end=" ")
            payload = fetch_fields(base_url, token, module)
            result["modules"][module] = normalize_fields(payload)
            print(f"✓ ({len(result['modules'][module])} fields)")
        except HTTPError as e:
            error_msg = f"HTTPError {e.code}: {e.reason}"
            result["errors"][module] = error_msg
            print(f"✗ {error_msg}")
        except URLError as e:
            error_msg = f"URLError: {e.reason}"
            result["errors"][module] = error_msg
            print(f"✗ {error_msg}")
        except Exception as e:
            error_msg = str(e)
            result["errors"][module] = error_msg
            print(f"✗ {error_msg}")

    out_path = os.path.abspath(args.out)
    os.makedirs(os.path.dirname(out_path), exist_ok=True)
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False, indent=2)

    print(f"\n✓ Saved to: {out_path}")
    print(f"✓ Successfully fetched {len(result['modules'])} modules")
    if result["errors"]:
        print(f"\n⚠ Errors for {len(result['errors'])} modules:")
        for module, error in result["errors"].items():
            print(f"  - {module}: {error}")
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
