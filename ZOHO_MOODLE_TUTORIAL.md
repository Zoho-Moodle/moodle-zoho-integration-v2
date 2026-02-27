# دليل بناء تكامل Zoho ↔ Moodle من الصفر
# Building a Zoho ↔ Moodle Integration from Scratch

> **الهدف / Goal**  
> بناء نظام متكامل يتلقى إشعارات من Zoho CRM عبر Webhooks، يخزنها في قاعدة بيانات Backend، ويعكسها فوراً على Moodle عبر Web Services.  
> Build a complete system that receives Zoho CRM webhook notifications, stores them in a Backend database, and reflects them immediately in Moodle via Web Services.

---

## جدول المحتويات / Table of Contents

| المرحلة / Phase | العنوان |
|-----------------|---------|
| 0 | إعداد بيئة العمل / Environment Setup |
| 1 | بناء الـ Backend (FastAPI) / Building the Backend |
| 2 | قاعدة البيانات (SQLAlchemy + SQLite/PostgreSQL) / Database Design |
| 3 | التكامل مع Zoho / Zoho Integration |
| 4 | الـ Moodle Plugin (PHP + JS) / The Moodle Plugin |
| 5 | المزامنة عبر Webhooks / Webhook-based Sync |
| 6 | إعدادات التكامل والمابينغ / Config & Field Mapping |
| 7 | الاختبار والتوثيق / Testing & Documentation |
| 8 | نشر المشروع في الإنتاج / Production Deployment |

---

## المرحلة 0: إعداد بيئة العمل
## Phase 0: Environment Setup

### ما ستحتاجه / What you need

| أداة / Tool | الإصدار / Version | الاستخدام / Use |
|-------------|-------------------|-----------------|
| Python | 3.11+ | Backend |
| Node.js | 18+ | Processing AMD JS for Moodle |
| PostgreSQL أو SQLite | أي / any | Database |
| ngrok | أي / any | تعريض Backend على الإنترنت (dev) / Expose Backend publicly (dev) |
| Moodle | 4.x | LMS instance |
| Zoho CRM | أي / any | CRM source |

---

### 0.1 هيكل المجلدات / Project Structure

```
my-integration/
├── backend/                   ← FastAPI application
│   ├── app/
│   │   ├── main.py            ← Entry point
│   │   ├── core/
│   │   │   └── config.py      ← Settings / .env loader
│   │   ├── api/
│   │   │   └── v1/
│   │   │       ├── router.py  ← API router (registers all endpoints)
│   │   │       └── endpoints/ ← Individual endpoint files
│   │   ├── infra/
│   │   │   ├── db/
│   │   │   │   ├── base.py    ← SQLAlchemy base + engine
│   │   │   │   ├── session.py ← DB session factory
│   │   │   │   └── models/    ← All ORM models (Student, Registration…)
│   │   │   ├── zoho/
│   │   │   │   └── client.py  ← Zoho API client (OAuth2)
│   │   │   └── moodle/
│   │   │       └── client.py  ← Moodle Web Services client
│   │   └── services/          ← Business logic
│   ├── admin/                 ← Admin UI (Jinja2 HTML pages)
│   ├── .env                   ← Secrets (never commit this!)
│   ├── .env.example           ← Template for secrets
│   ├── requirements.txt
│   └── start_server.py        ← Uvicorn launcher
│
└── moodle_plugin/             ← PHP Moodle plugin
    ├── version.php
    ├── lib.php
    ├── db/
    ├── classes/
    └── amd/
```

---

### 0.2 تثبيت المكتبات / Installing Dependencies

**بالعربية**: أنشئ مجلد المشروع، أنشئ بيئة افتراضية، ثم ثبّت المكتبات:  
**English**: Create the project folder, create a virtual environment, then install dependencies:

```bash
# إنشاء المجلد / Create folder
mkdir my-integration
cd my-integration

# إنشاء بيئة افتراضية / Create virtual environment
python -m venv .venv
.venv\Scripts\activate        # Windows
source .venv/bin/activate     # Linux/Mac

# تثبيت المكتبات / Install libraries
pip install fastapi uvicorn sqlalchemy pydantic-settings \
            httpx python-dotenv alembic psycopg2-binary \
            jinja2 python-multipart aiofiles
```

**`requirements.txt`** (الملف الفعلي من المشروع / Actual file from this project):

```text
fastapi
uvicorn[standard]
sqlalchemy
pydantic-settings
httpx
python-dotenv
jinja2
python-multipart
aiofiles
psycopg2-binary       # للـ PostgreSQL / for PostgreSQL
alembic               # للمايغريشن / for migrations
cryptography          # لتشفير التوكنات / for token encryption
```

---

### 0.3 ملف `.env` (الإعدادات السرية / Secret settings)

**بالعربية**: هذا الملف يحتوي على جميع الإعدادات السرية. **لا تضعه أبداً في Git.**  
**English**: This file contains all secret settings. **Never commit this to Git.**

```bash
# .env.example  ← انسخ هذا إلى .env وأكمل القيم / Copy this to .env and fill values

# قاعدة البيانات / Database
DATABASE_URL=sqlite:///./moodle_zoho_local.db
# للإنتاج استخدم PostgreSQL / For production use PostgreSQL:
# DATABASE_URL=postgresql://user:password@localhost:5432/mzi_db

# Moodle
MOODLE_BASE_URL=https://your-moodle.com
MOODLE_TOKEN=your_moodle_webservice_token
MOODLE_ENABLED=true

# Zoho OAuth2
ZOHO_CLIENT_ID=your_zoho_client_id
ZOHO_CLIENT_SECRET=your_zoho_client_secret
ZOHO_REFRESH_TOKEN=your_zoho_refresh_token
ZOHO_REGION=com

# Webhook
WEBHOOK_BASE_URL=https://your-ngrok-url.ngrok.io   # dev
# WEBHOOK_BASE_URL=https://your-production-url.com  # prod
```

> **ماذا يعني كل إعداد؟ / What does each setting mean?**
> - `DATABASE_URL` — رابط قاعدة البيانات الداخلية للـ Backend (SQLite للتطوير، PostgreSQL للإنتاج)  
> - `MOODLE_TOKEN` — توكن خدمة الويب في Moodle (يُنشأ من: Site Admin → Plugins → Web Services → Manage tokens)  
> - `ZOHO_REFRESH_TOKEN` — توكن التحديث من Zoho OAuth2 (لا تنتهي صلاحيته ما لم تلغِ الصلاحية)  
> - `WEBHOOK_BASE_URL` — الرابط العام للـ Backend الذي سيستقبل الإشعارات من Zoho

