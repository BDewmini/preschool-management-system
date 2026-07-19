<?php
session_start();
include 'db.php';

// Only logged-in parents can see this page
if (!isset($_SESSION['parent_logged_in']) || $_SESSION['parent_logged_in'] !== true) {
    header('Location: parent_login.php');
    exit;
}

$parent_id   = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'] ?? 'Parent';

// Get this parent's children
$children = [];
$q = mysqli_query($conn, "SELECT * FROM students WHERE parent_id = '$parent_id'");
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $children[] = $row;
    }
}

// Build a list of student ids belonging to this parent (for filtering attendance/payments)
$student_ids = array_column($children, 'st_id');
$student_ids_in = count($student_ids) > 0 ? implode(',', array_map('intval', $student_ids)) : '0';

// Today's attendance for this parent's children
$today = date('Y-m-d');
$attendance_today = [];
$aq = mysqli_query($conn, "SELECT * FROM attendance WHERE date='$today' AND st_id IN ($student_ids_in)");
if ($aq) {
    while ($row = mysqli_fetch_assoc($aq)) {
        $attendance_today[$row['st_id']] = $row['status'];
    }
}

// Pending / overdue payments for this parent's children
$payments = [];
$pq = mysqli_query($conn, "SELECT * FROM payments WHERE st_id IN ($student_ids_in) AND status IN ('pending','overdue') ORDER BY p_id DESC");
if ($pq) {
    while ($row = mysqli_fetch_assoc($pq)) {
        $payments[] = $row;
    }
}

