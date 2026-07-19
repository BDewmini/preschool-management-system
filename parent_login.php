<?php
session_start();
include 'db.php';
require_once 'sms_helper.php';

// ── OTP settings ─────────────────────────────────────
define('OTP_EXPIRY_SECONDS', 300); // 5 minutes

function generateOTP(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');

    if ($phone === '') {
        $error = 'Please enter your phone number.';
    } else {
        // Find parent by phone number
        $stmt = $conn->prepare("SELECT parent_id, name, phone FROM parents WHERE phone = ? LIMIT 1");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        $parent = $result->fetch_assoc();

        if (!$parent) {
            $error = 'No account found with that phone number.';
        } else {
            // Generate OTP and store in session
            $otp = generateOTP();
            $_SESSION['otp_code']         = $otp;
            $_SESSION['otp_expires_at']   = time() + OTP_EXPIRY_SECONDS;
            $_SESSION['otp_parent_id']    = $parent['parent_id'];
            $_SESSION['otp_parent_name']  = $parent['name'];
            $_SESSION['otp_parent_phone'] = $parent['phone'];
            $_SESSION['otp_attempts']     = 0; // reset wrong-attempt counter

            $msg = "Little Stars Pre School: Your login code is $otp. "
                 . "Valid for 5 minutes. Do not share this code with anyone.";

            $sendResult = sendSMS($parent['phone'], $msg);

            if ($sendResult['success']) {
                header('Location: parent_verify_otp.php');
                exit;
            } else {
                $error = 'Failed to send the login code. Please try again later.';
                error_log('OTP SMS error: ' . $sendResult['error']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Login – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root {
  --sun: #FFB830; --sky: #4FC3F7; --rose: #F06292;
  --bg: #F0F7FF; --card: #FFFFFF; --text: #2D3A4A; --muted: #8A9BB0;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Nunito', sans-serif;
  background: var(--bg); color: var(--text);
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.login-card {
  background: var(--card); border-radius: 24px;
  padding: 40px 36px; max-width: 380px; width: 100%;
  box-shadow: 0 8px 32px rgba(0,0,0,.08);
  text-align: center;
}
.logo { font-size: 48px; margin-bottom: 8px; }
h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.subtitle { color: var(--muted); font-size: 14px; margin-bottom: 28px; }
label { display: block; text-align: left; font-size: 13px; font-weight: 700; margin-bottom: 6px; }
input[type="tel"] {
  width: 100%; padding: 14px 16px; border-radius: 12px;
  border: 2px solid #E8EEF5; font-size: 16px; font-family: inherit;
  margin-bottom: 18px; outline: none; transition: border-color .18s;
}
input[type="tel"]:focus { border-color: var(--sky); }
button {
  width: 100%; padding: 14px; border: none; border-radius: 12px;
  background: var(--rose); color: #fff; font-size: 15px; font-weight: 800;
  cursor: pointer; transition: opacity .18s; font-family: inherit;
}
button:hover { opacity: .9; }
.error {
  background: #FCE4EC; color: #C62828; padding: 12px 16px;
  border-radius: 10px; font-size: 13px; margin-bottom: 18px; font-weight: 600;
}
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>
<div class="login-card">
  <div class="logo">🌟</div>
  <h1>Parent Login</h1>
  <p class="subtitle">We'll text a login code to your phone</p>

  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="phone">Phone Number</label>
    <input type="tel" id="phone" name="phone" placeholder="07XXXXXXXX" required
           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
    <button type="submit">Send Login Code 📱</button>
  </form>
</div>
</body>
</html>
