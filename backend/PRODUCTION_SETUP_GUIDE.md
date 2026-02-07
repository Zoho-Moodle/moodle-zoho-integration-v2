# 🚀 Production Deployment - Quick Guide

## Step 1: Configure Zoho CRM Webhooks

### Access Zoho Webhook Settings
1. Login to Zoho CRM: https://crm.zoho.com
2. Go to: **Settings** → **Developer Space** → **Actions** → **Webhooks**
3. Click **Configure Webhook**

### Create Student Webhook
```
Name: BTEC Students Sync
URL: https://YOUR-DOMAIN.com/api/v1/events/zoho/student
Module: BTEC_Students
Method: POST
Events: Create, Update, Delete

Request Body Format:
{
  "notification_id": "${notification_id}",
  "timestamp": "${timestamp}",
  "module": "BTEC_Students",
  "operation": "${operation}",
  "record_id": "${record_id}",
  "data": {
    "Student_ID_Number": "${Student_ID_Number}",
    "Academic_Email": "${Academic_Email}",
    "Name": "${Name}",
    "Phone": "${Phone}",
    "Moodle_User_ID": "${Moodle_User_ID}"
  }
}

Authentication: HMAC
Secret Key: [Generate strong key - save in .env]
Header Name: X-Zoho-Signature
```

### Create Enrollment Webhook
```
Name: BTEC Enrollments Sync
URL: https://YOUR-DOMAIN.com/api/v1/events/zoho/enrollment
Module: BTEC_Enrollments
Method: POST
Events: Create, Update, Delete

Request Body:
{
  "notification_id": "${notification_id}",
  "timestamp": "${timestamp}",
  "module": "BTEC_Enrollments",
  "operation": "${operation}",
  "record_id": "${record_id}",
  "data": {
    "Student": "${Student}",
    "Class": "${Class}",
    "Enrollment_Status": "${Enrollment_Status}",
    "Enrollment_Date": "${Enrollment_Date}"
  }
}
```

### Create Grade Webhook
```
Name: BTEC Grades Sync
URL: https://YOUR-DOMAIN.com/api/v1/events/zoho/grade
Module: BTEC_Grades
Method: POST
Events: Create, Update

Request Body:
{
  "notification_id": "${notification_id}",
  "timestamp": "${timestamp}",
  "module": "BTEC_Grades",
  "operation": "${operation}",
  "record_id": "${record_id}",
  "data": {
    "Enrollment": "${Enrollment}",
    "Assignment_Template": "${Assignment_Template}",
    "Grade": "${Grade}",
    "Submission_Date": "${Submission_Date}"
  }
}
```

### Create Payment Webhook (Optional)
```
Name: BTEC Payments Sync
URL: https://YOUR-DOMAIN.com/api/v1/events/zoho/payment
Module: BTEC_Payments
Method: POST
Events: Create, Update

Request Body:
{
  "notification_id": "${notification_id}",
  "timestamp": "${timestamp}",
  "module": "BTEC_Payments",
  "operation": "${operation}",
  "record_id": "${record_id}",
  "data": {
    "Student": "${Student}",
    "Amount": "${Amount}",
    "Payment_Date": "${Payment_Date}",
    "Payment_Method": "${Payment_Method}"
  }
}
```

---

## Step 2: Update .env File

```bash
# Generate strong HMAC secret
# Use: openssl rand -hex 32

ZOHO_WEBHOOK_HMAC_SECRET=your-generated-secret-from-step-1
MOODLE_WEBHOOK_HMAC_SECRET=another-strong-secret

# Production Database
DATABASE_URL=postgresql+psycopg2://user:pass@prod-host:5432/moodle_zoho_v2

# App Configuration
ENV=production
LOG_LEVEL=INFO
```

---

## Step 3: Deploy Backend

### Option A: Simple Deployment (Single Server)
```bash
# Install dependencies
pip install -r requirements.txt

# Run database migration
python create_event_log_table.py

# Start server with gunicorn
gunicorn app.main:app \
  -w 4 \
  -k uvicorn.workers.UvicornWorker \
  --bind 0.0.0.0:8001 \
  --access-logfile /var/log/zoho-integration/access.log \
  --error-logfile /var/log/zoho-integration/error.log
```