// Notifications (general feed — notifications table has no st_id link column)
$notifications = [];
$nq = mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
if ($nq) {
    while ($row = mysqli_fetch_assoc($nq)) {
        $notifications[] = $row;
    }
}
$unread_count = 0;
foreach ($notifications as $n) {
    if (isset($n['is_read']) && $n['is_read'] == 0) $unread_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Dashboard – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
  :root {
    --sun: #FFB830; --sky: #4FC3F7; --grass: #66BB6A; --rose: #F06292; --purple: #9575CD;
    --bg: #F0F7FF; --card: #FFFFFF; --text: #2D3A4A; --muted: #8A9BB0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg); color: var(--text);
    min-height: 100vh;
  }
  .topbar {
    background: linear-gradient(120deg, #1a2a4a 0%, #243756 60%, #1e3a5f 100%);
    padding: 24px 28px;
    display: flex; align-items: center; justify-content: space-between;
    color: #fff;
  }
  .brand { display: flex; align-items: center; gap: 12px; }
  .brand-icon { width: 42px; height: 42px; background: var(--sun); border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
  .brand-name { font-family: 'Fredoka One', cursive; font-size: 19px; }
  .brand-sub { font-size: 11px; color: rgba(255,255,255,0.5); letter-spacing: .5px; }
  .topbar-right { display: flex; align-items: center; gap: 14px; }
  .greet { font-size: 14px; color: rgba(255,255,255,0.8); }
  .logout-link {
    background: rgba(240,98,146,0.2); color: var(--rose);
    border-radius: 10px; padding: 8px 14px; text-decoration: none;
    font-size: 13px; font-weight: 700;
  }
  .content { padding: 28px; max-width: 1000px; margin: 0 auto; }

  .welcome-banner {
    background: #fff; border-radius: 18px; padding: 22px 26px;
    margin-bottom: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    display:flex; align-items:center; justify-content:space-between;
  }
  .welcome-banner h2 { font-size: 20px; font-weight: 900; }
  .welcome-banner p { color: var(--muted); font-size: 13px; margin-top: 4px; }

  .section-title { font-size: 15px; font-weight: 800; margin: 26px 0 12px; }

  .children-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px,1fr)); gap: 16px; }
  .child-card { background: #fff; border-radius: 16px; padding: 18px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
  .child-top { display:flex; align-items:center; gap:12px; margin-bottom: 10px; }
  .child-avatar { width: 44px; height: 44px; border-radius: 12px; background: #FFE0B2; display:flex; align-items:center; justify-content:center; font-size: 22px; }
  .child-name { font-weight: 800; font-size: 15px; }
  .child-class { font-size: 12px; color: var(--muted); }
  .attend-badge { display:inline-block; margin-top: 6px; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
  .attend-badge.present { background:#E8F5E9; color: var(--grass); }
  .attend-badge.absent  { background:#FCE4EC; color: var(--rose); }
  .attend-badge.unmarked{ background:#F0F4F8; color: var(--muted); }

  .panel { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
  .row { display:flex; align-items:center; justify-content:space-between; padding: 10px 0; border-bottom: 1px solid #F0F4F8; }
  .row:last-child { border-bottom: none; }
  .row-title { font-size: 14px; font-weight: 700; }
  .row-sub { font-size: 12px; color: var(--muted); }
  .amount { font-weight: 900; color: var(--rose); }
  .empty { color: var(--muted); font-size: 13px; text-align:center; padding: 18px 0; }

  .notif-item { padding: 10px 0; border-bottom: 1px solid #F0F4F8; }
  .notif-item:last-child { border-bottom:none; }
  .notif-msg { font-size: 13px; font-weight: 600; }
  .notif-time { font-size: 11px; color: var(--muted); margin-top: 2px; }
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-icon">🌟</div>
    <div>
      <div class="brand-name">Little Stars</div>
      <div class="brand-sub">PARENT PORTAL</div>
    </div>
  </div>
  <div class="topbar-right">
    <div class="greet">Hi, <?= htmlspecialchars($parent_name) ?> 👋</div>
    <a href="parent_logout.php" class="logout-link">Logout ↩</a>
  </div>
</div>

<div class="content">

  <div class="welcome-banner">
    <div>
      <h2>Welcome back!</h2>
      <p>Here's an update on your <?= count($children) ?> child<?= count($children) != 1 ? 'ren' : '' ?> today.</p>
    </div>
    <div style="font-size:44px;">🎒</div>
  </div>

  <div class="section-title">👧 My Children</div>
  <div class="children-grid">
    <?php if (count($children) === 0): ?>
      <p class="empty">No children linked to your account yet.</p>
    <?php else: ?>
      <?php foreach ($children as $c):
        $status = $attendance_today[$c['st_id']] ?? null;
        $badgeClass = $status === 'present' ? 'present' : ($status === 'absent' ? 'absent' : 'unmarked');
        $badgeText  = $status === 'present' ? '✅ Present Today' : ($status === 'absent' ? '❌ Absent Today' : '⏳ Not marked yet');
      ?>
      <div class="child-card">
        <div class="child-top">
          <div class="child-avatar">🧒</div>
          <div>
            <div class="child-name"><?= htmlspecialchars($c['name']) ?></div>
            <div class="child-class">Class: <?= htmlspecialchars($c['class'] ?? '—') ?></div>
          </div>
        </div>
        <span class="attend-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <a href="parent_gallery.php" style="display:inline-block; margin-top:16px; background:#9575CD; color:#fff; text-decoration:none; padding:10px 20px; border-radius:12px; font-size:13px; font-weight:800;">
  🖼️ View Photo Gallery
</a>

  <div class="section-title">💳 Payment Alerts</div>
  <div class="panel">
    <?php if (count($payments) === 0): ?>
      <p class="empty">No pending payments. You're all caught up! 🎉</p>
    <?php else: ?>
      <?php foreach ($payments as $p): ?>
      <div class="row">
        <div>
          <div class="row-title">School Fee — <?= htmlspecialchars($p['month'] ?? '') ?></div>
          <div class="row-sub">Status: <?= htmlspecialchars(ucfirst($p['status'])) ?><?= !empty($p['note']) ? ' • ' . htmlspecialchars($p['note']) : '' ?></div>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
          <div class="amount">LKR <?= number_format($p['amount'] ?? 0, 2) ?></div>
          <a href="payhere_checkout.php?p_id=<?= (int)$p['p_id'] ?>"
             style="background:#66BB6A; color:#fff; text-decoration:none; padding:8px 16px; border-radius:10px; font-size:13px; font-weight:800;">
             Pay Now
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="section-title">🔔 Notifications</div>
  <div class="panel">
    <?php if (count($notifications) === 0): ?>
      <p class="empty">No notifications right now.</p>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
      <div class="notif-item">
        <div class="notif-msg"><?= htmlspecialchars($n['message'] ?? '') ?></div>
        <div class="notif-time"><?= htmlspecialchars($n['created_at'] ?? '') ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