---

## المرحلة 1: بناء الـ Backend (FastAPI)
## Phase 1: Building the Backend (FastAPI)

### 1.1 لماذا FastAPI؟ / Why FastAPI?

| الميزة / Feature | التفصيل |
|------------------|---------|
| **السرعة / Speed** | يعتمد على Starlette وهو أسرع framework Python |
| **التوثيق التلقائي / Auto docs** | يولّد Swagger UI تلقائياً على `/docs` |
| **التحقق من البيانات / Validation** | يستخدم Pydantic لتحقق تلقائي من أنواع البيانات |
| **Async** | يدعم `async/await` لمعالجة ملايين الطلبات |
| **Dependency Injection** | نظام `Depends()` لإدارة الـ DB sessions وغيرها |

---

### 1.2 تطبيق FastAPI الرئيسي / The Main FastAPI App

**`backend/app/main.py`** — الملف الفعلي من المشروع:

```python
# backend/app/main.py
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.api.v1.router import router as api_router
from app.core.config import settings
from admin.router import router as admin_router
from app.infra.db.base import Base, engine
import app.infra.db.models  # noqa — ensures all models are imported before create_all
import logging

logger = logging.getLogger(__name__)


# ────────────────────────────────────────────────────────────
# Lifespan = دورة حياة التطبيق (تشغيل / إيقاف)
# ────────────────────────────────────────────────────────────
@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    يُنفَّذ عند بدء التطبيق: ينشئ جداول قاعدة البيانات إن لم تكن موجودة.
    Runs on startup: creates DB tables if they don't exist.
    """
    Base.metadata.create_all(bind=engine)
    logger.info("✅ Database tables created/verified.")
    yield  # ← التطبيق يعمل الآن / App is now running
    # كود الإيقاف هنا / Shutdown code here (optional)


# ────────────────────────────────────────────────────────────
# إنشاء التطبيق / Create the app
# ────────────────────────────────────────────────────────────
app = FastAPI(
    title=settings.APP_NAME,  # يظهر في Swagger / Shown in Swagger
    lifespan=lifespan
)

# إعداد CORS — يسمح لـ Moodle بالاتصال بالـ API / Allow Moodle to call the API
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],          # في الإنتاج: حدد دومين Moodle / In prod: specify Moodle domain
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# تسجيل الـ Routers / Register Routers
app.include_router(api_router, prefix="/api/v1")  # كل الـ API endpoints
app.include_router(admin_router)                  # لوحة التحكم الإدارية / Admin panel


# ────────────────────────────────────────────────────────────
# Health check — للتحقق أن الخادم يعمل / To verify server is running
# ────────────────────────────────────────────────────────────
@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "service": settings.APP_NAME,
        "version": "1.0.0"
    }
```

> **الشرح / Explanation:**
> - `lifespan` — دالة خاصة تُنفَّذ مرة واحدة عند بدء الخادم. نستخدمها لإنشاء جداول DB تلقائياً بدلاً من تشغيل سكريبت يدوي.
> - `app.include_router(api_router, prefix="/api/v1")` — يسجّل جميع الـ endpoints تحت `/api/v1/...`
> - `CORSMiddleware` — ضروري لأن Moodle (على بورت 80/443) سيتصل بالـ Backend (على بورت 8001)

---

### 1.3 تشغيل الخادم / Starting the Server

**`backend/start_server.py`**:

```python
#!/usr/bin/env python
"""Start the FastAPI server"""
import os
import sys
import uvicorn
import logging

# نحوّل مجلد العمل إلى backend/ لضمان تحميل .env / Set cwd to backend/ to load .env
backend_dir = os.path.dirname(os.path.abspath(__file__))
os.chdir(backend_dir)
sys.path.insert(0, backend_dir)

logging.basicConfig(
    level=logging.INFO,
    format="%(levelname)s:     %(name)s - %(message)s",
)

if __name__ == "__main__":
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8001,
        reload=False,
        log_level="info",
    )
```

```bash
# تشغيل الخادم / Run the server
cd backend
python start_server.py

# ← رسالة النجاح / Success message:
# INFO:     Application startup complete.
# INFO:     Uvicorn running on http://0.0.0.0:8001
```

---

### 1.4 إنشاء Endpoint بسيط / Creating a Simple Endpoint

**مثال: `backend/app/api/v1/endpoints/health.py`**

```python
from fastapi import APIRouter

router = APIRouter()

@router.get("/health")
def health_check():
    """
    فحص بسيط للتأكد أن الخادم يعمل.
    Simple check to confirm the server is running.
    """
    return {"status": "ok", "message": "API is healthy"}
```

**تسجيله في الـ Router / Register in router:**

```python
# backend/app/api/v1/router.py
from fastapi import APIRouter
from app.api.v1.endpoints.health import router as health_router

router = APIRouter()
router.include_router(health_router, tags=["health"])
```

**اختبار / Test:**

```bash
curl http://localhost:8001/api/v1/health
# Response: {"status": "ok", "message": "API is healthy"}
```

---

### 1.5 ما هو `prefix` في الـ Router؟ / What is `prefix` in Router?

```python
# مثال / Example:
router = APIRouter(prefix="/sync")

@router.post("/students")
def sync_students(): ...

# URL النهائي / Final URL:
# POST /api/v1/sync/students
#    ↑           ↑       ↑
#  app prefix  router  endpoint
```

الـ prefix يتراكم: `app.include_router(api_router, prefix="/api/v1")` + `APIRouter(prefix="/sync")` + `@router.post("/students")` = `/api/v1/sync/students`

---

## المرحلة 2: قاعدة البيانات
## Phase 2: Database Design

### 2.1 لماذا SQLAlchemy ORM؟ / Why SQLAlchemy ORM?

**بالعربية**: SQLAlchemy هو ORM (Object-Relational Mapper) يسمح لك بتعريف جداول قاعدة البيانات ككلاسات Python بدلاً من كتابة SQL يدوياً.  
**English**: SQLAlchemy is an ORM that lets you define database tables as Python classes instead of writing raw SQL.

