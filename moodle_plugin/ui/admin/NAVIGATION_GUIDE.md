# Universal Navigation System - Implementation Guide

## 📚 Overview
This navigation system provides consistent navigation across all UI pages with:
- **Navigation Bar** with icons and active state
- **Breadcrumbs** for context
- **Responsive design** for mobile
- **Unified styling** for consistency

---

## 🚀 Quick Implementation

### **Method 1: Using Shared Functions (Recommended)**

Add to the top of your page (after `$OUTPUT->header()`):

```php
require_once(__DIR__ . '/includes/navigation.php');

echo $OUTPUT->header();

// Output navigation styles (once per page)
mzi_output_navigation_styles();

// Start page container
echo '<div class="mzi-page-container">';

// Render navigation bar
mzi_render_navigation('your_page_key', 'Page Title', 'Optional Subtitle');

// Render breadcrumb
mzi_render_breadcrumb('Your Page Name');

// Your page content here
echo '<div class="mzi-content-wrapper">';
// ... your content ...
echo '</div>';

// Close page container
echo '</div>';

echo $OUTPUT->footer();
```

### **Page Keys Available:**
- `dashboard` - Dashboard
- `grade_monitor` - Grade Operations Monitor
- `event_logs` - Event Logs
- `health_check` - Health Check
- `statistics` - Statistics
- `sync_management` - Sync Management

---

## 📝 Example Implementation

### **Example 1: Dashboard Page**

```php
<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/navigation.php');

admin_externalpage_setup('local_moodle_zoho_sync_dashboard');

$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/dashboard.php'));
$PAGE->set_title('Dashboard');
$PAGE->set_heading('Dashboard');

echo $OUTPUT->header();

// Output navigation
mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('dashboard', 'Moodle-Zoho Integration', 'System Dashboard');
mzi_render_breadcrumb('Dashboard');

echo '<div class="mzi-content-wrapper">';
?>

<!-- Your dashboard content here -->
<div class="dashboard-content">
    <h2>Welcome to the Dashboard</h2>
    <!-- ... your content ... -->
</div>

<?php
echo '</div>'; // Close mzi-content-wrapper
echo '</div>'; // Close mzi-page-container
echo $OUTPUT->footer();
?>
```

### **Example 2: Event Logs Page**

```php
<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/navigation.php');

admin_externalpage_setup('local_moodle_zoho_sync_event_logs');

$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/event_logs.php'));
$PAGE->set_title('Event Logs');
$PAGE->set_heading('Event Logs');

echo $OUTPUT->header();

// Output navigation
mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('event_logs', 'Moodle-Zoho Integration', 'Event Logs & History');
mzi_render_breadcrumb('Event Logs');

echo '<div class="mzi-content-wrapper">';
?>

<!-- Your event logs content here -->
<div class="event-logs-content">
    <h2>Recent Events</h2>
    <!-- ... your content ... -->
</div>

<?php
echo '</div>';
echo '</div>';
echo $OUTPUT->footer();
?>
```

---

## 🎨 Visual Design

### **Navigation Bar Features:**
- **Gradient Background**: Purple gradient (`#667eea` → `#764ba2`)
- **Active State**: White highlight bar on top + lighter background
- **Hover Effect**: Subtle background change + top bar animation
- **Icons**: Large emoji icons for visual identification
- **Responsive**: Stacks vertically on mobile

### **Breadcrumb Features:**
- White background with subtle shadow
- Blue links with hover effect
- Current page in bold
- Responsive font sizing

### **Layout Structure:**
```
┌─────────────────────────────────────────────────────┐
│  mzi-page-container (max-width: 1600px)             │
│  ┌───────────────────────────────────────────────┐  │
│  │  mzi-nav-bar (gradient purple background)    │  │
│  │  - Navigation Header (title + subtitle)      │  │
│  │  - Navigation Items (horizontal icons)       │  │
│  └───────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────┐  │
│  │  mzi-breadcrumb (white card)                 │  │
│  │  Home › Moodle-Zoho Sync › Current Page     │  │
│  └───────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────┐  │
│  │  mzi-content-wrapper                         │  │
│  │  - Your page content goes here               │  │
│  └───────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

---

## 🔧 Customization Options

### **Custom Page Title:**
```php
mzi_render_navigation('your_page', 'Custom Title', 'Custom Subtitle');
```

### **Custom Breadcrumb:**
```php
mzi_render_breadcrumb('Your Custom Page Name');
```

### **Add New Navigation Item:**
Edit `includes/navigation.php`, add to `$nav_items` array:
```php
[
    'name' => 'New Page',
    'url' => new moodle_url('/local/moodle_zoho_sync/ui/admin/new_page.php'),
    'icon' => '🆕',
    'key' => 'new_page',
    'description' => 'Description for tooltip'
]
```

---

## 📊 Existing Page Updates

### **Pages That Need Navigation:**

1. ✅ **grade_queue_monitor.php** - Already updated
2. ⏳ **dashboard.php** - Needs update
3. ⏳ **event_logs.php** - Needs update
4. ⏳ **event_logs_enhanced.php** - Needs update
5. ⏳ **health_check.php** - Needs update
6. ⏳ **health_monitor_detailed.php** - Needs update
7. ⏳ **statistics.php** - Needs update
8. ⏳ **sync_management.php** - Needs update
9. ⏳ **btec_templates.php** - Needs update
10. ⏳ **event_detail.php** - Needs update

---

## 🎯 Implementation Checklist

For each page, follow this checklist:

- [ ] Add `require_once(__DIR__ . '/includes/navigation.php');` at top
- [ ] After `$OUTPUT->header()`, add:
  - [ ] `mzi_output_navigation_styles();`
  - [ ] `echo '<div class="mzi-page-container">';`
  - [ ] `mzi_render_navigation('page_key', 'Title', 'Subtitle');`
  - [ ] `mzi_render_breadcrumb('Page Name');`
  - [ ] `echo '<div class="mzi-content-wrapper">';`
- [ ] Wrap existing content in content wrapper
- [ ] Before `$OUTPUT->footer()`, add:
  - [ ] `echo '</div>'; // Close mzi-content-wrapper`
  - [ ] `echo '</div>'; // Close mzi-page-container`
