<?php
session_start();
include 'db.php';
include 'payhere_config.php';

// Only logged-in parents can pay
if (!isset($_SESSION['parent_logged_in']) || $_SESSION['parent_logged_in'] !== true) {
    header('Location: parent_login.php');
    exit;
}

$parent_id = $_SESSION['parent_id'];
$p_id = intval($_GET['p_id'] ?? 0);

if ($p_id <= 0) {
    die('Invalid payment reference.');
}

// Fetch the payment AND verify it belongs to this parent's child (security check)
$sql = "SELECT p.*, s.name AS student_name, s.parent_id
        FROM payments p
        JOIN students s ON p.st_id = s.st_id
        WHERE p.p_id = $p_id AND s.parent_id = '$parent_id'
        LIMIT 1";
$res = mysqli_query($conn, $sql);
$payment = $res ? mysqli_fetch_assoc($res) : null;

if (!$payment) {
    die('Payment not found or you do not have permission to pay this.');
}

if ($payment['status'] === 'Paid') {
    die('This payment has already been made. Thank you!');
}

// Build a unique order_id we can match on the notify callback
$order_id = 'PAY' . $payment['p_id'] . '-' . time();

// Save the order_id against this payment row so notify handler can match it back
$order_id_esc = mysqli_real_escape_string($conn, $order_id);
mysqli_query($conn, "UPDATE payments SET order_id='$order_id_esc' WHERE p_id=$p_id");

$amount   = number_format((float)$payment['amount'], 2, '.', '');
$currency = 'LKR';

// Hash as required by PayHere
$hashed_secret = strtoupper(md5(PAYHERE_MERCHANT_SECRET));
$hash = strtoupper(
    md5(
        PAYHERE_MERCHANT_ID .
        $order_id .
        $amount .
        $currency .
        $hashed_secret
    )
);

// Parent info for the form (fallbacks if not set)
$parent_name  = $_SESSION['parent_name']  ?? 'Parent';
$parent_email = $_SESSION['parent_email'] ?? 'parent@example.com';
$name_parts   = explode(' ', trim($parent_name), 2);
$first_name   = $name_parts[0] ?? 'Parent';
$last_name    = $name_parts[1] ?? '-';

// Build the site base URL automatically (works for both localhost and ngrok)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Redirecting to PayHere…</title>
<style>
  body { font-family: 'Nunito', sans-serif; background:#F0F7FF; display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .box { background:#fff; padding:40px; border-radius:18px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,.08); }
  .spinner { width:40px; height:40px; border:4px solid #E0E8F0; border-top-color:#F06292; border-radius:50%; animation:spin 1s linear infinite; margin:0 auto 16px; }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>
<div class="box">
  <div class="spinner"></div>
  <p>Redirecting you to PayHere secure checkout…</p>
</div>

<form id="payhere-form" method="POST" action="<?= htmlspecialchars(PAYHERE_CHECKOUT_URL) ?>">
  <input type="hidden" name="merchant_id" value="<?= htmlspecialchars(PAYHERE_MERCHANT_ID) ?>">
  <input type="hidden" name="return_url" value="<?= htmlspecialchars($base_url) ?>/payhere_return.php">
  <input type="hidden" name="cancel_url" value="<?= htmlspecialchars($base_url) ?>/payhere_cancel.php">
  <input type="hidden" name="notify_url" value="<?= htmlspecialchars($base_url) ?>/payhere_notify.php">

  <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
  <input type="hidden" name="items" value="School Fee - <?= htmlspecialchars($payment['month']) ?>">
  <input type="hidden" name="currency" value="<?= htmlspecialchars($currency) ?>">
  <input type="hidden" name="amount" value="<?= htmlspecialchars($amount) ?>">
  <input type="hidden" name="hash" value="<?= htmlspecialchars($hash) ?>">

  <input type="hidden" name="first_name" value="<?= htmlspecialchars($first_name) ?>">
  <input type="hidden" name="last_name" value="<?= htmlspecialchars($last_name) ?>">
  <input type="hidden" name="email" value="<?= htmlspecialchars($parent_email) ?>">
  <input type="hidden" name="phone" value="0770000000">
  <input type="hidden" name="address" value="Colombo">
  <input type="hidden" name="city" value="Colombo">
  <input type="hidden" name="country" value="Sri Lanka">
</form>

<script>
  document.getElementById('payhere-form').submit();
</script>
</body>
</html>
