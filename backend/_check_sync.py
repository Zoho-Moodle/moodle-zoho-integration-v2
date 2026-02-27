from sqlalchemy import create_engine, inspect, text
engine = create_engine('sqlite:///./moodle_zoho_local.db')
inspector = inspect(engine)
for tbl in ['sync_runs', 'integration_events_log']:
    print(f'\n=== {tbl} ===')
    try:
        cols = inspector.get_columns(tbl)
        print('Columns:', [c['name'] for c in cols])
        with engine.connect() as conn:
            rows = conn.execute(text(f'SELECT * FROM {tbl} ORDER BY rowid DESC LIMIT 5')).fetchall()
        print(f'Last {len(rows)} rows:')
        col_names = [c['name'] for c in cols]
        for r in rows:
            print(' ', {k: str(v)[:60] for k, v in zip(col_names, r)})
    except Exception as e:
        print(f'Error: {e}')
exit(0)
# ---- original below ----
engine = create_engine('sqlite:///./moodle_zoho_local.db')
with engine.connect() as conn:
    print('=== SYNC RUNS (last 10) ===')
    try:
        rows = conn.execute(text(
            "SELECT id, service, status, records_processed, records_failed, started_at, completed_at, "
            "ROUND((julianday(COALESCE(completed_at, datetime('now'))) - julianday(started_at)) * 86400) as dur "
            "FROM sync_runs ORDER BY started_at DESC LIMIT 10"
        )).fetchall()
        if not rows:
            print('  (no sync runs found)')
        for r in rows:
            print(f'  [{r[2]:10}] svc={r[1]:22} processed={r[3]} failed={r[4]} dur={r[7]}s  started={str(r[5])[:19]}')
    except Exception as e:
        print(f'  ERROR: {e}')

    print()
    print('=== RECENT EVENTS LOG (last 15) ===')
    try:
        rows2 = conn.execute(text(
            "SELECT event_type, entity_type, entity_id, status, error_message, created_at "
            "FROM integration_events_log ORDER BY created_at DESC LIMIT 15"
        )).fetchall()
        if not rows2:
            print('  (no events found)')
        for r in rows2:
            err = (' ERR: ' + str(r[4])[:80]) if r[4] else ''
            print(f'  [{r[3]:7}] {str(r[0]):25} {str(r[1]):15} id={str(r[2])[:20]}{err}  {str(r[5])[:19]}')
    except Exception as e:
        print(f'  ERROR: {e}')

    print()
    print('=== TABLE ROW COUNTS ===')
    for tbl in ['students','registrations','classes','enrollments','grades','payments','student_requests','field_mappings','sync_runs','integration_events_log']:
        try:
            c = conn.execute(text(f'SELECT COUNT(*) FROM {tbl}')).scalar()
            print(f'  {tbl:30} {c}')
        except Exception as e:
            print(f'  {tbl:30} (missing: {e})')
