<?php
session_start();
include 'db.php';
require_once 'fcm_notifications.php';
require_once 'sms_helper.php';

// Payment alert example
$parentToken = $conn->query("SELECT token FROM fcm_tokens LIMIT 1")
    ->fetch_assoc()['token'] ?? null;

if ($parentToken) {
    notifyPaymentDue($parentToken, 'Ranulya Ishali', 'LKR 5,000');
}

$user = $_SESSION['user'] ?? null;

// ── PAYMENT DUE / OVERDUE ALERTS ─────────────────────
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
$payment_count = $payment_alerts ? mysqli_num_rows($payment_alerts) : 0;

// ── ATTENDANCE ALERTS (absent today) ─────────────────
$today = date('Y-m-d');
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
$absent_count = $absent_alerts ? mysqli_num_rows($absent_alerts) : 0;

// ── BIRTHDAY ALERTS (this month) ─────────────────────
$this_month = date('m');
$birthday_alerts = $conn->query("
    SELECT
        s.st_id, s.name, s.date_of_birth, s.parent_phone,
        c.class_name,
        DAY(s.date_of_birth) AS bday,
        MONTH(s.date_of_birth) AS bmonth,
        DATEDIFF(
            DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(s.date_of_birth), '-', DAY(s.date_of_birth))),
            CURDATE()
        ) AS days_left
    FROM students s
    LEFT JOIN classes c ON c.class_id = s.class_id
    WHERE MONTH(s.date_of_birth) = '$this_month'
    AND s.status = 'active'
    ORDER BY DAY(s.date_of_birth) ASC
");
$birthday_count = $birthday_alerts ? mysqli_num_rows($birthday_alerts) : 0;

// ── TOTAL UNREAD COUNT ────────────────────────────────
$total_alerts = $payment_count + $absent_count + $birthday_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
:root {
  --sun:    #FFB830;
  --sky:    #fc03c2;
  --grass:  #66BB6A;
  --rose:   #F06292;
  --purple: #9575CD;
  --bg:     #F0F7FF;
  --card:   #FFFFFF;
  --text:   #2D3A4A;
  --muted:  #8A9BB0;
  --sidebar-w: 240px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Nunito', sans-serif;
  background: var(--bg); color: var(--text);
  min-height: 100vh; display: flex;
}

