# 🎴 Student Card Design - ABC Horizon

## 📐 Card Specifications

**Size:** 85.6mm × 53.98mm (Standard Credit Card Size - CR80)  
**Orientation:** Landscape  
**Print Quality:** 300 DPI  
**Format:** HTML → PDF (via wkhtmltopdf or browser print)

---

## 🎨 Design Layout

### **Front Side:**

```
┌─────────────────────────────────────────────────────────────────┐
│  🏛️ ABC HORIZON                                    [LOGO] 🎓   │
│  Business & Technology Institute                                │
│  ──────────────────────────────────────────────────────────────│
│                                                                  │
│  ┌────────┐                                                     │
│  │        │   AHMED ALI HASSAN                                  │
│  │ PHOTO  │   Digital Marketing - Level 3                       │
│  │        │   Student ID: ST-000123                             │
│  └────────┘   BTEC Reg: BTEC-SRM-2024-001                      │
│                                                                  │
│  ──────────────────────────────────────────────────────────────│
│  Valid Until: 30 Jun 2025           🔐 [QR Code]               │
│  Status: ✅ ACTIVE                                              │
└─────────────────────────────────────────────────────────────────┘
```

### **Back Side:**

```
┌─────────────────────────────────────────────────────────────────┐
│  ⚠️ IMPORTANT NOTICE                                            │
│  ──────────────────────────────────────────────────────────────│
│                                                                  │
│  This card is the property of ABC Horizon Institute.            │
│  If found, please return to:                                    │
│                                                                  │
│  📍 Address: Damascus, Syria                                    │
│  📞 Phone: +963 XXX XXX XXX                                     │
│  📧 Email: info@abchorizon.com                                  │
│  🌐 Website: www.abchorizon.com                                 │
│                                                                  │
│  ──────────────────────────────────────────────────────────────│
│  Issued on: 16 Feb 2026                    Signature: _______  │
│                                                                  │
│  [Barcode: *ST000123*]                                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📝 HTML Template (Front)

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Card - <?php echo $student_name; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: 85.6mm 53.98mm;
            margin: 0;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            width: 85.6mm;
            height: 53.98mm;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .card-container {
            width: 100%;
            height: 100%;
            padding: 8mm;
            position: relative;
        }
        
        /* Header */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6mm;
            border-bottom: 1px solid rgba(255,255,255,0.3);
            padding-bottom: 3mm;
        }
        
        .institute-name {
            flex: 1;
        }
        
        .institute-name h1 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 1mm;
            letter-spacing: 0.5px;
        }
        
        .institute-name p {
            font-size: 8pt;
            opacity: 0.9;
        }
        
        .logo {
            width: 15mm;
            height: 15mm;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10pt;
            color: #667eea;
            font-weight: bold;
        }
        
        /* Student Info */
        .student-info {
            display: flex;
            gap: 4mm;
        }
        
        .student-photo {
            width: 18mm;
            height: 22mm;
            background: white;
            border-radius: 3mm;
            border: 2px solid rgba(255,255,255,0.8);
            overflow: hidden;
        }
        
        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .student-details {
            flex: 1;
        }
        
        .student-name {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 1.5mm;
            text-transform: uppercase;
        }
        
        .program-name {
            font-size: 8pt;
            margin-bottom: 1mm;
            opacity: 0.95;
        }
        
        .student-id,
        .btec-reg {
            font-size: 7pt;
            opacity: 0.9;
            font-family: 'Courier New', monospace;
        }
        
        /* Footer */
        .card-footer {
            position: absolute;
            bottom: 8mm;
            left: 8mm;
            right: 8mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(255,255,255,0.3);
            padding-top: 3mm;
        }
        
        .validity {
            font-size: 7pt;
        }
        
        .validity-label {
            opacity: 0.8;
            margin-bottom: 0.5mm;
        }
        
        .validity-date {
            font-weight: bold;
            font-size: 8pt;
        }
        
        .status {
            font-size: 8pt;
            font-weight: bold;
            background: rgba(76, 175, 80, 0.9);
            padding: 2mm 4mm;
            border-radius: 3mm;
        }
        
        .qr-code {
            width: 12mm;
            height: 12mm;
            background: white;
            border-radius: 2mm;
            padding: 1mm;
        }
        
        .qr-code img {
            width: 100%;
            height: 100%;
        }
        
        /* Decorative Elements */
        .card-container::before {
            content: '';
            position: absolute;
            top: -20mm;
            right: -20mm;
            width: 40mm;
            height: 40mm;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .card-container::after {
            content: '';
            position: absolute;
            bottom: -15mm;
            left: -15mm;
            width: 30mm;
            height: 30mm;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="card-container">
        <!-- Header -->
        <div class="card-header">
            <div class="institute-name">
                <h1>ABC HORIZON</h1>
                <p>Business & Technology Institute</p>
            </div>
            <div class="logo">
                🎓
            </div>
        </div>
        
        <!-- Student Info -->
        <div class="student-info">
            <div class="student-photo">
                <?php if ($student_photo): ?>
                    <img src="<?php echo $student_photo; ?>" alt="Student Photo">
                <?php else: ?>
                    <img src="default-avatar.png" alt="No Photo">
                <?php endif; ?>
            </div>
            
            <div class="student-details">
                <div class="student-name"><?php echo strtoupper($student_name); ?></div>
                <div class="program-name"><?php echo $program_name; ?> - Level <?php echo $level; ?></div>
                <div class="student-id">Student ID: <?php echo $student_id; ?></div>
                <div class="btec-reg">BTEC Reg: <?php echo $btec_reg; ?></div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="card-footer">
            <div class="validity">
                <div class="validity-label">Valid Until:</div>
                <div class="validity-date"><?php echo $valid_until; ?></div>
            </div>
            
            <div class="status">
                ✅ <?php echo strtoupper($status); ?>
            </div>
            
            <div class="qr-code">
                <img src="<?php echo $qr_code_url; ?>" alt="QR Code">
            </div>
        </div>
    </div>
</body>
</html>
```

