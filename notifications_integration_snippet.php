<?php
/* ============================================================
   1) At the TOP of notifications.php, right after:
        include 'db.php';
      add this line:
   ============================================================ */

require_once 'sms_helper.php';


/* ============================================================
   2) Inside the PAYMENT ALERTS while-loop (around line 330),
      right after this line:

        $is_overdue = $pa['status'] === 'overdue' || (int)$pa['days_overdue'] > 30;

      add this block. It only fires ONCE per payment record,
      because it checks + sets sms_sent_at.
   ============================================================ */

if ($is_overdue && empty($pa['sms_sent_at'])) {
    $msg = "Little Stars Pre School: " . $pa['name'] . "ge fee "
         . "LKR " . number_format((float)$pa['amount'], 2)
         . " Payment is Overdue, Please Confirm Your Payment. Thanks.";

    $result = sendSMS($pa['parent_phone'], $msg);

    if ($result['success']) {
        $conn->query("UPDATE payments SET sms_sent_at = NOW() WHERE st_id = '{$pa['st_id']}' AND status IN ('pending','overdue')");
    } else {
        error_log("SMS failed for {$pa['name']}: " . $result['error']);
    }
}


/* ============================================================
   3) IMPORTANT: the payment_alerts SQL query (around line 17)
      needs to also select the new column. Update it to:
   ============================================================ */

$payment_alerts = $conn->query("
    SELECT
        s.st_id, s.name, s.parent_phone,
        c.class_name,
        p.amount, p.status, p.paid_date, p.sms_sent_at,
        DATEDIFF(CURDATE(), p.paid_date) AS days_overdue
    FROM payments p
    JOIN students s ON s.st_id = p.st_id
    LEFT JOIN classes c ON c.class_id = s.class_id
    WHERE p.status IN ('pending', 'overdue')
    ORDER BY p.status DESC, days_overdue DESC
");


/* ============================================================
   4) Inside the ABSENT ALERTS while-loop (around line 386),
      right after:

        $ac = $a_colors[$ai % count($a_colors)]; $ai++;

      add this block:
   ============================================================ */

if (empty($ab['sms_sent_at'])) {
    $msg = "Little Stars Pre School: " . $ab['name']
         . "Your Baby is Absent Today";

    $result = sendSMS($ab['parent_phone'], $msg);

    if ($result['success']) {
        $conn->query("UPDATE attendance SET sms_sent_at = NOW() WHERE st_id = '{$ab['st_id']}' AND date = '{$ab['date']}'");
    } else {
        error_log("SMS failed for {$ab['name']}: " . $result['error']);
    }
}


/* ============================================================
   5) The absent_alerts SQL query (around line 33) also needs
      the new column added:
   ============================================================ */

$absent_alerts = $conn->query("
    SELECT
        s.st_id, s.name, s.parent_phone,
        c.class_name,
        a.date, a.status AS att_status,
        a.note, a.sms_sent_at
    FROM attendance a
    JOIN students s ON s.st_id = a.st_id
    LEFT JOIN classes c ON c.class_id = s.class_id
    WHERE a.date = '$today' AND a.status = 'absent'
    ORDER BY c.class_name ASC
");
