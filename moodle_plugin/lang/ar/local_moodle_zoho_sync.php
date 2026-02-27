<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Arabic language strings for the Moodle-Zoho Integration plugin.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'تكامل Moodle-Zoho';

// Scheduled tasks.
$string['task_retry_failed_webhooks'] = 'إعادة محاولة Webhooks الفاشلة';
$string['task_cleanup_old_logs'] = 'تنظيف السجلات القديمة';
$string['task_health_monitor'] = 'مراقبة صحة النظام';
$string['task_sync_missing_grades'] = 'نظام التقييم الهجين: إثراء الدرجات، كشف RR، إنشاء درجات F';

// Hybrid Grading System
$string['gradequeue_monitor'] = 'مراقب قائمة الدرجات';
$string['gradequeue_pending'] = 'بانتظار الإثراء';
$string['gradequeue_enriched'] = 'مُثرى';
$string['gradequeue_failed'] = 'فاشل';
$string['gradequeue_total'] = 'إجمالي القائمة';
$string['gradequeue_status'] = 'الحالة';
$string['gradequeue_needs_enrichment'] = 'يحتاج إثراء';
$string['gradequeue_needs_rr_check'] = 'يحتاج فحص RR';
$string['gradequeue_retry'] = 'إعادة محاولة الفاشل';
$string['gradequeue_composite_key'] = 'المفتاح المركب';
$string['gradequeue_zoho_record_id'] = 'معرف سجل Zoho';
$string['gradequeue_error_message'] = 'رسالة الخطأ';
$string['gradequeue_basic_sent'] = 'تم إرسال البيانات الأساسية';
$string['gradequeue_enrichment_failed'] = 'فشل الإثراء';
$string['gradequeue_f_created'] = 'تم إنشاء درجة F';
$string['gradequeue_rr_detected'] = 'تم كشف RR';$string['gradequeue_workflow_state'] = 'حالة سير العمل';
$string['gradequeue_invalid_submission'] = 'تسليم غير صالح (01122)';
// Navigation
$string['mystudentarea'] = 'منطقة الطالب';
$string['studentprofile'] = 'الملف الشخصي';
$string['myprograms'] = 'برامجي';
$string['myclasses'] = 'حصصي';
$string['mygrades'] = 'درجاتي';
$string['myrequests'] = 'طلباتي';
$string['studentcard'] = 'بطاقة الطالب';
$string['sync_management'] = 'إدارة Zoho Sync';
$string['event_logs'] = 'سجلات الأحداث';
$string['statistics'] = 'الإحصائيات';
