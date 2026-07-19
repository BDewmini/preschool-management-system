<?php
// This file is called SERVER-TO-SERVER by PayHere (not by the browser).
// It verifies the payment and updates the payments table.

include 'db.php';
include 'payhere_config.php';

$order_id       = $_POST['order_id']        ?? '';
$payhere_amount = $_POST['payhere_amount']  ?? '';
$payhere_currency = $_POST['payhere_currency'] ?? '';
$status_code    = $_POST['status_code']     ?? '';
$md5sig         = $_POST['md5sig']          ?? '';
$method         = $_POST['method']          ?? 'Online';

if (!$order_id || !$md5sig) {
    http_response_code(400);
    exit('Missing parameters');
}

// Recreate the signature PayHere expects and compare
$local_md5sig = strtoupper(
    md5(
        PAYHERE_MERCHANT_ID .
        $order_id .
        $payhere_amount .
        $payhere_currency .
        $status_code .
        strtoupper(md5(PAYHERE_MERCHANT_SECRET))
    )
);

if ($local_md5sig !== $md5sig) {
    http_response_code(400);
    exit('Invalid signature');
}

// status_code 2 = success in PayHere's system
if ($status_code == '2') {
    $order_id_esc = mysqli_real_escape_string($conn, $order_id);
    $method_esc   = mysqli_real_escape_string($conn, $method);
    $today        = date('Y-m-d');

    mysqli_query($conn, "
        UPDATE payments
        SET status='Paid', paid_date='$today', method='$method_esc', note='Paid online via PayHere'
        WHERE order_id='$order_id_esc'
    ");
}
// status_code -1 = cancelled, -2 = failed, -3 = charged back — no update needed,
// payment simply stays pending/overdue.

http_response_code(200);
echo 'OK';
