"""Quick check for classes in database"""
import psycopg2

conn = psycopg2.connect(
    dbname='moodle_zoho_v2',
    user='postgres',
    password='NewStrongPassword',
    host='localhost',
    port=5432
)

cur = conn.cursor()
cur.execute('SELECT COUNT(*) FROM classes')
count = cur.fetchone()[0]
print(f'\n📊 Total classes in database: {count}')

if count > 0:
    cur.execute('SELECT zoho_id, class_name, moodle_class_id FROM classes LIMIT 5')
    rows = cur.fetchall()
    print('\nFirst 5 classes:')
    for r in rows:
        print(f'  {r[1]} (Zoho: {r[0]}, Moodle: {r[2]})')
else:
    print('\n⚠️ No classes found. Need to import classes first.')

cur.close()
conn.close()