```
Python Class (Student)  ←→  Table "students" in DB
    student.academic_email  ←→  column "academic_email" VARCHAR
    student.moodle_user_id  ←→  column "moodle_user_id" VARCHAR
```

---

### 2.2 الإعداد الأساسي / Base Setup

**`backend/app/infra/db/base.py`**:

```python
from sqlalchemy import create_engine
from sqlalchemy.orm import DeclarativeBase
from app.core.config import settings

# المحرك / Engine — الاتصال بقاعدة البيانات
engine = create_engine(
    settings.DATABASE_URL,
    connect_args={"check_same_thread": False}  # ضروري لـ SQLite فقط / SQLite only
)

# الكلاس الأساسي لجميع الموديلز / Base class for all models
class Base(DeclarativeBase):
    pass
```

**`backend/app/infra/db/session.py`**:

```python
from sqlalchemy.orm import sessionmaker, Session
from app.infra.db.base import engine
from typing import Generator

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

def get_db() -> Generator[Session, None, None]:
    """
    Dependency لاستخدامها مع FastAPI Depends().
    Dependency for use with FastAPI Depends().
    
    تضمن إغلاق الجلسة تلقائياً بعد كل طلب.
    Ensures the session is closed after every request.
    """
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
```

> **لماذا `yield` وليس `return`؟ / Why `yield` not `return`?**  
> لأن FastAPI تحتاج أن تُنفّذ الكود بعد `yield` (أي `db.close()`) بعد انتهاء معالجة الطلب.  
> Because FastAPI needs to execute the code after `yield` (i.e. `db.close()`) after the request finishes.

---

### 2.3 موديل الطالب / Student Model

**`backend/app/infra/db/models/student.py`** — الكود الفعلي:

```python
from sqlalchemy import Column, String, Integer, DateTime, Text
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Student(Base):
    __tablename__ = "students"  # اسم الجدول في قاعدة البيانات / Table name in DB

    # المفتاح الأساسي UUID / Primary key UUID
    id = Column(String, primary_key=True, default=lambda: str(uuid4()), index=True)

    # معلومات المصدر / Source info
    tenant_id = Column(String, default="default", nullable=False)
    source = Column(String, default="zoho", nullable=True)

    # معرفات الطالب / Student identifiers
    zoho_id = Column(String, unique=True, index=True, nullable=True)
    moodle_user_id = Column(String, nullable=True)
    username = Column(String, unique=True, index=True, nullable=True)

    # معلومات الطالب / Student info
    display_name = Column(String, nullable=True)
    academic_email = Column(String, nullable=False)   # ← مطلوب دائماً / Always required
    birth_date = Column(String, nullable=True)
    phone = Column(String, nullable=True)
    status = Column(String, nullable=True)

    # تتبع المزامنة / Sync tracking
    sync_status = Column(String, default="pending", nullable=True)
    last_sync = Column(Integer, nullable=True)       # Unix timestamp
    data_hash = Column(String, nullable=True)        # لتجنب المزامنة المكررة / Avoid redundant syncs
    moodle_userid = Column(Integer, nullable=True, index=True)

    # التواريخ / Timestamps
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)
```

> **ملاحظات مهمة / Important notes:**
> - `index=True` — يُنشئ فهرس على هذا العمود لتسريع الاستعلامات / Creates an index for faster queries
> - `unique=True` — يمنع التكرار / Prevents duplicates
> - `nullable=False` — الحقل مطلوب لا يمكن أن يكون فارغاً / Field is required
> - `default=lambda: str(uuid4())` — ينشئ معرّفاً فريداً تلقائياً / Auto-generates a unique ID
> - `onupdate=datetime.utcnow` — يُحدّث التاريخ تلقائياً عند كل تعديل / Auto-updates timestamp on edit

---

### 2.4 موديل التسجيل / Registration Model

```python
# backend/app/infra/db/models/registration.py
from sqlalchemy import Column, String, DateTime, ForeignKey, Index
from uuid import uuid4
from datetime import datetime
from app.infra.db.base import Base


class Registration(Base):
    __tablename__ = "registrations"

    id = Column(String, primary_key=True, default=lambda: str(uuid4()))

    # مفاتيح خارجية / Foreign keys — تربط التسجيل بالطالب والبرنامج
    student_zoho_id = Column(String, ForeignKey("students.zoho_id"), nullable=False, index=True)
    program_zoho_id = Column(String, ForeignKey("programs.zoho_id"), nullable=False, index=True)
    zoho_id = Column(String, nullable=False, index=True)

    # تفاصيل التسجيل / Registration details
    enrollment_status = Column(String, nullable=False)  # Active, Inactive, Completed
    registration_date = Column(String, nullable=True)
    completion_date = Column(String, nullable=True)

    # تتبع المزامنة
    sync_status = Column(String, default="pending", nullable=True)
    data_hash = Column(String, nullable=True)

    # Timestamps
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)

    # فهارس مركّبة / Composite indexes — للاستعلامات الشائعة
    __table_args__ = (
        Index("ix_reg_tenant_student_program", "tenant_id", "student_zoho_id", "program_zoho_id"),
        Index("ix_reg_tenant_zoho", "tenant_id", "zoho_id"),
    )
```

---

### 2.5 العلاقات بين الجداول / Table Relationships

```
students          1 ──→ N  registrations
students          1 ──→ N  payments
students          1 ──→ N  grades
registrations     1 ──→ N  enrollments       ← تسجيل طالب في course Moodle
programs          1 ──→ N  registrations
programs          1 ──→ N  classes
classes           1 ──→ N  enrollments
btec_units        1 ──→ N  grades
```

---

### 2.6 استخدام الـ DB في Endpoint / Using DB in an Endpoint

```python
from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from app.infra.db.session import get_db
from app.infra.db.models.student import Student

router = APIRouter()

@router.get("/students/{zoho_id}")
def get_student(zoho_id: str, db: Session = Depends(get_db)):
    """
    Depends(get_db) يحقن جلسة DB تلقائياً في كل طلب.
    Depends(get_db) injects a DB session automatically into every request.
    """
    student = db.query(Student).filter(Student.zoho_id == zoho_id).first()
    if not student:
        raise HTTPException(status_code=404, detail="Student not found")
    return {"zoho_id": student.zoho_id, "name": student.display_name}
```