- [ ] Test navigation links work
- [ ] Test active state highlights correctly
- [ ] Test responsive design on mobile

---

## 🐛 Troubleshooting

### **Navigation not showing?**
- Check `includes/navigation.php` exists and is readable
- Verify `require_once` path is correct (use `__DIR__`)

### **Styles not applying?**
- Ensure `mzi_output_navigation_styles()` is called ONCE
- Check for CSS conflicts with existing page styles

### **Active state not working?**
- Verify `$current_page` parameter matches a key in `$nav_items`
- Check page key is correct (e.g., `'dashboard'` not `'Dashboard'`)

### **Layout broken?**
- Ensure page container div is opened AND closed
- Check all div closing tags are present
- Verify content wrapper is properly nested

### **Mobile view issues?**
- Test with browser dev tools responsive mode
- Check media query breakpoint (768px)
- Verify flex-direction changes to column

---

## 💡 Best Practices

1. **Consistent Structure**: Always use the same div structure
2. **Semantic HTML**: Use proper heading levels (h1 for title, h2 for sections)
3. **Accessibility**: Include title attributes for navigation links
4. **Performance**: Call `mzi_output_navigation_styles()` once per page
5. **Maintainability**: Don't duplicate navigation code - use shared functions

---

## 📱 Responsive Behavior

### **Desktop (>768px):**
- Horizontal navigation bar
- Icons above labels
- Full breadcrumb text

### **Mobile (<768px):**
- Vertical stacked navigation
- Icons beside labels
- Smaller breadcrumb font

---

## 🎨 Color Scheme

```css
Primary Gradient: #667eea → #764ba2
Active State: rgba(255, 255, 255, 0.15)
Hover State: rgba(255, 255, 255, 0.1)
Text Color: white / rgba(255, 255, 255, 0.85)
Breadcrumb Links: #007bff
```

---

## 📦 Files Structure

```
moodle_plugin/ui/admin/
├── includes/
│   └── navigation.php          # Shared navigation functions
├── grade_queue_monitor.php     # Example: Already implemented
├── dashboard.php               # To be updated
├── event_logs.php              # To be updated
├── health_check.php            # To be updated
├── statistics.php              # To be updated
├── sync_management.php         # To be updated
└── NAVIGATION_GUIDE.md         # This file
```

---

## 🚀 Quick Start Example

Copy-paste this template for new pages:

```php
<?php
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/includes/navigation.php');

admin_externalpage_setup('local_moodle_zoho_sync_YOUR_PAGE');

$PAGE->set_url(new moodle_url('/local/moodle_zoho_sync/ui/admin/YOUR_PAGE.php'));
$PAGE->set_title('Your Page Title');
$PAGE->set_heading('Your Page Heading');

echo $OUTPUT->header();
mzi_output_navigation_styles();
echo '<div class="mzi-page-container">';
mzi_render_navigation('your_page_key', 'Moodle-Zoho Integration', 'Your Page Subtitle');
mzi_render_breadcrumb('Your Page Name');
echo '<div class="mzi-content-wrapper">';

// ═══════════════════════════════════════
// YOUR PAGE CONTENT HERE
// ═══════════════════════════════════════
?>

<div class="your-page-content">
    <h2>Your Content</h2>
    <!-- ... -->
</div>

<?php
// ═══════════════════════════════════════
// END PAGE CONTENT
// ═══════════════════════════════════════

echo '</div>'; // Close mzi-content-wrapper
echo '</div>'; // Close mzi-page-container
echo $OUTPUT->footer();
```

---

**Status:** ✅ Navigation System Complete  
**Version:** 1.0  
**Last Updated:** February 2026  
**Author:** ABC Horizon Development Team

**تم إنشاء نظام Navigation كامل وجاهز للاستخدام! 🎉**