### Option B: Docker Deployment
```dockerfile
# Dockerfile
FROM python:3.11-slim

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

CMD ["gunicorn", "app.main:app", "-w", "4", "-k", "uvicorn.workers.UvicornWorker", "--bind", "0.0.0.0:8001"]
```

```bash
# Build and run
docker build -t zoho-integration .
docker run -d -p 8001:8001 --env-file .env zoho-integration
```

---

## Step 4: Configure Nginx Reverse Proxy

```nginx
# /etc/nginx/sites-available/zoho-integration

server {
    listen 80;
    server_name your-domain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Webhook endpoints
    location /api/v1/events/ {
        proxy_pass http://localhost:8001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Increase timeouts for webhooks
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

### Enable site and get SSL certificate
```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d your-domain.com

# Enable site
sudo ln -s /etc/nginx/sites-available/zoho-integration /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Step 5: Test Webhooks

### Test from command line
```bash
# Test student webhook
curl -X POST https://your-domain.com/api/v1/events/zoho/student \
  -H "Content-Type: application/json" \
  -H "X-Zoho-Signature: sha256=test" \
  -d '{
    "notification_id": "test_123",
    "timestamp": "2026-01-25T10:00:00Z",
    "module": "BTEC_Students",
    "operation": "update",
    "record_id": "5398830000123893227",
    "data": {}
  }'

# Expected response:
# {"success": true, "message": "Event accepted for processing", ...}
```

### Monitor events
```bash
# Health check
curl https://your-domain.com/api/v1/events/health

# Event statistics
curl https://your-domain.com/api/v1/events/stats
```

---

## Step 6: Monitoring & Maintenance

### View Logs
```bash
# Application logs
tail -f /var/log/zoho-integration/error.log

# Nginx access logs
tail -f /var/log/nginx/access.log
```

### Database Monitoring
```sql
-- Recent events
SELECT * FROM integration_events_log 
ORDER BY created_at DESC 
LIMIT 20;

-- Failed events count
SELECT COUNT(*) FROM integration_events_log 
WHERE status = 'failed' 
AND created_at > NOW() - INTERVAL '24 hours';

-- Events by status
SELECT status, COUNT(*) 
FROM integration_events_log 
GROUP BY status;
```

### Set up Cron for Cleanup (Optional)
```bash
# Clean old events (keep 30 days)
0 2 * * * psql -c "DELETE FROM integration_events_log WHERE created_at < NOW() - INTERVAL '30 days';"
```

---

## Troubleshooting

### Issue: Webhook returns 401/403
- Verify HMAC secret matches in Zoho and `.env`
- Check `X-Zoho-Signature` header is present
- Verify webhook URL is HTTPS

### Issue: Events not processing
- Check database connection
- Verify Zoho credentials in `.env`
- Check application logs for errors

### Issue: Slow performance
- Increase worker count in gunicorn
- Check database connection pool
- Monitor database query performance

---

## Security Checklist

- [ ] HTTPS enabled with valid SSL certificate
- [ ] HMAC secrets are strong (32+ characters)
- [ ] Firewall configured (only ports 80, 443 open)
- [ ] Database has strong password
- [ ] .env file not in git repository
- [ ] Application logs don't contain secrets
- [ ] Regular security updates scheduled

---

## Quick Commands Reference

```bash
# Start server
cd backend && python start_server.py

# Start with gunicorn (production)
gunicorn app.main:app -w 4 -k uvicorn.workers.UvicornWorker --bind 0.0.0.0:8001

# Check health
curl http://localhost:8001/api/v1/events/health

# View stats
curl http://localhost:8001/api/v1/events/stats

# Database migration
python create_event_log_table.py

# Run tests
python examples/test_event_router_integration.py

# Stop server
pkill -f "python start_server.py"
# or
Stop-Process -Name python -Force
```

---

**Next:** Configure Zoho webhooks and test with real data! 🚀