---

## 📝 HTML Template (Back)

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Card Back - <?php echo $student_name; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: 85.6mm 53.98mm;
            margin: 0;
        }
        
        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            width: 85.6mm;
            height: 53.98mm;
            background: white;
            color: #333;
            position: relative;
        }
        
        .card-back {
            width: 100%;
            height: 100%;
            padding: 6mm;
        }
        
        .notice-header {
            text-align: center;
            background: #f44336;
            color: white;
            padding: 2mm;
            margin-bottom: 4mm;
            border-radius: 2mm;
            font-size: 10pt;
            font-weight: bold;
        }
        
        .notice-content {
            font-size: 7pt;
            line-height: 1.4;
            margin-bottom: 4mm;
        }
        
        .notice-content p {
            margin-bottom: 1.5mm;
        }
        
        .contact-info {
            font-size: 7pt;
            line-height: 1.5;
            border-top: 1px solid #ddd;
            padding-top: 2mm;
            margin-bottom: 3mm;
        }
        
        .contact-info div {
            margin-bottom: 1mm;
        }
        
        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #ddd;
            padding-top: 2mm;
            font-size: 6pt;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            width: 25mm;
            margin-top: 1mm;
        }
        
        .barcode {
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 14pt;
            letter-spacing: 2px;
            margin-top: 2mm;
        }
    </style>
</head>
<body>
    <div class="card-back">
        <div class="notice-header">
            ⚠️ IMPORTANT NOTICE
        </div>
        
        <div class="notice-content">
            <p>This card is the property of <strong>ABC Horizon Institute</strong>.</p>
            <p>If found, please return to:</p>
        </div>
        
        <div class="contact-info">
            <div>📍 <strong>Address:</strong> Damascus, Syria</div>
            <div>📞 <strong>Phone:</strong> +963 XXX XXX XXX</div>
            <div>📧 <strong>Email:</strong> info@abchorizon.com</div>
            <div>🌐 <strong>Website:</strong> www.abchorizon.com</div>
        </div>
        
        <div class="card-meta">
            <div>
                <strong>Issued on:</strong> <?php echo $issue_date; ?>
            </div>
            <div>
                <strong>Signature:</strong>
                <div class="signature-line"></div>
            </div>
        </div>
        
        <div class="barcode">
            *<?php echo $student_id; ?>*
        </div>
    </div>
</body>
</html>
```

---

## 🔧 PHP Controller (student_card.php)

```php
<?php
require_once(__DIR__ . '/../../../../../config.php');
require_login();

// Get student data
$userid = optional_param('userid', $USER->id, PARAM_INT);

