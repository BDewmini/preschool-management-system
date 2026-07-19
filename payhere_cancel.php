<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Cancelled – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Nunito',sans-serif; background:#F0F7FF; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .card { background:#fff; border-radius:24px; padding:40px 32px; max-width:380px; width:100%; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,.08); }
  .icon { font-size:60px; margin-bottom:12px; }
  h1 { font-size:20px; margin-bottom:8px; color:#2D3A4A; }
  p { color:#8A9BB0; font-size:14px; margin-bottom:24px; }
  a { display:inline-block; background:#F06292; color:#fff; text-decoration:none; padding:12px 24px; border-radius:12px; font-weight:800; font-size:14px; }
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>
  <div class="card">
    <div class="icon">⚠️</div>
    <h1>Payment Cancelled</h1>
    <p>Your payment was not completed. You can try again anytime from your dashboard.</p>
    <a href="parent_dashboard.php">Back to Dashboard</a>
  </div>
</body>
</html>
