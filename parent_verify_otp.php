<?php
session_start();

// Redirect back to login if no OTP session exists
if (!isset($_SESSION['otp_code'])) {
    header('Location: parent_login.php');
    exit;
}

$error = '';

// Mask the phone number, e.g. 0771234567 -> 077****567
$phoneRaw = $_SESSION['otp_parent_phone'] ?? '';
$maskedPhone = $phoneRaw;
if (strlen($phoneRaw) >= 6) {
    $maskedPhone = substr($phoneRaw, 0, 3) . str_repeat('*', strlen($phoneRaw) - 6) . substr($phoneRaw, -3);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = trim($_POST['otp'] ?? '');

    if (time() > ($_SESSION['otp_expires_at'] ?? 0)) {
        $error = 'Your code has expired. Please request a new one.';
        unset($_SESSION['otp_code']);
    } elseif ($enteredOtp !== $_SESSION['otp_code']) {
        $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;

        if ($_SESSION['otp_attempts'] >= 3) {
            // Too many wrong attempts — kill this OTP, force a fresh one
            unset($_SESSION['otp_code'], $_SESSION['otp_expires_at'], $_SESSION['otp_attempts']);
            header('Location: parent_login.php?error=too_many_attempts');
            exit;
        }

        $error = 'Incorrect code. Please try again. ('
                . (3 - $_SESSION['otp_attempts']) . ' attempt(s) left)';
    } else {
        // Success — log the parent in
        $_SESSION['parent_logged_in'] = true;
        $_SESSION['parent_id']        = $_SESSION['otp_parent_id'];
        $_SESSION['parent_name']      = $_SESSION['otp_parent_name'];
        $_SESSION['parent_phone']     = $_SESSION['otp_parent_phone'];

        unset(
            $_SESSION['otp_code'],
            $_SESSION['otp_expires_at'],
            $_SESSION['otp_parent_id'],
            $_SESSION['otp_attempts']
        );

        header('Location: parent_dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Code – Little Stars Pre School</title>
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
input[type="text"] {
  width: 100%; padding: 14px 16px; border-radius: 12px;
  border: 2px solid #E8EEF5; font-size: 24px; font-weight: 800;
  letter-spacing: 8px; text-align: center; font-family: inherit;
  margin-bottom: 18px; outline: none; transition: border-color .18s;
}
input[type="text"]:focus { border-color: var(--sky); }
button {
  width: 100%; padding: 14px; border: none; border-radius: 12px;
  background: var(--rose); color: #fff; font-size: 15px; font-weight: 800;
  cursor: pointer; transition: opacity .18s; font-family: inherit;
  margin-bottom: 12px;
}
button:hover { opacity: .9; }
.error {
  background: #FCE4EC; color: #C62828; padding: 12px 16px;
  border-radius: 10px; font-size: 13px; margin-bottom: 18px; font-weight: 600;
}
a.resend { color: var(--sky); font-size: 13px; font-weight: 700; text-decoration: none; }
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>
<div class="login-card">
  <div class="logo">📱</div>
  <h1>Enter Your Code</h1>
  <p class="subtitle">We sent a 6-digit code to<br><strong><?= htmlspecialchars($maskedPhone) ?></strong></p>

  <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="text" name="otp" maxlength="6" inputmode="numeric" placeholder="------" required autofocus>
    <button type="submit">Verify & Login ✅</button>
  </form>
  <a href="parent_login.php" class="resend">↻ Send a new code</a>
</div>
</body>
</html>