// Verify the user can view this card
if ($userid != $USER->id && !has_capability('moodle/site:config', context_system::instance())) {
    throw new moodle_exception('nopermission');
}

// Fetch student data from database
global $DB;
$student = $DB->get_record_sql("
    SELECT 
        s.*,
        r.Program_Name,
        r.Level,
        r.Registration_Status,
        r.End_Date as Valid_Until
    FROM {local_mzi_students} s
    LEFT JOIN {local_mzi_registrations} r ON s.id = r.student_id
    WHERE s.moodle_user_id = ?
    AND r.Registration_Status = 'Active'
    ORDER BY r.Registration_Date DESC
    LIMIT 1
", [$userid]);

if (!$student) {
    throw new moodle_exception('studentnotfound', 'local_moodle_zoho_sync');
}

// Check if student has active status
if ($student->Registration_Status !== 'Active') {
    throw new moodle_exception('cardnotavailable', 'local_moodle_zoho_sync', '', 
        'Student card is only available for active students.');
}

// Prepare data
$student_data = [
    'student_name' => $student->First_Name . ' ' . $student->Last_Name,
    'student_id' => $student->Student_ID,
    'btec_reg' => $student->BTEC_Registration_Number,
    'program_name' => $student->Program_Name,
    'level' => $student->Level,
    'status' => $student->Registration_Status,
    'valid_until' => userdate(strtotime($student->Valid_Until), '%d %b %Y'),
    'issue_date' => userdate(time(), '%d %b %Y'),
    'student_photo' => $student->Photo_URL ?? null,
    'qr_code_url' => generate_qr_code($student->Student_ID) // Function to generate QR
];

// Output based on request type
$side = optional_param('side', 'front', PARAM_ALPHA);

if ($side === 'back') {
    include('includes/card_back_template.php');
} else {
    include('includes/card_front_template.php');
}

// Helper function to generate QR code
function generate_qr_code($student_id) {
    // Using Google Charts API or phpqrcode library
    return "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . 
           urlencode("https://lms.abchorizon.com/verify?id=" . $student_id);
}
```

---

## 📥 كيفية الطباعة/التحميل

### **Option 1: Browser Print**

```javascript
// Add print button in Profile page
<button onclick="printCard()">🖨️ Print Student Card</button>

<script>
function printCard() {
    // Open card in new window
    var frontWindow = window.open(
        '/local/moodle_zoho_sync/ui/student/student_card.php?side=front',
        'StudentCardFront',
        'width=400,height=250'
    );
    
    // Wait for load then print
    frontWindow.onload = function() {
        frontWindow.print();
    };
}
</script>
```

### **Option 2: PDF Generation**

```php
// Using mPDF or TCPDF
require_once($CFG->libdir . '/pdflib.php');

$pdf = new pdf();
$pdf->AddPage('L', 'CREDIT_CARD'); // Landscape, Credit Card size
$pdf->writeHTML($html_content);
$pdf->Output('student_card_' . $student_id . '.pdf', 'D'); // Download
```

### **Option 3: Backend PDF Generation**

```python
# Backend endpoint: POST /api/v1/student-card/generate
from weasyprint import HTML

@router.post("/student-card/generate")
async def generate_student_card(student_id: str):
    # Fetch student data
    student = get_student_data(student_id)
    
    # Render HTML template
    html_content = render_template('student_card_front.html', **student)
    
    # Generate PDF
    pdf = HTML(string=html_content).write_pdf()
    
    return Response(content=pdf, media_type='application/pdf')
```

---

## 🎯 Workflow

```
1. Student goes to Profile page
2. Sees "📥 Download Student Card" button
3. Clicks button
4. System checks:
   - Status = Active ✅
   - Has active registration ✅
5. Generates HTML card (front + back)
6. Converts to PDF or opens print dialog
7. Student downloads/prints
```

---

## 🔐 Security

- ✅ **Only Active students** can generate cards
- ✅ **Watermark** with generation date
- ✅ **QR Code** for verification (links to public verification page)
- ✅ **Barcode** with Student ID
- ✅ **Expiry date** based on registration end date

---

## 📱 Mobile Responsive Alternative

```html
<!-- For mobile display (not print) -->
<div class="digital-card">
    <div class="card-front">
        <!-- Same design but responsive units (vw, vh) -->
    </div>
    <button onclick="flipCard()">Flip to Back</button>
</div>
```

هل تصميم البطاقة يناسبك؟ 🎴