---

## المرحلة 3: التكامل مع Zoho
## Phase 3: Zoho Integration

### 3.1 كيف يعمل Zoho Webhook؟ / How does a Zoho Webhook work?

```
┌─────────────────┐    HTTP POST     ┌──────────────────┐    Moodle WS    ┌──────────┐
│   Zoho CRM      │ ──────────────→  │  FastAPI Backend  │ ─────────────→  │  Moodle  │
│ (Automation/    │  Notification    │  /webhooks/       │   update DB     │  Plugin  │
│  Notification)  │  payload         │  student_updated  │   tables        │  reads   │
└─────────────────┘                  └──────────────────┘                  └──────────┘
```

**خطوات العملية / Process steps:**
1. يحدث حدث في Zoho (إنشاء/تعديل طالب)
2. Zoho يرسل HTTP POST إلى Backend URL
3. Backend يستقبل الـ Payload ويستخرج الـ `zoho_id`
4. Backend يجلب السجل الكامل من Zoho CRM API (باستخدام `zoho_id`)
5. Backend يُحوّل الحقول من Zoho إلى Moodle (Field Mapping)
6. Backend يستدعي Moodle Web Service لتحديث الـ DB
7. Plugin Moodle يقرأ البيانات المحدّثة مباشرة من DB

---

### 3.2 عميل Zoho OAuth2 / Zoho OAuth2 Client

**بالعربية**: Zoho يستخدم OAuth2. التوكن يتجدد تلقائياً كل ساعة.  
**English**: Zoho uses OAuth2. The access token auto-refreshes every hour.

```python
# backend/app/infra/zoho/auth.py
import httpx
from app.core.config import settings


class ZohoAuthClient:
    """يتعامل مع OAuth2 tokens لـ Zoho / Handles Zoho OAuth2 tokens"""
    
    REGION_URLS = {
        "com": "https://accounts.zoho.com",
        "eu":  "https://accounts.zoho.eu",
        "in":  "https://accounts.zoho.in",
    }

    def __init__(self, client_id, client_secret, refresh_token, region="com"):
        self.client_id = client_id
        self.client_secret = client_secret
        self.refresh_token = refresh_token
        self.token_url = f"{self.REGION_URLS[region]}/oauth/v2/token"
        self._access_token = None

    async def get_access_token(self) -> str:
        """
        يجلب access_token جديداً باستخدام refresh_token.
        Fetches a new access_token using the refresh_token.
        """
        async with httpx.AsyncClient() as client:
            resp = await client.post(self.token_url, data={
                "grant_type":    "refresh_token",
                "client_id":     self.client_id,
                "client_secret": self.client_secret,
                "refresh_token": self.refresh_token,
            })
            resp.raise_for_status()
            data = resp.json()
            self._access_token = data["access_token"]
            return self._access_token
```

---

### 3.3 جلب سجل كامل من Zoho / Fetching a Full Record from Zoho

**بالعربية**: عندما يُرسل Zoho الـ Webhook، قد لا يتضمن كل الحقول — فقط الـ `id`. لذلك نجلب السجل الكامل بعدها.  
**English**: When Zoho sends the webhook, it may only include the `id` — not all fields. So we fetch the full record afterward.

```python
# في webhooks_shared.py / from webhooks_shared.py
async def fetch_zoho_full_record(module: str, record_id: str) -> dict:
    """
    يجلب السجل الكامل من Zoho CRM API.
    Fetches the full record from Zoho CRM API.
    
    يُستخدم عندما يحتوي الـ webhook notification فقط على الـ ID.
    Used when webhook notification only contains the ID.
    """
    auth = ZohoAuthClient(
        client_id=settings.ZOHO_CLIENT_ID,
        client_secret=settings.ZOHO_CLIENT_SECRET,
        refresh_token=settings.ZOHO_REFRESH_TOKEN,
        region=settings.ZOHO_REGION,
    )
    token = await auth.get_access_token()
    
    url = f"https://www.zohoapis.com/crm/v2/{module}/{record_id}"
    async with httpx.AsyncClient(timeout=30.0) as client:
        resp = await client.get(
            url,
            headers={"Authorization": f"Zoho-oauthtoken {token}"}
        )
    
    if resp.status_code == 200:
        data = resp.json().get("data", [])
        if data:
            return data[0]  # السجل الأول / First record
    
    return {}
```

---

### 3.4 معالج الـ Webhook / Webhook Handler

**`backend/app/api/v1/endpoints/webhooks_dashboard_sync.py`** — مثال فعلي:

```python
from fastapi import APIRouter, HTTPException, Request
from app.api.v1.endpoints.webhooks_shared import (
    call_moodle_ws,
    resolve_zoho_payload,
    transform_zoho_to_moodle,
    read_zoho_body,
)
import json
import logging

logger = logging.getLogger(__name__)
router = APIRouter()


@router.post("/student_updated")
async def handle_student_updated(request: Request):
    """
    يستقبل إشعار Zoho عند تعديل طالب:
    1. يقرأ الـ body
    2. يجلب السجل الكامل من Zoho API
    3. يُحوّل الحقول (Zoho → Moodle)
    4. يُرسل إلى Moodle Web Service
    
    Receives Zoho notification when a student is updated:
    1. Reads body
    2. Fetches full record from Zoho API
    3. Maps fields (Zoho → Moodle)
    4. Calls Moodle Web Service
    """
    try:
        # الخطوة 1: قراءة الـ body / Step 1: Read the body
        raw = await read_zoho_body(request)
        
        # الخطوة 2: جلب السجل الكامل / Step 2: Fetch full record
        payload = await resolve_zoho_payload(raw, "students")
        
        # الخطوة 3: تحويل الحقول / Step 3: Map fields
        transformed = transform_zoho_to_moodle(payload, "students")
        
        if not transformed.get("zoho_student_id"):
            raise HTTPException(status_code=400, detail="Missing zoho_student_id after transform")
        
        # الخطوة 4: استدعاء Moodle / Step 4: Call Moodle
        result = await call_moodle_ws(
            "local_mzi_update_student",
            {"studentdata": json.dumps(transformed)},
        )
        
        logger.info(f"✅ Student synced: {transformed['zoho_student_id']}")
        return {
            "status": "success",
            "zoho_student_id": transformed["zoho_student_id"],
            "moodle_response": result
        }
    
    except Exception as e:
        logger.error(f"❌ student_updated error: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
```

