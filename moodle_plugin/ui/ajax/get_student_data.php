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
 * AJAX endpoint to fetch student data from local_mzi_* Moodle DB tables.
 *
 * SOURCE OF TRUTH: Zoho CRM.
 * local_mzi_* tables are a write-through mirror maintained exclusively by the
 * Zoho → Middleware → Moodle WS pipeline.  This file MUST NOT call the backend
 * API or any external service — it reads only from local_mzi_* tables.
 *
 * @package    local_moodle_zoho_sync
 * @copyright  2026 Mohyeddine Farhat
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

require_login();

// CSRF protection.
$sesskey = required_param('sesskey', PARAM_ALPHANUM);
require_sesskey();

$userid   = required_param('userid', PARAM_INT);
$datatype = required_param('type', PARAM_ALPHANUMEXT);

// Students can only view their own data.
if ($userid != $USER->id) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => true, 'message' => 'Access denied – you can only view your own data']);
    exit;
}

/**
 * Emit JSON and terminate.
 */
function json_response($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

try {
    // ------------------------------------------------------------------
    // Resolve student mirror record by Moodle user ID
    // ------------------------------------------------------------------
    $student = $DB->get_record(
        'local_mzi_students',
        ['moodle_user_id' => $userid],
        '*',
        IGNORE_MISSING
    );

    switch ($datatype) {

        // ------------------------------------------------------------------
        case 'profile':
        // ------------------------------------------------------------------
            if (!$student) {
                json_response(['success' => true, 'student' => null]);
            }
            json_response([
                'success' => true,
                'student' => [
                    'student_id'     => $student->zoho_student_id  ?: 'N/A',
                    'full_name'      => trim($student->first_name . ' ' . $student->last_name),
                    'email'          => $student->email           ?: '',
                    'phone'          => $student->phone_number    ?: 'N/A',
                    'student_status' => $student->status          ?: 'Active',
                    'nationality'    => $student->nationality     ?: '',
                    'date_of_birth'  => $student->date_of_birth   ?: ''
                ],
            ]);
            break; // unreachable – json_response() exits, satisfies linters

        // ------------------------------------------------------------------
        case 'academics':
        // ------------------------------------------------------------------
            if (!$student) {
                json_response(['success' => true, 'programs' => []]);
            }
            $regs = $DB->get_records(
                'local_mzi_registrations',
                ['student_id' => $student->id],
                'registration_date DESC'
            );
            $programs = [];
            foreach ($regs as $reg) {
                $programs[] = [
                    'program_name'   => $reg->program_name        ?: 'N/A',
                    'program_status' => $reg->registration_status ?: 'N/A',
                    'start_date'     => $reg->registration_date   ?: 'N/A',
                    'units_count'    => (int)$DB->count_records('local_mzi_grades', ['student_id' => $student->id]),
                    'total_fees'     => (float)($reg->total_fees       ?? 0),
                    'remaining'      => (float)($reg->remaining_amount ?? 0),
                    'study_mode'     => $reg->study_mode ?: '',
                ];
            }
            json_response(['success' => true, 'programs' => $programs]);
            break;

        // ------------------------------------------------------------------
        case 'finance':
        // ------------------------------------------------------------------
            if (!$student) {
                json_response([
                    'success'  => true,
                    'summary'  => ['total_fees' => 0, 'amount_paid' => 0, 'balance_due' => 0],
                    'payments' => [],
                ]);
            }

            // Aggregate fees from registrations.
            $regs = $DB->get_records(
                'local_mzi_registrations',
                ['student_id' => $student->id],
                '',
                'id, total_fees, remaining_amount'
            );
            $total_fees  = 0;
            $balance_due = 0;
            foreach ($regs as $reg) {
                $total_fees  += (float)($reg->total_fees       ?? 0);
                $balance_due += (float)($reg->remaining_amount ?? 0);
            }

            // Payments list — join through registrations since payments have no direct student FK.
            $payment_rows = $DB->get_records_sql(
                "SELECT p.*
                   FROM {local_mzi_payments} p
                   JOIN {local_mzi_registrations} r ON r.id = p.registration_id
                  WHERE r.student_id = ?
                  ORDER BY p.payment_date DESC",
                [$student->id]
            );
            $amount_paid  = 0;
            $payments_out = [];
            foreach ($payment_rows as $p) {
                $amount_paid += (float)($p->payment_amount ?? 0);
                $payments_out[] = [
                    'payment_date'   => $p->payment_date   ?: 'N/A',
                    'amount'         => (float)($p->payment_amount ?? 0),
                    'payment_method' => $p->payment_method ?: 'N/A',
                    'payment_status' => $p->payment_status ?: 'Completed',
                    'note'           => $p->payment_notes  ?: '',
                ];
            }

            json_response([
                'success' => true,
                'summary' => [
                    'total_fees'  => $total_fees,
                    'amount_paid' => $amount_paid,
                    'balance_due' => $balance_due,
                ],
                'payments' => $payments_out,
            ]);
            break;

        // ------------------------------------------------------------------
        case 'classes':
        // ------------------------------------------------------------------
            if (!$student) {
                json_response(['success' => true, 'classes' => []]);
            }

            // Join enrollments → classes on Zoho class ID.
            $sql = "SELECT c.class_name, c.class_short_name, c.teacher_name,
                           c.start_date, c.end_date, c.class_status,
                           c.program_level, c.unit_name,
                           e.enrollment_date, e.enrollment_status
                      FROM {local_mzi_enrollments} e
                      JOIN {local_mzi_classes} c ON c.zoho_class_id = e.zoho_class_id
                     WHERE e.zoho_student_id = :zoho_student_id
                  ORDER BY e.enrollment_date DESC";

            $rows = $DB->get_records_sql($sql, ['zoho_student_id' => $student->zoho_student_id]);

            $classes_out = [];
            foreach ($rows as $row) {
                $schedule = $row->start_date ?: '';
                if ($schedule && $row->end_date) {
                    $schedule .= ' – ' . $row->end_date;
                }
                $classes_out[] = [
                    'class_name'        => $row->class_name        ?: 'N/A',
                    'instructor'        => $row->teacher_name      ?: 'N/A',  // key JS uses
                    'schedule'          => $schedule               ?: 'N/A',
                    'room'              => '',                                 // not in schema
                    'class_status'      => $row->class_status      ?: '',
                    'program_level'     => $row->program_level     ?: '',
                    'unit_name'         => $row->unit_name         ?: '',
                    'enrollment_status' => $row->enrollment_status ?: '',
                ];
            }
            json_response(['success' => true, 'classes' => $classes_out]);
            break;

        // ------------------------------------------------------------------
        case 'grades':
        // ------------------------------------------------------------------
            if (!$student) {
                json_response(['success' => true, 'grades' => []]);
            }
            $grade_rows = $DB->get_records(
                'local_mzi_grades',
                ['student_id' => $student->id],
                'grade_date DESC'
            );
            $grades_out = [];
            foreach ($grade_rows as $g) {
                // JS reads 'grade' — prefer named grade, fall back to numeric.
                $grade_display = $g->btec_grade_name
                    ?: ($g->numeric_grade !== null ? (string)$g->numeric_grade : 'N/A');
                $grades_out[] = [
                    'unit_name'       => $g->unit_name       ?: 'N/A',
                    'grade'           => $grade_display,
                    'grade_status'    => $g->grade_status    ?: 'N/A',
                    'submission_date' => $g->grade_date      ?: 'N/A',
                    'assignment_name' => $g->assignment_name ?: '',
                    'attempt_number'  => (int)($g->attempt_number ?? 1),
                    'feedback'        => $g->feedback        ?: '',
                ];
            }
            json_response(['success' => true, 'grades' => $grades_out]);
            break;

        // ------------------------------------------------------------------
        case 'requests':
        // ------------------------------------------------------------------
            if (!$student) {
                json_response(['success' => true, 'requests' => []]);
            }
            $req_rows = $DB->get_records(
                'local_mzi_requests',
                ['student_id' => $student->id],
                'created_at DESC'
            );
            $requests_out = [];
            foreach ($req_rows as $req) {
                $requests_out[] = [
                    'request_type'   => $req->request_type   ?: 'N/A',
                    'request_status' => $req->request_status ?: 'Pending',
                    'description'    => $req->description    ?: '',
                    'request_date'   => !empty($req->created_at) ? userdate($req->created_at, '%d %b %Y') : 'N/A',
                ];
            }
            json_response(['success' => true, 'requests' => $requests_out]);
            break;

        // ------------------------------------------------------------------
        default:
        // ------------------------------------------------------------------
            json_response(['error' => true, 'message' => 'Invalid data type: ' . $datatype]);
    }

} catch (Exception $e) {
    json_response(['error' => true, 'message' => $e->getMessage()]);
}