/* ── SIDEBAR ── */
.sidebar {
  width: var(--sidebar-w);
  background: linear-gradient(160deg, #1a2a4a 0%, #243756 100%);
  min-height: 100vh; display: flex; flex-direction: column;
  position: fixed; top: 0; left: 0; z-index: 100;
}
.sidebar-brand {
  padding: 28px 24px 20px;
  display: flex; align-items: center; gap: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.brand-icon {
  width: 42px; height: 42px; background: var(--sun);
  border-radius: 12px; display: flex; align-items: center;
  justify-content: center; font-size: 22px;
}
.brand-name { font-family: 'Fredoka One', cursive; font-size: 20px; color: #fff; line-height: 1.1; }
.brand-sub  { font-size: 11px; color: rgba(255,255,255,0.45); letter-spacing: .5px; }
.nav { padding: 20px 12px; flex: 1; }
.nav-label {
  font-size: 10px; font-weight: 800; letter-spacing: 1.5px;
  color: rgba(255,255,255,0.3); text-transform: uppercase;
  padding: 0 12px; margin: 16px 0 6px;
}
.nav a {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 14px; border-radius: 10px;
  color: rgba(255,255,255,0.65); text-decoration: none;
  font-size: 14px; font-weight: 600;
  transition: all .18s; margin-bottom: 2px; position: relative;
}
.nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.nav a.active { background: rgba(79,195,247,0.2); color: var(--sky); }
.nav a .icon { font-size: 18px; width: 22px; text-align: center; }
.nav-badge {
  margin-left: auto; background: var(--rose);
  color: #fff; border-radius: 20px;
  font-size: 10px; font-weight: 900;
  padding: 2px 7px; min-width: 20px; text-align: center;
}
.sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.08); }
.user-info { display: flex; align-items: center; gap: 10px; }
.avatar {
  width: 36px; height: 36px; border-radius: 50%; background: var(--rose);
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; color: #fff; font-size: 15px;
}
.user-name { font-size: 13px; font-weight: 700; color: #fff; }
.user-role  { font-size: 11px; color: rgba(255,255,255,0.4); }
.logout-btn {
  margin-left: auto; background: rgba(240,98,146,0.2);
  border: none; border-radius: 8px; padding: 6px 10px;
  cursor: pointer; color: var(--rose); font-size: 18px;
  transition: background .18s; text-decoration: none;
  display: flex; align-items: center;
}
.logout-btn:hover { background: rgba(240,98,146,0.4); }

/* ── MAIN ── */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar {
  background: var(--card); padding: 18px 32px;
  display: flex; align-items: center; justify-content: space-between;
  cursor: pointer;border-bottom: 1px solid #E8EEF5; position: sticky; top: 0; z-index: 50;
}
.page-title { font-size: 22px; font-weight: 800; }
.page-title span { color: var(--sky); }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.date-badge {
  background: var(--bg); border-radius: 20px;
  padding: 6px 14px; font-size: 13px; font-weight: 600; color: var(--muted);
}
.total-badge {
  background: var(--rose); color: #fff;
  border-radius: 20px; padding: 6px 16px;
  font-size: 13px; font-weight: 900;
}

/* CONTENT */
.content { padding: 28px 32px; flex: 1; }

/* STAT ROW */
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card {
  background: var(--card); border-radius: 16px; padding: 20px 24px;
  cursor: pointer;box-shadow: 0 2px 12px rgba(0,0,0,.05);
  display: flex; align-items: center; gap: 16px;
  transition: transform .2s;
}
.stat-card:hover { transform: translateY(-3px); }
.stat-icon {
  width: 52px; height: 52px; border-radius: 14px;
  cursor: pointer;display: flex; align-items: center; justify-content: center; font-size: 24px;
}
.stat-num   { font-size: 28px; font-weight: 900; line-height: 1; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; margin-top: 3px; }

/* SECTION */
.section { margin-bottom: 28px; }
.section-header {
  cursor: pointer;display: flex; align-items: center; gap: 10px;
  margin-bottom: 14px;
}
.section-title {
  cursor: pointer;font-size: 16px; font-weight: 900;
}
.section-count {
  cursor: pointer;padding: 3px 12px; border-radius: 20px;
  font-size: 12px; font-weight: 800;
}

/* NOTIFICATION CARD */
.notif-list { display: flex; flex-direction: column; gap: 12px; }
.notif-card {
  cursor: pointer;background: var(--card); border-radius: 14px;
  padding: 16px 20px; box-shadow: 0 2px 10px rgba(0,0,0,.05);
  display: flex; align-items: center; gap: 16px;
  border-left: 4px solid transparent;
  transition: transform .18s, box-shadow .18s;
}
.notif-card:hover { transform: translateX(4px); box-shadow: 0 4px 18px rgba(0,0,0,.09); }
.notif-card.payment  { border-left-color: var(--rose); }
.notif-card.absent   { border-left-color: var(--sun); }
.notif-card.birthday { border-left-color: var(--purple); }

.notif-icon {
  width: 44px; height: 44px; border-radius: 12px;
  cursor: pointer;display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.notif-body { flex: 1; }
.notif-title { font-size: 14px; font-weight: 800; margin-bottom: 3px; }
.notif-sub   { font-size: 12px; color: var(--muted); font-weight: 600; }
.notif-right { text-align: right; flex-shrink: 0; }
.notif-tag {
  display: inline-block; padding: 4px 12px;
  border-radius: 20px; font-size: 11px; font-weight: 800;
}
.tag-rose   { background: #FCE4EC; color: #C62828; }
.tag-sun    { background: #FFF3CD; color: #F57F17; }
.tag-purple { background: #EDE7F6; color: #6A1B9A; }
.tag-grass  { background: #E8F5E9; color: #2E7D32; }
.tag-sky    { background: #E0F4FD; color: #0277BD; }

.notif-meta { font-size: 11px; color: var(--muted); margin-top: 4px; }

/* EMPTY */
.empty-notif {
  background: var(--card); border-radius: 14px;
  cursor: pointer;padding: 32px; text-align: center;
  box-shadow: 0 2px 10px rgba(0,0,0,.05);
}
.empty-notif .ei { font-size: 36px; margin-bottom: 8px; }
.empty-notif p { color: var(--muted); font-weight: 600; font-size: 14px; }

/* BADGE */
.badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; }
.badge-grass  { background: #E8F5E9; color: #2E7D32; }
.badge-rose   { background: #FCE4EC; color: #C62828; }
.badge-sky    { background: #E0F4FD; color: #0277BD; }
.badge-sun    { background: #FFF3CD; color: #F57F17; }
.badge-purple { background: #EDE7F6; color: #6A1B9A; }

/* SMS TAG */
.tag-sms-sent { background: #E8F5E9; color: #2E7D32; }
.tag-sms-fail { background: #FCE4EC; color: #C62828; }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; }
  .stat-row { grid-template-columns: 1fr; }
  .notif-card { flex-wrap: wrap; }
}
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<!-- ── SIDEBAR ── -->
<?php include 'sidebar.php'; ?>

<!-- ── MAIN ── -->
<div class="main">
  <div class="topbar">
    <div class="page-title">🔔 <span>Notifications</span></div>
    <div class="topbar-right">
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <?php if ($total_alerts > 0): ?>
        <div class="total-badge">🔴 <?= $total_alerts ?> Alert<?= $total_alerts > 1 ? 's' : '' ?></div>
      <?php else: ?>
        <div class="total-badge" style="background:var(--grass);">✅ All Clear</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">

    <!-- ── STAT CARDS ── -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-icon" style="background:#FCE4EC;">💳</div>
        <div>
          <div class="stat-num" style="color:var(--rose)"><?= $payment_count ?></div>
          <div class="stat-label">Payment Alerts</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#FFF3CD;">📋</div>
        <div>
          <div class="stat-num" style="color:var(--sun)"><?= $absent_count ?></div>
          <div class="stat-label">Absent Today</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#EDE7F6;">🎂</div>
        <div>
          <div class="stat-num" style="color:var(--purple)"><?= $birthday_count ?></div>
          <div class="stat-label">Birthdays This Month</div>
        </div>
      </div>
    </div>

    <!-- ══ 1. PAYMENT ALERTS ══ -->
    <div class="section">
      <div class="section-header">
        <span style="font-size:22px;">💳</span>
        <div class="section-title">Payment Due / Overdue Alerts</div>
        <span class="section-count" style="background:#FCE4EC; color:#C62828;"><?= $payment_count ?> alert<?= $payment_count != 1 ? 's' : '' ?></span>
      </div>

      <?php if ($payment_count > 0): ?>
      <div class="notif-list">
        <?php
        mysqli_data_seek($payment_alerts, 0);
        $pi = 0;
        $p_colors = ['#4FC3F7','#66BB6A','#F06292','#FFB830','#9575CD','#26C6DA'];
        while ($pa = mysqli_fetch_assoc($payment_alerts)):
          $pc = $p_colors[$pi % count($p_colors)]; $pi++;
          $is_overdue = $pa['status'] === 'overdue' || (int)$pa['days_overdue'] > 30;

          // ── Send SMS once for overdue payments ──────────────
          $sms_status = null;
          if ($is_overdue && empty($pa['sms_sent_at'])) {
              $sms_msg = "Little Stars Pre School: " . $pa['name'] . "ge fee "
                       . "LKR " . number_format((float)$pa['amount'], 2)
                       . " Payment is Overdue. Please continue Your Payment. Thank You";

              $sms_result = sendSMS($pa['parent_phone'], $sms_msg);

              if ($sms_result['success']) {
                  $conn->query("UPDATE payments SET sms_sent_at = NOW() 
                                WHERE st_id = '{$pa['st_id']}' AND status IN ('pending','overdue')");
                  $sms_status = 'sent';
              } else {
                  error_log("SMS failed for " . $pa['name'] . ": " . $sms_result['error']);
                  $sms_status = 'failed';
              }
          } elseif (!empty($pa['sms_sent_at'])) {
              $sms_status = 'already_sent';
          }
        ?>
        <div class="notif-card payment">
          <div class="notif-icon" style="background:<?= $is_overdue ? '#FCE4EC' : '#FFF3CD' ?>;">
            <?= $is_overdue ? '🚨' : '⏳' ?>
          </div>
          <div class="notif-body">
            <div class="notif-title">
              <?= htmlspecialchars($pa['name']) ?>
              <span class="notif-tag <?= $is_overdue ? 'tag-rose' : 'tag-sun' ?>" style="margin-left:6px;">
                <?= $is_overdue ? 'Overdue' : 'Pending' ?>
              </span>
              <?php if ($sms_status === 'sent'): ?>
                <span class="notif-tag tag-sms-sent" style="margin-left:6px;">📲 SMS Sent</span>
              <?php elseif ($sms_status === 'already_sent'): ?>
                <span class="notif-tag tag-sms-sent" style="margin-left:6px;">📲 SMS Sent</span>
              <?php elseif ($sms_status === 'failed'): ?>
                <span class="notif-tag tag-sms-fail" style="margin-left:6px;">⚠️ SMS Failed</span>
              <?php endif; ?>
            </div>
            <div class="notif-sub">
              🏫 <?= htmlspecialchars($pa['class_name'] ?? '—') ?>
              &nbsp;·&nbsp; 📞 <?= htmlspecialchars($pa['parent_phone'] ?? '—') ?>
            </div>
            <?php if ($pa['days_overdue'] > 0): ?>
            <div class="notif-meta">⚠️ <?= $pa['days_overdue'] ?> days overdue</div>
            <?php endif; ?>
          </div>
          <div class="notif-right">
            <div style="font-size:18px; font-weight:900; color:var(--rose);">
              LKR <?= number_format((float)$pa['amount'], 2) ?>
            </div>
            <div class="notif-meta" style="margin-top:4px;">
              <?= $pa['paid_date'] ? '📅 Due: '.date('d M Y', strtotime($pa['paid_date'])) : 'No due date set' ?>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
      <div class="empty-notif">
        <div class="ei">✅</div>
        <p>No payment alerts — all payments are up to date!</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ 2. ABSENT ALERTS ══ -->
    <div class="section">
      <div class="section-header">
        <span style="font-size:22px;">📋</span>
        <div class="section-title">Absent Students Today — <?= date('d M Y') ?></div>
        <span class="section-count" style="background:#FFF3CD; color:#F57F17;"><?= $absent_count ?> student<?= $absent_count != 1 ? 's' : '' ?></span>
      </div>

      <?php if ($absent_count > 0): ?>
      <div class="notif-list">
        <?php
        mysqli_data_seek($absent_alerts, 0);
        $ai = 0;
        $a_colors = ['#4FC3F7','#66BB6A','#F06292','#FFB830','#9575CD','#26C6DA'];
        while ($ab = mysqli_fetch_assoc($absent_alerts)):
          $ac = $a_colors[$ai % count($a_colors)]; $ai++;

          // ── Send SMS once for today's absence ───────────────
          $sms_status = null;
          if (empty($ab['sms_sent_at'])) {
              $sms_msg = "Little Stars Pre School: " . $ab['name']
                       . " Your Baby is Absent today. Please call the office. Thank You";

              $sms_result = sendSMS($ab['parent_phone'], $sms_msg);

              if ($sms_result['success']) {
                  $conn->query("UPDATE attendance SET sms_sent_at = NOW() 
                                WHERE st_id = '{$ab['st_id']}' AND date = '{$ab['date']}'");
                  $sms_status = 'sent';
              } else {
                  error_log("SMS failed for " . $ab['name'] . ": " . $sms_result['error']);
                  echo "<pre style='background:#fdd;padding:10px'>DEBUG SMS ERROR: " . htmlspecialchars(print_r($sms_result, true)) . "</pre>";
                  $sms_status = 'failed';
              }
          } else {
              $sms_status = 'already_sent';
          }
        ?>
        <div class="notif-card absent">
          <div class="notif-icon" style="background:#FFF3CD;">❌</div>
          <div class="notif-body">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
              <div style="width:34px; height:34px; border-radius:50%;
                          background:<?= $ac ?>22; color:<?= $ac ?>;
                          display:flex; align-items:center; justify-content:center;
                          font-weight:900; font-size:14px; border:2px solid <?= $ac ?>44; flex-shrink:0;">
                <?= strtoupper(substr($ab['name'], 0, 1)) ?>
              </div>
              <div>
                <div class="notif-title">
                  <?= htmlspecialchars($ab['name']) ?>
                  <?php if ($sms_status === 'sent'): ?>
                    <span class="notif-tag tag-sms-sent" style="margin-left:6px;">📲 SMS Sent</span>
                  <?php elseif ($sms_status === 'already_sent'): ?>
                    <span class="notif-tag tag-sms-sent" style="margin-left:6px;">📲 SMS Sent</span>
                  <?php elseif ($sms_status === 'failed'): ?>
                    <span class="notif-tag tag-sms-fail" style="margin-left:6px;">⚠️ SMS Failed</span>
                  <?php endif; ?>
                </div>
                <div class="notif-sub">
                  🏫 <?= htmlspecialchars($ab['class_name'] ?? '—') ?>
                  &nbsp;·&nbsp; 📞 <?= htmlspecialchars($ab['parent_phone'] ?? '—') ?>
                </div>
              </div>
            </div>
            <?php if ($ab['note']): ?>
              <div class="notif-meta">📝 Note: <?= htmlspecialchars($ab['note']) ?></div>
            <?php endif; ?>
          </div>
          <div class="notif-right">
            <span class="notif-tag tag-sun">Absent</span>
            <div class="notif-meta" style="margin-top:6px;">📅 <?= date('d M Y', strtotime($ab['date'])) ?></div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
      <div class="empty-notif">
        <div class="ei">🎉</div>
        <p>All students are present today!</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ 3. BIRTHDAY ALERTS ══ -->
    <div class="section">
      <div class="section-header">
        <span style="font-size:22px;">🎂</span>
        <div class="section-title">Birthdays — <?= date('F Y') ?></div>
        <span class="section-count" style="background:#EDE7F6; color:#6A1B9A;"><?= $birthday_count ?> birthday<?= $birthday_count != 1 ? 's' : '' ?></span>
      </div>

      <?php if ($birthday_count > 0): ?>
      <div class="notif-list">
        <?php
        mysqli_data_seek($birthday_alerts, 0);
        $bi = 0;
        $b_colors = ['#9575CD','#4FC3F7','#66BB6A','#F06292','#FFB830','#26C6DA'];
        while ($bd = mysqli_fetch_assoc($birthday_alerts)):
          $bc = $b_colors[$bi % count($b_colors)]; $bi++;
          $is_today  = date('m-d') === date('m-d', strtotime($bd['date_of_birth']));
          $days_left = (int)$bd['days_left'];
          $age_turning = $bd['date_of_birth'] ? (date('Y') - date('Y', strtotime($bd['date_of_birth']))) : '?';
        ?>
        <div class="notif-card birthday" style="<?= $is_today ? 'background: linear-gradient(135deg, #f3e5f5 0%, #fff 100%);' : '' ?>">
          <div class="notif-icon" style="background:<?= $bc ?>22; font-size:24px;">
            <?= $is_today ? '🎉' : '🎂' ?>
          </div>
          <div class="notif-body">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
              <div style="width:34px; height:34px; border-radius:50%;
                          background:<?= $bc ?>22; color:<?= $bc ?>;
                          display:flex; align-items:center; justify-content:center;
                          font-weight:900; font-size:14px; border:2px solid <?= $bc ?>44; flex-shrink:0;">
                <?= strtoupper(substr($bd['name'], 0, 1)) ?>
              </div>
              <div>
                <div class="notif-title">
                  <?= htmlspecialchars($bd['name']) ?>
                  <?php if ($is_today): ?>
                    <span class="notif-tag tag-purple" style="margin-left:6px;">🎉 Today!</span>
                  <?php endif; ?>
                </div>
                <div class="notif-sub">
                  🏫 <?= htmlspecialchars($bd['class_name'] ?? '—') ?>
                  &nbsp;·&nbsp; 🎂 Turning <?= $age_turning ?>
                </div>
              </div>
            </div>
            <div class="notif-meta">
              📅 <?= date('d M', strtotime($bd['date_of_birth'])) ?>
              &nbsp;·&nbsp; 📞 <?= htmlspecialchars($bd['parent_phone'] ?? '—') ?>
            </div>
          </div>
          <div class="notif-right">
            <?php if ($is_today): ?>
              <span class="notif-tag tag-purple">Today! 🎉</span>
            <?php elseif ($days_left >= 0): ?>
              <span class="notif-tag tag-sky">in <?= $days_left ?> day<?= $days_left != 1 ? 's' : '' ?></span>
            <?php else: ?>
              <span class="notif-tag" style="background:#F0F4F8; color:var(--muted);">Passed</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
      <div class="empty-notif">
        <div class="ei">🎂</div>
        <p>No birthdays this month.</p>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</body>
</html>