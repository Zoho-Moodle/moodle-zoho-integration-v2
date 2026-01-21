# Deployment Guide - نليل النشر

## متطلبات النظام

### Hardware
- CPU: 2 cores minimum
- RAM: 2GB minimum
- Storage: 10GB minimum
- Network: Internet connection

### Software
- Python 3.9+
- PostgreSQL 12+
- pip (Python package manager)

---

## خطوات التثبيت والإعداد

### 1. تثبيت المتطلبات

```bash
# كلون المشروع
git clone https://github.com/your-repo/moodle-zoho-integration.git
cd moodle-zoho-integration/backend

# تثبيت Python packages
pip install -r requirements.txt
```

### 2. إعداد قاعدة البيانات

```bash
# التأكد من تشغيل PostgreSQL
# Windows: Net Start PostgreSQL14  (أو version رقمك)
# Linux: sudo systemctl start postgresql
# Mac: brew services start postgresql

# إنشاء قاعدة البيانات
createdb -U admin moodle_zoho

# أو باستخدام psql
psql -U admin
CREATE DATABASE moodle_zoho;
\q
```

### 3. إعداد ملف .env

```bash
# Copy template
cp .env.example .env

# Edit .env with your values
# Windows: notepad .env
# Linux/Mac: nano .env
```

**قيم مثالية:**
```dotenv
DATABASE_URL=postgresql+psycopg2://admin:YourPassword@localhost:5432/moodle_zoho
APP_NAME=Moodle Zoho Integration
ENV=production
LOG_LEVEL=INFO
MOODLE_BASE_URL=http://your-moodle-server.com
MOODLE_TOKEN=your_moodle_api_token
ZOHO_API_KEY=your_zoho_api_key
```

### 4. تهيئة قاعدة البيانات

```bash
# تشغيل script إعداد البيانات
python setup_db.py

# الناتج المتوقع:
# ✅ تم إنشاء الجداول بنجاح
# ✅ جميع الحقول صحيحة
```

### 5. تشغيل Server

**Development (مع auto-reload):**
```bash
python -m uvicorn app.main:app --reload --host 127.0.0.1 --port 8001
```

**Production (بدون auto-reload):**
```bash
python -m uvicorn app.main:app --host 0.0.0.0 --port 8001 --workers 4
```

### 6. التحقق من الحالة

```bash
# اختبار Health endpoint
curl http://127.0.0.1:8001/v1/health

# الناتج المتوقع:
# {"status":"ok","message":"API is healthy"}
```

---

## إعدادات الإنتاج

### استخدام Gunicorn (الأفضل للإنتاج)

```bash
# تثبيت gunicorn
pip install gunicorn

# تشغيل مع 4 workers
gunicorn -w 4 -b 0.0.0.0:8001 app.main:app
```

### استخدام Systemd Service (Linux)

**ملف الـ Service:**
```bash
sudo nano /etc/systemd/system/moodle-zoho.service
```

**المحتوى:**
```ini
[Unit]
Description=Moodle Zoho Integration API
After=network.target

[Service]
Type=notify
User=www-data
WorkingDirectory=/opt/moodle-zoho-integration/backend
Environment="PATH=/opt/moodle-zoho-integration/venv/bin"
ExecStart=/opt/moodle-zoho-integration/venv/bin/gunicorn -w 4 -b 127.0.0.1:8001 app.main:app
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**تفعيل الـ Service:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable moodle-zoho
sudo systemctl start moodle-zoho
sudo systemctl status moodle-zoho
```

### استخدام Nginx كـ Reverse Proxy

**ملف الإعدادات:**
```bash
sudo nano /etc/nginx/sites-available/moodle-zoho
```

**المحتوى:**
```nginx
server {
    listen 80;
    server_name api.moodle-zoho.com;

    location / {
        proxy_pass http://127.0.0.1:8001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

**تفعيل:**
```bash
sudo ln -s /etc/nginx/sites-available/moodle-zoho /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## النسخ الاحتياطية

### النسخ الاحتياطية اليومية

**Script للنسخ الاحتياطي:**
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/moodle_zoho"

mkdir -p $BACKUP_DIR

# نسخ احتياطية لـ PostgreSQL
pg_dump -U admin moodle_zoho > "$BACKUP_DIR/db_backup_$DATE.sql"

# ضغط الملف
gzip "$BACKUP_DIR/db_backup_$DATE.sql"

# حذف النسخ القديمة (أكثر من 30 يوم)
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/db_backup_$DATE.sql.gz"
```

**Cron Job:**
```bash
# تشغيل النسخ الاحتياطية يومياً الساعة 2 صباحاً
0 2 * * * /opt/backup_script.sh
```

---

## المراقبة والـ Logging

### تفعيل Logging

تأكد من أن `LOG_LEVEL=INFO` في `.env`

### ملفات السجلات

```bash
# عرض السجلات الحالية (إذا كانت مشغلة)
tail -f /var/log/moodle-zoho/app.log

# أو من خلال journalctl (إذا استخدمت systemd)
journalctl -u moodle-zoho -f
```

### استكشاف الأخطاء

**خطأ: Connection refused**
```
السبب: PostgreSQL غير مشغل
الحل: systemctl start postgresql
```

**خطأ: Invalid DATABASE_URL**
```
السبب: .env يحتوي على قيمة خاطئة
الحل: تحقق من .env والـ credentials
```

**خطأ: Port already in use**
```
السبب: Port 8001 مستخدم من قبل process آخر
الحل: netstat -tlnp | grep 8001
      kill -9 <PID>
```

---

## الأمان

### Best Practices

1. ✅ استخدم HTTPS في الإنتاج
2. ✅ غيّر كلمات السر الافتراضية
3. ✅ استخدم firewall
4. ✅ قيّد الوصول إلى API
5. ✅ فعّل logging شامل
6. ✅ عمل نسخ احتياطية منتظمة

### SSL/TLS

```bash
# استخدام Let's Encrypt مع Certbot
sudo certbot certonly --nginx -d api.moodle-zoho.com

# تحديث Nginx للـ HTTPS
sudo certbot --nginx -d api.moodle-zoho.com
```

---

## الأداء

### تحسين الـ Query

```bash
# إعادة البناء الدوري للـ indexes
REINDEX DATABASE moodle_zoho;
```

### مراقبة استخدام الموارد

```bash
# استخدام CPU و RAM
top -p $(pgrep -f "uvicorn|gunicorn")

# استخدام Disk
df -h

# استخدام PostgreSQL
SELECT * FROM pg_stat_statements;
```

---

## الترقيات والصيانة

### ترقية Python Packages

```bash
# تحديث جميع الـ packages
pip install --upgrade -r requirements.txt
```

### ترقية قاعدة البيانات

```bash
# تشغيل script الترقية
python setup_db.py

# أو يدويًا
python migrate_db.py
```

---

## الفحص الصحي

**يومي:**
```bash
# اختبر Health endpoint
curl https://api.moodle-zoho.com/v1/health
```

**أسبوعي:**
```bash
# تحقق من حجم قاعدة البيانات
SELECT pg_database.datname, pg_size_pretty(pg_database_size(pg_database.datname))
FROM pg_database;
```

**شهري:**
```bash
# تحقق من الـ logs
grep ERROR /var/log/moodle-zoho/*.log
```

---

## الدعم الفني

للمساعدة:
1. تحقق من الـ logs
2. تأكد من اتصال PostgreSQL
3. تحقق من قيم .env
4. أعد تشغيل Service
