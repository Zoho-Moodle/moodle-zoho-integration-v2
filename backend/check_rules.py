import httpx, json, asyncio, sys
sys.path.insert(0, '.')
from app.core.config import settings
from app.infra.zoho.auth import ZohoAuthClient

async def main():
    auth = ZohoAuthClient(
        client_id=settings.ZOHO_CLIENT_ID,
        client_secret=settings.ZOHO_CLIENT_SECRET,
        refresh_token=settings.ZOHO_REFRESH_TOKEN,
        region=settings.ZOHO_REGION,
    )
    token = await auth.get_access_token()
    headers = {'Authorization': f'Zoho-oauthtoken {token}'}

    state = json.load(open('zoho_rules_state.json'))
    rules = state.get('rules', [])

    print(f"Checking {len(rules)} rules...\n")
    for rule in rules:
        rid = rule['rule_id']
        # Extract module from name e.g. "MZI - BTEC_Students - create edit"
        parts = rule['name'].split(' - ')
        module = parts[1] if len(parts) > 1 else 'BTEC_Students'
        r = httpx.get(
            f'https://www.zohoapis.com/crm/v8/settings/automation/workflow_rules/{rid}',
            headers=headers,
            timeout=15.0,
        )
        if r.status_code == 200:
            data = r.json().get('workflow_rules', [{}])[0]
            active = data.get('active') or data.get('status', {}).get('active')
            last_exec = data.get('last_executed_time', 'never')
            print(f"  {'✅' if active else '❌'} {rule['name']}: active={active}, last_exec={last_exec}")
        else:
            print(f"  ⚠️  {rule['name']}: HTTP {r.status_code} -> {r.text[:120]}")

asyncio.run(main())