---

### 3.5 قراءة الـ Webhook Body / Reading the Webhook Body

**بالعربية**: Zoho يمكن أن يرسل الـ payload بصيغة JSON أو Form-encoded. نتعامل مع كلاهما:  
**English**: Zoho can send the payload as JSON or Form-encoded. We handle both:

```python
async def read_zoho_body(request: Request) -> dict:
    """
    يقرأ الـ request body من Zoho — يدعم JSON و form-encoded.
    Reads request body from Zoho — supports both JSON and form-encoded.
    """
    content_type = request.headers.get("content-type", "")
    
    if "application/json" in content_type:
        return await request.json()
    
    if "application/x-www-form-urlencoded" in content_type:
        form = await request.form()
        return dict(form)
    
    # محاولة JSON في الحالات الأخرى / Try JSON for other cases
    body = await request.body()
    try:
        return json.loads(body)
    except json.JSONDecodeError:
        return {}
```

---

## المرحلة 4: الـ Moodle Plugin
## Phase 4: The Moodle Plugin

### 4.1 هيكل الـ Plugin الأساسي / Basic Plugin Structure

```
moodle_plugin/
├── version.php          ← معلومات الـ plugin / Plugin info
├── lib.php              ← Event observers + hook callbacks
├── settings.php         ← إعدادات Admin / Admin settings page
├── db/
│   ├── install.xml      ← هيكل الجداول الجديدة / New tables structure
│   ├── upgrade.php      ← تحديثات DB عند upgrade / DB upgrades
│   └── services.xml     ← تعريف الـ Web Services الجديدة / WS definitions
│       ├── functions.php← قائمة functions الـ WS / WS function list
├── classes/
│   ├── external/        ← PHP classes لكل WS function
│   │   ├── update_student.php
│   │   ├── create_registration.php
│   │   └── ...
│   └── observer.php     ← يستقبل Moodle events → يرسل إلى Zoho
├── lang/
│   └── en/
│       └── local_mzi.php  ← Language strings
└── amd/
    └── src/             ← JavaScript (AMD modules)
        └── student_dashboard.js
```

---

### 4.2 `version.php` — معلومات الـ Plugin

```php
<?php
// version.php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_mzi';   // اسم الـ plugin / Plugin name
$plugin->version = 2026022700;      // YYYYMMDDXX — يجب أن يتزايد عند كل update
$plugin->requires = 2023100900;     // حد أدنى لـ Moodle / Minimum Moodle version
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '3.0.0';
```

---

### 4.3 Web Services — تعريف Function / Defining a WS Function

**بالعربية**: نعرّف الـ Web Services التي سيستدعيها الـ Backend في Moodle:  
**English**: We define the Web Services that the Backend will call in Moodle:

**`db/services.xml`** — يُعرّف الـ service وما يحتويه:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<SERVICES>
    <SERVICE name="MZI Local Functions" shortname="local_mzi_service"
             component="local_mzi" enabled="1">
        <FUNCTIONS>
            <FUNCTION name="local_mzi_update_student" />
            <FUNCTION name="local_mzi_create_registration" />
            <FUNCTION name="local_mzi_sync_installments" />
            <FUNCTION name="local_mzi_record_grade" />
        </FUNCTIONS>
    </SERVICE>
</SERVICES>
```

**`db/functions.php`** — يصف كل function:

```php
<?php
$functions = [
    'local_mzi_update_student' => [
        'classname'   => 'local_mzi\external\update_student',
        'description' => 'Create or update a student record from Zoho',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'local/mzi:syncdata',
    ],
    'local_mzi_create_registration' => [
        'classname'   => 'local_mzi\external\create_registration',
        'description' => 'Create or update a student registration',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
```

---

### 4.4 استدعاء Moodle من Backend / Calling Moodle from Backend

```python
# backend/app/api/v1/endpoints/webhooks_shared.py
async def call_moodle_ws(wsfunction: str, params: dict) -> dict:
    """
    يستدعي Moodle Web Service REST API.
    Calls Moodle Web Service REST API.
    
    كل الـ functions تُستدعى بنفس الطريقة:
    All functions are called the same way:
        POST {MOODLE_BASE_URL}/webservice/rest/server.php
            ?wstoken=TOKEN
            &wsfunction=local_mzi_update_student
            &moodlewsrestformat=json
            &studentdata={"zoho_student_id": "..."}
    """
    if not settings.MOODLE_ENABLED:
        return {"status": "moodle_disabled"}

    url = f"{settings.MOODLE_BASE_URL}/webservice/rest/server.php"
    query_params = {
        "wstoken": settings.MOODLE_TOKEN,
        "wsfunction": wsfunction,
        "moodlewsrestformat": "json",
        **params,
    }

    async with httpx.AsyncClient(timeout=30.0) as client:
        resp = await client.post(url, data=query_params)

    result = resp.json()

    # Moodle يُرجع خطأ كـ {"exception": "...", "message": "..."}
    # Moodle returns errors as {"exception": "...", "message": "..."}
    if isinstance(result, dict) and result.get("exception"):
        raise Exception(f"Moodle WS error: {result.get('message', result)}")

    return result
```

---

### 4.5 عرض البيانات في Moodle (JavaScript/AMD) / Displaying Data in Moodle

```javascript
// moodle_plugin/amd/src/student_dashboard.js
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    
    return {
        init: function(userId) {
            // جلب بيانات الطالب من Plugin API / Fetch student data from Plugin API
            Ajax.call([{
                methodname: 'local_mzi_get_student_dashboard',
                args: { userid: userId },
                done: function(response) {
                    // عرض البيانات في الصفحة / Display data on page
                    document.getElementById('student-name').textContent = response.display_name;
                    document.getElementById('student-status').textContent = response.status;
                    
                    // عرض التسجيلات / Display registrations
                    response.registrations.forEach(function(reg) {
                        // بناء عناصر HTML / Build HTML elements
                    });
                },
                fail: Notification.exception
            }]);
        }
    };
});
```

---

## المرحلة 5: المزامنة عبر Webhooks
## Phase 5: Webhook-based Synchronization

### 5.1 مخطط المزامنة الكامل / Complete Sync Architecture

```
======== ZOHO → MOODLE (Real-time) =========

