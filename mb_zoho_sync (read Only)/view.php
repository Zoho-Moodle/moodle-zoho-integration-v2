<?php
require('../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mb_zoho_sync/view.php'));
$PAGE->set_title('My Financial Info');

$userid = $USER->id;
$finance = $DB->get_record('financeinfo', ['userid' => $userid]);

echo $OUTPUT->header();
echo $OUTPUT->heading('My Financial Information');
?>

<style>
/* ✅ تنسيقاتك الأصلية بدون تعديل */
@media (max-width: 768px) {
    .finance-section {
        flex-direction: column !important;
        gap: 20px !important;
    }
    .generaltable, .paymentstable {
        display: block;
        overflow-x: auto;
        width: 100% !important;
    }
    .generaltable colgroup, .paymentstable colgroup {
        display: none;
    }
    .finance-box {
        width: 100%;
    }
}

.finance-section {
    display: flex;
    justify-content: center;
    gap: 70px;
    flex-wrap: wrap;
    margin: 40px 0 60px 0;
}

.finance-box {
    max-width: 700px;
    flex: 1;
}

.finance-title {
    font-size: 18px;
    margin-bottom: 10px;
    font-weight: 600;
    color: #333;
    text-align: center;
}

.generaltable {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #ccc;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    min-height: 380px;
}

.generaltable colgroup col:first-child {
    width: 35%;
    background-color: #f9f9f9;
}
.generaltable colgroup col:last-child {
    width: 65%;
}

.generaltable th {
    background-color: #1e769c;
    color: white;
    font-weight: bold;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1.5px solid #ccc;
    border-right: 1.5px solid #ccc;
}
.generaltable td {
    padding: 14px 16px;
    border-bottom: 1px solid #eee;
    border-right: 1px solid #eee;
    background-color: #fff;
    font-size: 15px;
}

.paymentstable {
    margin: 0 auto 60px auto;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid #ccc;
    border-radius: 8px;
    width: 90%;
    max-width: 1100px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}
.paymentstable th {
    background-color: #1e769c;
    color: white;
    padding: 12px 16px;
    border-right: 1px solid #ccc;
    font-weight: bold;
    text-align: left;
}
.paymentstable td {
    padding: 12px 16px;
    border-right: 1px solid #eee;
    border-bottom: 1px solid #eee;
    background-color: #fff;
}
</style>

<?php
$fields = [
    'scholarship', 'scholarship_reason', 'scholarship_percentage', 'currency',
    'amount_transferred', 'payment_method', 'bank_name',
    'bank_holder', 'registration_fees', 'invoice_reg_fees',
    'total_amount', 'discount_amount',
];
$fields1 = array_slice($fields, 0, 6);
$fields2 = array_slice($fields, 6);

$tables = [
    ['title' => '', 'fields' => $fields1],
    ['title' => '', 'fields' => $fields2]
];

echo '<div class="finance-section">';
foreach ($tables as $t) {
    echo '<div class="finance-box">';
    echo '<h4 class="finance-title">' . $t['title'] . '</h4>';
    echo '<table class="generaltable">';
    echo '<colgroup><col><col></colgroup>';
    echo '<thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>';
    foreach ($t['fields'] as $f) {
        $value = $finance ? $finance->$f : '';
        echo '<tr>';
        echo '<td>' . ucfirst(str_replace('_', ' ', $f)) . '</td>';
        echo '<td data-field="' . $f . '">' . s($value) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}
echo '</div>';

$payments = [];
if ($finance) {
    $payments = $DB->get_records('financeinfo_payments', ['financeinfoid' => $finance->id]);
}

echo '<h5 style="margin: 40px 0 10px 0; font-size: 18px;">My Payments</h5>';
echo '<div style="overflow-x: auto;">';
echo '<table class="paymentstable">';
echo '<thead>
        <tr>
            <th>Payment Name</th>
            <th>Amount</th>
            <th>Date</th>
            <th>Invoice Number</th>
            <th>Notes</th>
        </tr>
      </thead><tbody id="payments-body">';

if (!empty($payments)) {
    foreach ($payments as $p) {
        echo '<tr>';
        echo '<td>' . s($p->payment_name) . '</td>';
        echo '<td>' . $p->amount . '</td>';
        echo '<td>' . date('Y-m-d', $p->payment_date) . '</td>';
        echo '<td>' . s($p->invoice_number) . '</td>';
        echo '<td>' . s($p->notes ?? '') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="5" style="text-align:center; color: gray;">No payments found.</td></tr>';
}
echo '</tbody></table>';
echo '</div>';

/* 🔵 زر مزامنة الطالب الحالي تحت الجداول بالكامل */
echo '<div style="text-align:center; margin: 40px 0;">
        <button id="sync-my-finance-btn" class="btn btn-success" style="font-size:18px; padding:12px 30px;">🔄 Sync My Finance</button>
      </div>';

echo $OUTPUT->footer();
?>

<script>
// ✅ توحيد ارتفاع الجدولين المالية
document.addEventListener("DOMContentLoaded", () => {
    const tables = document.querySelectorAll(".finance-section .generaltable");
    let maxHeight = 0;
    tables.forEach(table => {
        const height = table.offsetHeight;
        if (height > maxHeight) maxHeight = height;
    });
    tables.forEach(table => {
        table.style.height = maxHeight + "px";
    });
});

// ✅ زر مزامنة معلومات الطالب
document.getElementById("sync-my-finance-btn")?.addEventListener("click", function () {
    fetch("sync_finance_info.php?userid=<?= $userid ?>")
    .then(response => response.json())
    .then(result => {
        if (result.status === "success") {
            result.updated_fields.forEach(field => {
                const cell = document.querySelector('[data-field="' + field + '"]');
                if (cell && result.data[field] !== undefined) {
                    cell.textContent = result.data[field];
                    cell.style.backgroundColor = "#c8e6c9";
                    setTimeout(() => {
                        cell.style.backgroundColor = "";
                    }, 1500);
                }
            });
            const tbody = document.getElementById("payments-body");
            tbody.innerHTML = "";
            if (result.payments.length > 0) {
                result.payments.forEach(payment => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${payment.payment_name}</td>
                            <td>${payment.amount}</td>
                            <td>${payment.payment_date}</td>
                            <td>${payment.invoice_number}</td>
                            <td>${payment.notes}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color: gray;">No payments found.</td></tr>';
            }
        } else {
            alert("❌ " + result.message);
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("❌ حدث خطأ أثناء الاتصال.");
    });
});
</script>
