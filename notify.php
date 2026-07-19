<?php
include 'db.php';
$merchant_secret = "YOUR_SANDBOX_MERCHANT_SECRET";

$merchant_id = $_POST['merchant_id'];
$order_id = $_POST['order_id'];
$payhere_amount = $_POST['payhere_amount'];
$payhere_currency = $_POST['payhere_currency'];
$status_code = $_POST['status_code'];
$md5sig = $_POST['md5sig'];

$local_md5sig = strtoupper(
    md5($merchant_id . $order_id . $payhere_amount . $payhere_currency . $status_code .
    strtoupper(md5($merchant_secret)))
);

if ($local_md5sig === $md5sig && $status_code == 2) {
    // Payment successful — update DB
    $conn->query("UPDATE payments SET status='paid', paid_date=NOW() 
                  WHERE order_id='$order_id'");
    // (order_id column ekak payments table ekata add karanna one)
}