Zoho Event (create/update)
    │
    ▼
POST /api/v1/webhooks/student-dashboard/student_updated
    │
    ├── read_zoho_body(request)          ← قراءة الـ payload
    │
    ├── resolve_zoho_payload(raw, "students")
    │       └── fetch_zoho_full_record("BTEC_Students", zoho_id)
    │           └── GET https://zohoapis.com/crm/v2/BTEC_Students/{id}
    │
    ├── transform_zoho_to_moodle(payload, "students")
    │       └── FIELD_MAPPINGS["students"]["First_Name"] → "first_name"
    │
    └── call_moodle_ws("local_mzi_update_student", {studentdata: JSON})
            └── POST {MOODLE_URL}/webservice/rest/server.php
                    └── Moodle PHP upserts local_mzi_students table


======== MOODLE → ZOHO (Events) =========

Student submits request in Moodle UI
    │
    ▼
PHP observer_grade_submitted::observe()
    │
    ├── Reads Moodle event data
    │
    └── POST Backend /api/v1/webhooks/moodle/grade_submitted
            └── Backend calls Zoho CRM API to update grade record
```

---

### 5.2 الـ Field Mapping — تحويل الحقول / Field Mapping

**بالعربية**: كل حقل في Zoho له اسم مختلف تماماً في Moodle. الـ FIELD_MAPPINGS هو قاموس يربط الاثنين:  
**English**: Each Zoho field has a completely different name in Moodle. FIELD_MAPPINGS is a dictionary linking the two:

```python
# backend/app/api/v1/endpoints/webhooks_shared.py

FIELD_MAPPINGS = {
    # طلاب / Students
    "students": {
        # اسم الحقل في Zoho CRM → اسم العمود في Moodle DB
        # Zoho CRM field name  → Moodle DB column name
        "id":                  "zoho_student_id",
        "First_Name":          "first_name",
        "Last_Name":           "last_name",
        "Email":               "academic_email",
        "Phone":               "phone",
        "Date_of_Birth":       "birth_date",
        "Student_Status":      "status",
        "Account_Name":        "display_name",
        "Photo":               "profile_picture_url",
    },

    # تسجيلات / Registrations
    "registrations": {
        "id":                  "zoho_registration_id",
        "Student":             "student_zoho_id",       # ← lookup field
        "Program_Name":        "program_name",
        "Enrollment_Status":   "enrollment_status",
        "Registration_Date":   "registration_date",
        "Fees":                "total_fees",
        "Paid_Amount":         "paid_amount",
    },

    # درجات / Grades
    "grades": {
        "id":                  "zoho_grade_id",
        "Student":             "student_zoho_id",
        "BTEC_Unit_Name":      "unit_name",
        "Grade":               "grade_value",
        "Submission_Date":     "submission_date",
        "Grade_Status":        "grade_status",
    },

    # مدفوعات / Payments
    "payments": {
        "id":                  "zoho_payment_id",
        "Student":             "student_zoho_id",
        "Amount":              "amount",
        "Payment_Date":        "payment_date",
        "Payment_Method":      "payment_method",
        "Payment_Status":      "status",
    },
}


def transform_zoho_to_moodle(payload: dict, entity_type: str) -> dict:
    """
    يُحوّل حقول Zoho إلى حقول Moodle باستخدام FIELD_MAPPINGS.
    Converts Zoho fields to Moodle fields using FIELD_MAPPINGS.
    """
    mapping = FIELD_MAPPINGS.get(entity_type, {})
    result = {}

    for zoho_field, moodle_field in mapping.items():
        value = payload.get(zoho_field)

        # معالجة Lookup fields — Zoho يُرجع {"id": "...", "name": "..."}
        # Handle Lookup fields — Zoho returns {"id": "...", "name": "..."}
        if isinstance(value, dict):
            result[moodle_field] = value.get("id") or value.get("name") or ""
        elif value is not None:
            result[moodle_field] = value
        else:
            result[moodle_field] = ""

    return result
```

---

### 5.3 منع التكرار Idempotency / Preventing Duplicate Processing

**بالعربية**: أحياناً Zoho يرسل نفس الإشعار مرتين. نستخدم Idempotency لمنع معالجته مرتين:  
**English**: Sometimes Zoho sends the same notification twice. We use Idempotency to prevent processing it twice:

```python
# backend/app/core/idempotency.py
import hashlib
import time
from typing import Dict, Tuple

# تخزين مؤقت في الذاكرة / In-memory cache
_store: Dict[str, Tuple[str, float]] = {}
TTL = 300  # ثواني / seconds

def is_duplicate(key: str, payload: dict) -> bool:
    """
    يتحقق إذا تمت معالجة هذا الـ payload من قبل.
    Checks if this payload has been processed before.
    """
    payload_hash = hashlib.md5(str(sorted(payload.items())).encode()).hexdigest()
    
    if key in _store:
        stored_hash, stored_time = _store[key]
        if stored_hash == payload_hash and (time.time() - stored_time) < TTL:
            return True  # ← Already processed recently
    
    _store[key] = (payload_hash, time.time())
    return False
```

---

## المرحلة 6: إعدادات التكامل والمابينغ
## Phase 6: Config & Field Mapping Settings

### 6.1 تحميل الإعدادات / Loading Config

```python
# backend/app/core/config.py
from pydantic_settings import BaseSettings, SettingsConfigDict
from typing import Optional
import os

# تحديد مسار .env بشكل نسبي / Resolve .env path relative to this file
_ENV_FILE = os.path.normpath(
    os.path.join(os.path.dirname(__file__), "..", "..", ".env")
)


class Settings(BaseSettings):
    # قاعدة البيانات / Database
    DATABASE_URL: str = "sqlite:///./moodle_zoho_local.db"

    # التطبيق / App
    APP_NAME: str = "Moodle Zoho Integration"
    ENV: str = "development"

    # Moodle
    MOODLE_BASE_URL: Optional[str] = None
    MOODLE_TOKEN: Optional[str] = None
    MOODLE_ENABLED: bool = False

    # Zoho
    ZOHO_CLIENT_ID: Optional[str] = None
    ZOHO_CLIENT_SECRET: Optional[str] = None
    ZOHO_REFRESH_TOKEN: Optional[str] = None
    ZOHO_REGION: str = "com"

    # Webhook
    WEBHOOK_BASE_URL: Optional[str] = None

    # تُحمّل القيم تلقائياً من .env / Values auto-loaded from .env
    model_config = SettingsConfigDict(env_file=_ENV_FILE, extra="ignore")


