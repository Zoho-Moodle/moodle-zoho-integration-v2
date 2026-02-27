# Moodle–Zoho Integration — Deployment Guide

> **Who is this for?**  Anyone setting up this project from scratch on a new server or a new Zoho/Moodle account.

---

## Table of Contents

1. [What is this project?](#1-what-is-this-project)
2. [System Architecture](#2-system-architecture)
3. [Prerequisites](#3-prerequisites)
4. [Project Structure](#4-project-structure)
5. [Backend Setup (Local / Dev)](#5-backend-setup-local--dev)
6. [Backend Setup (Production Server)](#6-backend-setup-production-server)
7. [Files to Upload & Where](#7-files-to-upload--where)
8. [Moodle Plugin Installation](#8-moodle-plugin-installation)
9. [Starting the Backend Server](#9-starting-the-backend-server)
10. [Opening the Setup Wizard](#10-opening-the-setup-wizard)
11. [Setup Wizard Walkthrough (All 7 Steps)](#11-setup-wizard-walkthrough-all-7-steps)
12. [After Setup — Daily Operations](#12-after-setup--daily-operations)
13. [Troubleshooting](#13-troubleshooting)

---

## 1. What is this project?

This project is a **two-way integration bridge** between:

| System | Role |
|--------|------|
| **Zoho CRM** | Source of truth for student data, registrations, classes, grades, payments |
| **Moodle LMS** | Learning Management System — courses, users, enrollments |
| **Backend (FastAPI)** | The bridge: receives Zoho webhooks, syncs data, serves the Student Dashboard |
| **Moodle Plugin** | Displays the Student Dashboard, card, and request forms inside Moodle |

**Data flow:**
```
Zoho CRM ──── webhooks ────► Backend API ──── writes ────► Local SQLite DB
                                  │                              │
                                  ├── creates users ──────────► Moodle WS API
                                  │
                                  └── serves dashboard ────────► Moodle Plugin (PHP)
```

---

## 2. System Architecture

```
moodle-zoho-integration-v3/
├── backend/                 ← FastAPI app (Python)
│   ├── app/                 ← Core application
│   │   ├── api/v1/          ← REST API endpoints (webhooks, sync, student data)
│   │   ├── infra/           ← Database models, Zoho client, Moodle client
│   │   └── services/        ← Business logic
│   ├── admin/               ← Admin dashboard (Jinja2 templates + routes)
│   │   ├── templates/       ← HTML pages (base, dashboard, setup, mappings…)
│   │   └── router.py        ← All /admin/* routes
│   ├── .env                 ← 🔑 All secrets and configuration (never commit!)
│   ├── start_server.py      ← Entry point: starts uvicorn on port 8001
│   └── requirements.txt     ← Python dependencies
│
└── moodle_plugin/           ← Moodle plugin (PHP)
    └── local/moodle_zoho_sync/
        ├── version.php
        ├── lib.php
        └── ui/              ← Student-facing pages
```

---

## 3. Prerequisites

### On the server where the backend runs

| Requirement | Version | Check |
|-------------|---------|-------|
| Python | ≥ 3.9 | `python --version` |
| pip | latest | `pip --version` |
| git | any | `git --version` |
| Network access to Zoho | HTTPS outbound | — |
| Network access to Moodle | HTTPS outbound | — |
| Public URL (HTTPS) | For Zoho webhooks | ngrok (dev) or reverse proxy (prod) |

### Zoho CRM

- A Zoho CRM account with the required custom modules (BTEC_Students, etc.)
- API access: **Client ID**, **Client Secret**, **Refresh Token**
- OAuth scopes:  `ZohoCRM.modules.ALL`, `ZohoCRM.settings.ALL`, `ZohoCRM.bulk.ALL`, `ZohoFiles.files.ALL`

> **How to get a Refresh Token:**
> 1. Go to [api-console.zoho.com](https://api-console.zoho.com)
> 2. Create a **Self Client** app
> 3. Copy Client ID and Client Secret
> 4. In Self Client → **Generate Code** tab, paste the scopes above, set duration to **10 minutes**, click Generate
> 5. Run: `python backend/tools/get_refresh_token.py --code YOUR_CODE`
> 6. Copy the resulting Refresh Token

### Moodle

- Admin access to the Moodle site
- Web Services enabled (Site Administration → Server → Web Services → Overview)
- REST protocol enabled
- An External Service with **all required functions** (see Step 3 of Setup Wizard)
- A Web Service token for that service

---

## 4. Project Structure

### Backend — key files

| File/Folder | Purpose |
|------------|---------|
| `backend/.env` | ⭐ All configuration — edit this before starting |
| `backend/start_server.py` | Start the FastAPI server (port 8001) |
| `backend/requirements.txt` | Python packages |
| `backend/app/main.py` | FastAPI app factory |
| `backend/app/api/v1/endpoints/` | Webhook handlers, sync, student API |
| `backend/app/infra/zoho/` | Zoho CRM API client |
| `backend/app/infra/moodle/` | Moodle WS client |
| `backend/app/infra/db/` | SQLAlchemy models + mapping loader |
| `backend/admin/router.py` | Admin dashboard routes |
| `backend/admin/templates/` | HTML templates for admin panel |
| `backend/moodle_zoho_local.db` | SQLite database (auto-created) |

### Moodle Plugin — key files

| File | Purpose |
|------|---------|
| `version.php` | Plugin version and Moodle compatibility |
| `lib.php` | Plugin hooks |
| `ui/student/student_card.php` | Student ID card page |
| `ui/student/requests.php` | Request submission form |
| `ui/admin2/settings.php` | Admin settings page |
| `ui/admin2/student_manager.php` | Student manager view |

---

## 5. Backend Setup (Local / Dev)

```powershell
# 1. Clone the repo
git clone <repo-url>
cd moodle-zoho-integration-v3

# 2. Create Python virtual environment
python -m venv .venv
.venv\Scripts\Activate.ps1          # Windows PowerShell
# source .venv/bin/activate          # Linux/Mac

# 3. Install dependencies
cd backend
pip install -r requirements.txt

# 4. Copy and edit the .env file
cp .env.example .env
# → Edit .env with your credentials (see section below)

# 5. Start the server
python start_server.py
```

The server will be available at: **http://localhost:8001**

Admin panel: **http://localhost:8001/admin**

---

## 6. Backend Setup (Production Server)

```bash
# SSH into your server
ssh root@YOUR_SERVER_IP

# 1. Install Python 3.9+
apt update && apt install python3.9 python3.9-venv python3-pip -y

# 2. Clone the repo
cd /opt
git clone <repo-url> moodle-zoho-integration
cd moodle-zoho-integration/backend

# 3. Create venv and install packages
python3.9 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

# 4. Create .env
cp .env.example .env
nano .env   # Edit with your credentials

# 5. Run with systemd (recommended for production)
# Create /etc/systemd/system/mzi-backend.service:
```

**systemd service file** (`/etc/systemd/system/mzi-backend.service`):

```ini
[Unit]
Description=Moodle-Zoho Integration Backend
After=network.target

[Service]
User=www-data
WorkingDirectory=/opt/moodle-zoho-integration/backend
ExecStart=/opt/moodle-zoho-integration/backend/.venv/bin/python start_server.py
Restart=always
RestartSec=5
Environment=PYTHONUNBUFFERED=1

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable mzi-backend
systemctl start mzi-backend
systemctl status mzi-backend
```

---

## 7. Files to Upload & Where

### Backend files → Server

Upload the entire `backend/` folder to your server. The minimum required files:

```
backend/
├── app/                    ← All Python application code
├── admin/                  ← Admin dashboard
├── .env                    ← ⚠ Create from .env.example — DO NOT commit
├── start_server.py
├── requirements.txt
└── run_server.py
```

**Via SCP:**
```bash
scp -r backend/ root@YOUR_SERVER:/opt/moodle-zoho-integration/
```

**Via Git (recommended):**
```bash
git pull origin main
```

### Moodle Plugin files → Moodle Server

The plugin lives at: `/path/to/moodle/local/moodle_zoho_sync/`

Upload command pattern:
```bash
scp moodle_plugin/ui/admin2/settings.php \
    root@MOODLE_SERVER:/path/to/moodle/local/moodle_zoho_sync/ui/admin2/settings.php

scp moodle_plugin/ui/student/requests.php \
    root@MOODLE_SERVER:/path/to/moodle/local/moodle_zoho_sync/ui/student/requests.php

scp moodle_plugin/ui/ajax/submit_request.php \
    root@MOODLE_SERVER:/path/to/moodle/local/moodle_zoho_sync/ui/ajax/submit_request.php

scp moodle_plugin/ui/student/student_card.php \
    root@MOODLE_SERVER:/path/to/moodle/local/moodle_zoho_sync/ui/student/student_card.php
```

Or for a full plugin install (first time):
```bash
scp -r moodle_plugin/local/moodle_zoho_sync/ \
    root@MOODLE_SERVER:/path/to/moodle/local/
```

---

## 8. Moodle Plugin Installation

1. Copy the plugin folder to `{moodle_root}/local/moodle_zoho_sync/`
2. Log in to Moodle as Admin
3. Go to **Site Administration → Notifications** — Moodle will detect the new plugin
4. Click **Upgrade Moodle database now**
5. After install, go to **Site Administration → Plugins → Local plugins → Moodle Zoho Sync** to configure

---

## 9. Starting the Backend Server

```powershell
# Windows (dev)
cd backend
& ".venv\Scripts\Activate.ps1"
python start_server.py
```

```bash
# Linux (dev or prod)
cd backend
source .venv/bin/activate
python start_server.py
```

Expected output:
```
INFO:     Started server process
INFO:     Waiting for application startup.
INFO:     Application startup complete.
INFO:     Uvicorn running on http://0.0.0.0:8001 (Press CTRL+C to quit)
```

### Making the backend publicly accessible (for Zoho webhooks)

Zoho webhooks require a public HTTPS URL. In development, use **ngrok**:

```bash
# Download ngrok from https://ngrok.com or use the included ngrok.exe
./ngrok http 8001
```

Copy the `https://xxxx.ngrok-free.app` URL and set it as `WEBHOOK_BASE_URL` in `.env`.

In production, use a reverse proxy (nginx/Apache) with SSL certificate.

---

## 10. Opening the Setup Wizard

After the server is running:

1. Open your browser: **http://localhost:8001/admin**
2. Log in with admin credentials (default: see `admin_users.json`)
3. Click **Setup Wizard** in the sidebar
4. Work through all 7 steps

> The wizard saves your progress automatically. You can stop and resume at any point.

---

## 11. Setup Wizard Walkthrough (All 7 Steps)

### Step 1 — System Check ✅ (Auto-runs)
Verifies: Python version ≥ 3.9, `.env` file exists, all required packages installed.

**If it fails:** Run `pip install -r requirements.txt`

---

### Step 2 — Zoho CRM API
**What it does:** Tests your Zoho OAuth credentials.

**Required `.env` keys:**
```
ZOHO_CLIENT_ID=1000.XXXX
ZOHO_CLIENT_SECRET=XXXX
ZOHO_REFRESH_TOKEN=1000.XXXX
ZOHO_REGION=com          # or eu, in, au
```

**How to get credentials:**
1. Go to [api-console.zoho.com](https://api-console.zoho.com)
2. Create a **Self Client** application
3. Generate a code with scopes: `ZohoCRM.modules.ALL ZohoCRM.settings.ALL ZohoCRM.bulk.ALL ZohoFiles.files.ALL`
4. Run `python tools/get_refresh_token.py --code YOUR_CODE` from the backend folder
5. Paste the token into `.env`

**Common errors:**
- `invalid_code` → Code expired (10-min limit). Generate a new one.
- `invalid_client` → Wrong Client ID or Secret.

---

### Step 3 — Moodle Configuration
**What it does:** Calls `core_webservice_get_site_info` to verify the token and check which WS functions are available.

**Required `.env` keys:**
```
MOODLE_BASE_URL=https://your-moodle.com
MOODLE_TOKEN=your_token_here
MOODLE_DB_URL=mariadb+pymysql://user:pass@host:3306/moodle_db
```

**Required Moodle Web Service functions** (must be added to your External Service):
- `core_webservice_get_site_info`
- `core_user_get_users`
- `core_course_create_courses`
- `core_enrol_get_users_courses`
- `mod_assign_get_grades`
- `gradereport_user_get_grades_table`

**How to add functions in Moodle:**
1. Site Administration → Server → Web Services → External Services
2. Open your service → **Add Functions** → search and add each function above
3. Site Administration → Server → Manage Tokens → copy the token for this service

**Common errors:**
- `Invalid token` → Token is wrong or expired. Create a new one.
- `Access control exception` → Function not added to the External Service (see above).

---

### Step 4 — Database Setup
**What it does:** Checks that all required tables exist in the local SQLite database.

**Required tables:** students, registrations, classes, enrollments, grades, payments, integration_events_log, sync_runs

If any are missing → click **Initialize Database** to create them automatically.

---

### Step 5 — Field Mapping
**What it does:** Links Zoho modules to local DB entities and maps Zoho field names to local column names.

**Workflow:**
1. Click **Discover Zoho Modules** — fetches all available modules from your Zoho account
2. For each data entity (Students, Registrations, etc.):
   - Select the Zoho module from the dropdown (e.g. `BTEC_Students`)
   - Click **Map Fields** — fetches field names from a sample Zoho record
   - Verify/adjust the auto-mapped fields
   - Click **Save This Mapping**
3. Click **Save & Next: Webhooks**

> **Why is this important?** Every Zoho account uses different module names and field names. This step tells the system exactly which Zoho field corresponds to which local database column. The mappings are saved to the `field_mappings` table and used by all webhooks and sync operations.

> **You can always update mappings later** via the **Field Mappings** page in the admin panel (Admin → Field Mappings).

---

### Step 6 — Zoho Webhooks
**What it does:** Registers this backend as a notification channel in Zoho CRM so that when data changes in Zoho, it is automatically sent here.

**Required:** `WEBHOOK_BASE_URL` must be set to a public HTTPS URL reachable from Zoho's servers.

**For development:** Use ngrok (see [Section 9](#9-starting-the-backend-server)).

**For production:** Set to your server's domain, e.g. `https://integration.yourcompany.com`

> If webhooks fail, you can click **Skip** and set them up later via Settings → Webhooks.

---

### Step 7 — Go Live 🚀
**What it does:** Final summary. Optionally runs an initial full sync to pull all existing Zoho data into the local database.

After completing this step, you're ready to use the full system.

---

## 12. After Setup — Daily Operations

### Admin Dashboard
- **http://localhost:8001/admin/dashboard** — Overview, recent events, sync status

### Manual Sync
- **Admin → Sync Control** — Trigger a full sync of any module

### Field Mappings (update anytime)
- **Admin → Field Mappings** — View and edit all Zoho module and field assignments

### Settings
- **Admin → Settings** — Update Zoho credentials, Moodle URL, Webhook URL

### Checking Webhooks
Zoho webhooks arrive at: `POST {WEBHOOK_BASE_URL}/api/v1/webhooks/{module-path}`

Check webhook delivery in Zoho: CRM → Setup → Notifications → Webhooks

---

## 13. Troubleshooting

| Problem | Likely Cause | Fix |
|---------|-------------|-----|
| Server won't start | Missing packages | `pip install -r requirements.txt` |
| 500 error on any page | Python exception | Check terminal output |
| Zoho token expired | Access tokens expire every hour (auto-refreshed) | Check `ZOHO_REFRESH_TOKEN` is correct |
| Webhooks not arriving | WEBHOOK_BASE_URL not public | Use ngrok in dev, or check firewall in prod |
| Moodle sync fails | Wrong token or missing WS functions | Re-run Step 3 of Setup Wizard |
| `field_mappings` empty | Wizard Step 5 not completed | Complete field mapping in Setup Wizard or Field Mappings page |
| DB tables missing | First run, DB not initialized | Click "Initialize Database" in Step 4 |

### Logs

```bash
# Live logs (when running manually)
python start_server.py

# If running as systemd service
journalctl -u mzi-backend -f
```

---

## Environment Variables Reference

| Variable | Required | Description |
|----------|----------|-------------|
| `DATABASE_URL` | ✅ | SQLite: `sqlite:///./moodle_zoho_local.db` |
| `MOODLE_BASE_URL` | ✅ | Full URL of your Moodle site |
| `MOODLE_TOKEN` | ✅ | Moodle Web Service token |
| `MOODLE_DB_URL` | ✅ | Direct DB connection (MariaDB/MySQL) |
| `ZOHO_CLIENT_ID` | ✅ | From api-console.zoho.com |
| `ZOHO_CLIENT_SECRET` | ✅ | From api-console.zoho.com |
| `ZOHO_REFRESH_TOKEN` | ✅ | Generated via OAuth flow |
| `ZOHO_REGION` | ✅ | `com` / `eu` / `in` / `au` |
| `WEBHOOK_BASE_URL` | ✅ | Public HTTPS URL of this backend |
| `ZOHO_WEBHOOK_HMAC_SECRET` | ✅ | Random secret for webhook signature verification |
| `ENABLED_SERVICES` | auto | Set by Setup Wizard |
| `SETUP_COMPLETED_STEPS` | auto | Tracks wizard progress |
| `ZOHO_MODULE_*` | auto | Set by Field Mapping step (e.g. `ZOHO_MODULE_STUDENTS=BTEC_Students`) |

---

*Last updated: February 2026*
