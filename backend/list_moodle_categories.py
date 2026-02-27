import httpx, json, sys
sys.path.insert(0, '.')
from app.core.config import settings

params = {
    'wstoken': settings.MOODLE_TOKEN,
    'wsfunction': 'core_course_get_categories',
    'moodlewsrestformat': 'json',
    'addsubcategories': 1,
}
r = httpx.get(settings.MOODLE_BASE_URL.rstrip('/') + '/webservice/rest/server.php', params=params, timeout=15)
cats = r.json()
if isinstance(cats, list):
    for c in sorted(cats, key=lambda x: x['id']):
        print(f"  id={c['id']:4}  parent={c['parent']:4}  name={c['name']}")
else:
    print(json.dumps(cats, indent=2))