settings = Settings()  # ← singleton يُستخدم في كل مكان / Singleton used everywhere
```

> **لماذا `pydantic-settings`؟ / Why `pydantic-settings`?**  
> يُحمّل القيم من `.env` تلقائياً، يتحقق من الأنواع، ويُتيح قيماً افتراضية — كل هذا في كلاس Python واحد.  
> Auto-loads values from `.env`, validates types, and provides defaults — all in one Python class.

---

### 6.2 الحصول على Zoho Tokens / Getting Zoho Tokens

1. اذهب إلى [api-console.zoho.com](https://api-console.zoho.com)
2. أنشئ "Server-based Application"
3. أضف صلاحيات (Scopes): `ZohoCRM.modules.ALL`
4. في الطلب الأول: احصل على `code` عبر browser redirect
5. استبدل `code` بـ `refresh_token`:

```bash
# مرة واحدة فقط! / One time only!
curl -X POST https://accounts.zoho.com/oauth/v2/token \
  -d "grant_type=authorization_code" \
  -d "client_id=YOUR_CLIENT_ID" \
  -d "client_secret=YOUR_CLIENT_SECRET" \
  -d "redirect_uri=https://your-redirect.com/callback" \
  -d "code=THE_CODE_FROM_STEP_4"

# Response:
# {"access_token": "...", "refresh_token": "SAVE_THIS!", "expires_in": 3600}
```

---

### 6.3 الحصول على Moodle Token / Getting the Moodle Token

1. Moodle Admin → Site administration → Server → Web services → Manage tokens
2. أنشئ مستخدماً مخصصاً للـ API (مثل: `api_user`)
3. أعطِه Role يحتوي على صلاحية `local/mzi:syncdata`
4. أنشئ توكناً لهذا المستخدم
5. ضعه في `.env` كـ `MOODLE_TOKEN`

---

### 6.4 إعداد Zoho Webhooks (من Admin UI) / Setting up Zoho Webhooks

**بالعربية**: المشروع يحتوي على endpoint يُسجّل الـ webhooks تلقائياً في Zoho:  
**English**: The project has an endpoint that auto-registers webhooks in Zoho:

```bash
# يُسجّل جميع Notifications في Zoho CRM تلقائياً
# Registers all Notifications in Zoho CRM automatically
POST /api/v1/admin/setup-zoho-webhooks

# What it does:
# 1. يحمل WEBHOOK_BASE_URL من .env
# 2. لكل entity: يُنشئ Channel + Notification في Zoho
# 3. يضبط return_affected_field_values=false (نجلب كامل السجل لاحقاً)
```

---

## المرحلة 7: الاختبار والتوثيق
## Phase 7: Testing & Documentation

### 7.1 اختبار الـ Health / Health Test

```bash
# فحص أن الخادم يعمل / Check server is running
curl http://localhost:8001/health
# ✅ {"status": "healthy", "service": "Moodle Zoho Integration"}

# فحص API / Check API
curl http://localhost:8001/api/v1/health
# ✅ {"status": "ok", "message": "API is healthy"}
```

---

### 7.2 اختبار Webhook يدوياً / Manually Testing a Webhook

```bash
# محاكاة Zoho webhook لطالب / Simulate Zoho student webhook
curl -X POST http://localhost:8001/api/v1/webhooks/student-dashboard/student_updated \
  -H "Content-Type: application/json" \
  -d '{
    "zoho_id": "TEST123456789",
    "module": "BTEC_Students"
  }'

# Response إذا نجح / Success response:
# {"status": "success", "zoho_student_id": "TEST123456789", "moodle_response": {...}}

# إذا فشل / On failure:
# {"detail": "Missing zoho_student_id after transform"}
```

---

### 7.3 Swagger UI — التوثيق التلقائي / Auto-Documentation

**بالعربية**: FastAPI يُولّد صفحة توثيق تفاعلية تلقائياً:  
**English**: FastAPI automatically generates an interactive documentation page:

```
http://localhost:8001/docs        ← Swagger UI (تفاعلي / Interactive)
http://localhost:8001/redoc       ← ReDoc (قراءة / Reading)
http://localhost:8001/openapi.json ← OpenAPI JSON Schema
```

---

### 7.4 كتابة Tests / Writing Tests

```python
# backend/tests/test_webhooks.py
import pytest
from fastapi.testclient import TestClient
from app.main import app

client = TestClient(app)


def test_health_check():
    """يختبر أن الخادم يعمل / Tests server is running"""
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["status"] == "healthy"


def test_student_webhook_missing_id():
    """يختبر معالجة البيانات الناقصة / Tests handling of missing data"""
    response = client.post(
        "/api/v1/webhooks/student-dashboard/student_updated",
        json={"module": "BTEC_Students"}  # ← zoho_id مفقود / missing
    )
    # يجب أن يُرجع خطأ / Should return error
    assert response.status_code in [400, 500]


pytest.main(["-v"])
```

---

### 7.5 Logging — التوثيق في الكود / Code Logging

```python
import logging

logger = logging.getLogger(__name__)

# مستويات الـ logging / Logging levels:
logger.debug("تفاصيل / Details — for development only")
logger.info("✅ عملية ناجحة / Successful operation")
logger.warning("⚠️ تحذير / Warning — something unusual")
logger.error("❌ خطأ / Error — something went wrong")
logger.critical("🔴 خطأ حرج / Critical — system cannot continue")

# مثال فعلي من المشروع / Real example from the project:
logger.info(f"✅ Student synced to Moodle DB: {transformed['zoho_student_id']}")
logger.error(f"❌ student_updated error: {e}", exc_info=True)
```

---

## المرحلة 8: نشر المشروع في الإنتاج
## Phase 8: Production Deployment

### 8.1 قائمة التحقق قبل النشر / Pre-deployment Checklist

```
□ MOODLE_ENABLED=true في .env
□ DATABASE_URL يشير إلى PostgreSQL (ليس SQLite)
□ WEBHOOK_BASE_URL هو الدومين العام (ليس ngrok)
□ ZOHO_CLIENT_ID / ZOHO_CLIENT_SECRET / ZOHO_REFRESH_TOKEN موجودة
□ MOODLE_TOKEN توكن فعّال
□ .env غير موجود في Git (.gitignore)
□ Plugin مثبّت في Moodle
□ Web Services مُفعّل في Moodle (Admin → Web Services → Overview)
```

---

### 8.2 تشغيل الخادم مع Nginx / Running with Nginx (Linux)

```bash
# تشغيل FastAPI بـ Gunicorn + Uvicorn workers
gunicorn app.main:app \
    -w 4 \
    -k uvicorn.workers.UvicornWorker \
    --bind 127.0.0.1:8001 \
    --access-logfile /var/log/mzi/access.log \
    --error-logfile /var/log/mzi/error.log \
    --daemon
```

**إعداد Nginx reverse proxy:**

```nginx
# /etc/nginx/sites-available/mzi
server {
    listen 443 ssl;
    server_name api.your-domain.com;

    ssl_certificate     /etc/ssl/certs/your_cert.crt;
    ssl_certificate_key /etc/ssl/private/your_key.key;

    location / {
        proxy_pass         http://127.0.0.1:8001;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }
}
```

---

### 8.3 Systemd Service (Linux) / Running as a System Service

```ini
# /etc/systemd/system/mzi-backend.service
[Unit]
Description=Moodle-Zoho Integration Backend
After=network.target postgresql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/mzi/backend
ExecStart=/var/www/mzi/.venv/bin/python start_server.py
Restart=on-failure
RestartSec=5
Environment="ENV=production"

[Install]
WantedBy=multi-user.target
```

```bash
# تفعيل وتشغيل الـ service / Enable and start the service
sudo systemctl daemon-reload
sudo systemctl enable mzi-backend
sudo systemctl start mzi-backend
sudo systemctl status mzi-backend
```

---

### 8.4 تثبيت الـ Plugin في Moodle / Installing the Plugin in Moodle

```bash
# ضغط المجلد / Compress the folder
cd moodle_plugin
zip -r local_mzi.zip . --exclude "*.md" "*.git*"

# رفعه في Moodle / Upload in Moodle:
# Site Administration → Plugins → Install plugins → Upload ZIP
# أو / or:
cp -r moodle_plugin /var/www/html/moodle/local/mzi
cd /var/www/html/moodle
sudo -u www-data php admin/cli/upgrade.php
```

---

### 8.5 المزامنة الأولى / Initial Sync

بعد تثبيت كل شيء، شغّل المزامنة الأولى لجلب جميع البيانات من Zoho:  
After installing everything, run the initial sync to pull all data from Zoho:

```bash
# عبر Admin UI / Via Admin UI:
# http://your-backend.com/admin/sync → Full Sync

# أو عبر API / or via API:
curl -X POST http://localhost:8001/api/v1/admin/full-sync \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

---

## ملخص المعمارية الكاملة / Full Architecture Summary

```
┌────────────────────────────────────────────────────────────────────┐
│                        ZOHO CRM                                     │
│  BTEC_Students, BTEC_Registrations, BTEC_Payments, BTEC_Grades...  │
└─────────────────────────────┬──────────────────────────────────────┘
                              │ Webhooks (HTTP POST)
                              ▼
┌────────────────────────────────────────────────────────────────────┐
│                    FastAPI BACKEND (Port 8001)                       │
│                                                                      │
│  /api/v1/webhooks/student-dashboard/student_updated                 │
│  /api/v1/webhooks/student-dashboard/registration_created            │
│  /api/v1/webhooks/student-dashboard/grade_submitted                 │
│         │                                                            │
│         ├── read_zoho_body()          ← Parse notification          │
│         ├── resolve_zoho_payload()    ← Fetch full record from Zoho │
│         ├── transform_zoho_to_moodle()← Apply FIELD_MAPPINGS        │
│         └── call_moodle_ws()         ← Update Moodle DB tables      │
│                                                                      │
│  /api/v1/sync/students               ← Bulk sync endpoint           │
│  /admin/                             ← Admin UI (Jinja2)            │
└─────────────────────────────┬──────────────────────────────────────┘
                              │ Moodle Web Services (HTTPS)
                              ▼
┌────────────────────────────────────────────────────────────────────┐
│                       MOODLE (PHP)                                   │
│                                                                      │
│  local_mzi Plugin                                                    │
│  ├── Web Services: local_mzi_update_student,                        │
│  │                 local_mzi_create_registration, ...               │
│  ├── DB Tables: local_mzi_students, local_mzi_registrations, ...   │
│  └── UI Pages:  student_dashboard.php  (reads from DB)              │
└────────────────────────────────────────────────────────────────────┘
```

---

## المصطلحات المهمة / Glossary

| المصطلح | الشرح |
|---------|-------|
| **ORM** | Object-Relational Mapper — تمثيل جداول DB ككلاسات Python |
| **Webhook** | إشعار HTTP تلقائي يُرسله Zoho عند كل تغيير |
| **Web Service** | PHP function في Moodle تُستدعى عبر HTTP REST |
| **Dependency Injection** | `Depends()` في FastAPI — حقن الـ DB session تلقائياً |
| **OAuth2 Refresh Token** | توكن دائم يُستخدم للحصول على access_token جديد |
| **FIELD_MAPPING** | قاموس يربط اسم الحقل في Zoho باسمه في Moodle |
| **Idempotency** | ضمان أن معالجة نفس الطلب مرتين لا تُحدث تأثيراً مزدوجاً |
| **lifespan** | coroutine خاصة تُنفَّذ عند بدء/إيقاف FastAPI |
| **AMD Module** | JavaScript module لـ Moodle (define/require pattern) |
| **CORS** | Cross-Origin Resource Sharing — يسمح لـ Moodle باستدعاء Backend |

---

*هذا الدليل مبني على مشروع حقيقي يعمل في الإنتاج. جميع الأكواد مستخرجة من الكود الفعلي.*  
*This guide is built from a real production project. All code is extracted from the actual codebase.*
