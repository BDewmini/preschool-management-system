<?php
session_start();
include 'db.php';

// Notification counts for bell icon
$notif_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payments WHERE status IN ('pending','overdue')");
$pay_cnt = mysqli_fetch_assoc($notif_q)['cnt'];

$today_n = date('Y-m-d');
$notif_q2 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM attendance WHERE date='$today_n' AND status='absent'");
$abs_cnt = mysqli_fetch_assoc($notif_q2)['cnt'];

$this_month_n = date('m');
$notif_q3 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM students WHERE MONTH(date_of_birth)='$this_month_n' AND status='active'");
$bday_cnt = mysqli_fetch_assoc($notif_q3)['cnt'];

$unread = $pay_cnt + $abs_cnt + $bday_cnt;

//if(!isset($_SESSION['user'])){
//    header("Location: login.php");
//    exit();
//}

$user = $_SESSION['user'] ?? null;

// Fetch counts safely
function safe_count($conn, $table) {
    $r = mysqli_query($conn, "SELECT COUNT(*) as c FROM `$table`");
    return ($r) ? (mysqli_fetch_assoc($r)['c'] ?? 0) : 0;
}
$total_students = safe_count($conn, 'students');
$total_teachers = safe_count($conn, 'teachers');
$total_classes  = safe_count($conn, 'classes');
$total_parents  = safe_count($conn, 'parents');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard – Pre School</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
  <style>
    :root {
      --sun:    #FFB830;
      --sky:    #4FC3F7;
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
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar-w);
      background: linear-gradient(160deg, #1a2a4a 0%, #243756 100%);
      height: 100vh;
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0;
      z-index: 100;
      overflow-y: auto;
    }
    .sidebar-brand { flex-shrink: 0; }
    .sidebar-footer { flex-shrink: 0; }
    .sidebar-brand {
      padding: 28px 24px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .brand-icon {
      width: 42px; height: 42px;
      background: var(--sun);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
    }
    .brand-name {
      font-family: 'Fredoka One', cursive;
      font-size: 20px;
      color: #fff;
      line-height: 1.1;
    }
    .brand-sub { font-size: 11px; color: rgba(255,255,255,0.45); letter-spacing: .5px; }

    .nav { padding: 20px 12px; flex: 1; }
    .nav-label {
      font-size: 10px; font-weight: 800; letter-spacing: 1.5px;
      color: rgba(255,255,255,0.3); text-transform: uppercase;
      padding: 0 12px; margin: 16px 0 6px;
    }
    .nav a {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 14px;
      border-radius: 10px;
      color: rgba(255,255,255,0.65);
      text-decoration: none;
      font-size: 14px; font-weight: 600;
      transition: all .18s;
      margin-bottom: 2px;
    }
    .nav a:hover, .nav a.active {
      background: rgba(255,255,255,0.1);
      color: #fff;
    }
    .nav a.active { background: rgba(79,195,247,0.2); color: var(--sky); }
    .nav a span.icon { font-size: 18px; width: 22px; text-align: center; }

    .nav a.logout-link {
      color: rgba(240,98,146,0.85);
    }
    .nav a.logout-link:hover {
      background: rgba(240,98,146,0.15);
      color: #fff;
    }

    .sidebar-footer {
      padding: 16px 20px;
      border-top: 1px solid rgba(255,255,255,0.08);
    }
    .user-info { display: flex; align-items: center; gap: 10px; }
    .avatar {
      width: 36px; height: 36px; border-radius: 50%;
      background: var(--rose);
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; color: #fff; font-size: 15px;
    }
    .user-name { font-size: 13px; font-weight: 700; color: #fff; }
    .user-role { font-size: 11px; color: rgba(255,255,255,0.4); }
    .logout-btn {
      margin-left: auto;
      background: rgba(240,98,146,0.2);
      border: none; border-radius: 8px;
      padding: 6px 10px; cursor: pointer;
      color: var(--rose); font-size: 18px;
      transition: background .18s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .logout-btn:hover { background: rgba(240,98,146,0.4); }

    /* ── MAIN ── */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* ── TOPBAR ── */
    .topbar {
      background: var(--card);
      padding: 18px 32px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1px solid #E8EEF5;
      position: sticky; top: 0; z-index: 50;
    }
    .page-title { font-size: 22px; font-weight: 800; }
    .page-title span { color: var(--sky); }
    .topbar-right { display: flex; align-items: center; gap: 14px; }
    .date-badge {
      background: var(--bg);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 13px; font-weight: 600; color: var(--muted);
    }
    .notif-btn {
      width: 38px; height: 38px; border-radius: 50%;
      background: var(--bg); border: none; cursor: pointer;
      font-size: 18px; display: flex; align-items: center; justify-content: center;
      position: relative;
    }
    .notif-dot {
      width: 9px; height: 9px; background: var(--rose);
      border-radius: 50%; border: 2px solid #fff;
      position: absolute; top: 6px; right: 6px;
    }

    /* ── CONTENT ── */
    .content { padding: 28px 32px; flex: 1; }

    /* Welcome banner */
    .welcome-banner {
      background: linear-gradient(120deg, #1a2a4a 0%, #243756 60%, #1e3a5f 100%);
      border-radius: 20px;
      padding: 28px 32px;
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 28px;
      overflow: hidden;
      position: relative;
    }
    .welcome-banner::before {
      content: '';
      position: absolute; top: -40px; right: 180px;
      width: 180px; height: 180px;
      background: rgba(79,195,247,0.08);
      border-radius: 50%;
    }
    .welcome-banner::after {
      content: '';
      position: absolute; bottom: -60px; right: 60px;
      width: 220px; height: 220px;
      background: rgba(255,184,48,0.07);
      border-radius: 50%;
    }
    .welcome-text h3 { font-size: 13px; font-weight: 700; color: var(--sky); letter-spacing: .5px; margin-bottom: 6px; }
    .welcome-text h2 { font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 8px; }
    .welcome-text p { font-size: 14px; color: rgba(255,255,255,0.55); max-width: 380px; }
    .welcome-emoji { font-size: 72px; position: relative; z-index: 1; line-height: 1; }

    /* ── STAT CARDS ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 18px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: var(--card);
      border-radius: 18px;
      padding: 22px;
      display: flex; flex-direction: column;
      gap: 14px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.05);
      transition: transform .2s, box-shadow .2s;
      cursor: default;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.10); }
    .stat-top { display: flex; align-items: center; justify-content: space-between; }
    .stat-icon {
      width: 48px; height: 48px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px;
    }
    .stat-icon.sun    { background: #FFF3CD; }
    .stat-icon.sky    { background: #E0F4FD; }
    .stat-icon.grass  { background: #E8F5E9; }
    .stat-icon.rose   { background: #FCE4EC; }
    .stat-badge {
      font-size: 11px; font-weight: 800;
      padding: 4px 10px; border-radius: 20px;
    }
    .stat-badge.up   { background: #E8F5E9; color: var(--grass); }
    .stat-badge.down { background: #FCE4EC; color: var(--rose); }
    .stat-number { font-size: 36px; font-weight: 900; line-height: 1; }
    .stat-label  { font-size: 13px; color: var(--muted); font-weight: 600; }

    /* ── LOWER GRID ── */
    .lower-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .panel {
      background: var(--card);
      border-radius: 18px;
      padding: 22px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }
    .panel-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 18px;
    }
    .panel-title { font-size: 15px; font-weight: 800; }
    .panel-link  { font-size: 12px; color: var(--sky); font-weight: 700; text-decoration: none; }
    .panel-link:hover { text-decoration: underline; }

    /* Recent students */
    .student-row {
      display: flex; align-items: center; gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid #F0F4F8;
    }
    .student-row:last-child { border-bottom: none; }
    .s-avatar {
      width: 38px; height: 38px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; font-weight: 800; flex-shrink: 0;
    }
    .s-name  { font-size: 14px; font-weight: 700; }
    .s-class { font-size: 12px; color: var(--muted); }
    .s-badge {
      margin-left: auto;
      font-size: 11px; font-weight: 700;
      padding: 4px 10px; border-radius: 20px;
    }
    .s-badge.new    { background: #E0F4FD; color: var(--sky); }
    .s-badge.active { background: #E8F5E9; color: var(--grass); }

    /* Quick actions */
    .actions-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    .action-btn {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 8px;
      padding: 18px 12px;
      border-radius: 14px;
      border: 2px dashed #E0E8F0;
      cursor: pointer;
      text-decoration: none;
      transition: all .18s;
    }
    .action-btn:hover { border-style: solid; transform: translateY(-2px); }
    .action-btn .a-icon { font-size: 28px; }
    .action-btn .a-label { font-size: 12px; font-weight: 700; color: var(--text); text-align: center; }
    .action-btn.sun-btn:hover  { border-color: var(--sun);    background: #FFFBF0; }
    .action-btn.sky-btn:hover  { border-color: var(--sky);    background: #F0FBFF; }
    .action-btn.grass-btn:hover{ border-color: var(--grass);  background: #F1FBF1; }
    .action-btn.rose-btn:hover { border-color: var(--rose);   background: #FFF0F5; }

    /* Responsive tweak */
    @media (max-width: 1100px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main { margin-left: 0; }
      .lower-grid { grid-template-columns: 1fr; }
    }
  </style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🌟</div>
            <div>
                  <div class="brand-name">Little Stars</div>
                        <div class="brand-sub">PRE SCHOOL</div>
                            </div>
                              </div>

                                <nav class="nav">
                                    <div class="nav-label">Main</div>
                                        <a href="dashboard.php" class="active">
                                              <span class="icon">🏠</span> Dashboard
                                                  </a>

                                                      <div class="nav-label">Management</div>
                                                          <a href="students/index.php">
                                                                <span class="icon">👧</span> Students
                                                                    </a>
                                                                        <a href="teachers.php">
                                                                              <span class="icon">👩‍🏫</span> Teachers
                                                                                  </a>
                                                                                      <a href="classes.php">
                                                                                            <span class="icon">🏫</span> Classes
                                                                                                </a>
                                                                                                    <a href="activities.php"><span class="icon">🎯</span> Activities</a>
                                                                                                        <a href="parents.php">
                                                                                                              <span class="icon">👨‍👩‍👧</span> Parents
                                                                                                                  </a>
                                                                                                                      <a href="attendance.php">
                                                                                                                            <span class="icon">✅</span> Attendance
                                                                                                                                </a>
                                                                                                                                    <a href="payments.php">
                                                                                                                                          <span class="icon">💳</span> Payments
                                                                                                                                              </a>

                                                                                                                                                  <div class="nav-label">System</div>
                                                                                                                                                      <a href="reports.php">
                                                                                                                                                            <span class="icon">📊</span> Reports
                                                                                                                                                                </a>
                                                                                                                                                                    <a href="notifications.php">
                                                                                                                                                                          <span class="icon">🔔</span> Notification
                                                                                                                                                                              </a>
                                                                                                                                                                              <a href="gallery.php" class="<?= basename($_SERVER['PHP_SELF']) === 'gallery.php' ? 'active' : '' ?>">
  <span class="icon">🖼️</span> Gallery
</a>
                                                                                                                                                                                  <a href="settings.php">
                                                                                                                                                                                        <span class="icon">⚙️</span> Settings
                                                                                                                                                                                            </a>

                                                                                                                                                                                                <div class="nav-label">Account</div>
                                                                                                                                                                                                    <a href="logout.php" class="logout-link" onclick="return confirm('Are you sure you want to logout?');">
                                                                                                                                                                                                          <span class="icon">🚪</span> Logout
                                                                                                                                                                                                              </a>
                                                                                                                                                                                                                </nav>

                                                                                                                                                                                                                  <div class="sidebar-footer">
                                                                                                                                                                                                                      <div class="user-info">
                                                                                                                                                                                                                            <div class="avatar"><?= strtoupper(substr($user['email'] ?? 'A', 0, 1)) ?></div>
                                                                                                                                                                                                                                  <div>
                                                                                                                                                                                                                                          <div class="user-name"><?= htmlspecialchars($user['email'] ?? 'Admin') ?></div>
                                                                                                                                                                                                                                                  <div class="user-role">Administrator</div>
                                                                                                                                                                                                                                                        </div>
                                                                                                                                                                                                                                                              <a href="logout.php" class="logout-btn" title="Logout" onclick="return confirm('Are you sure you want to logout?');">↩</a>
                                                                                                                                                                                                                                                                  </div>
</div>
</aside>

<!-- ── MAIN ── -->
<div class="main">
  <!-- Topbar -->
  <div class="topbar">
    <div class="page-title">Good Morning, <span>Admin</span> 👋</div>
    <div class="topbar-right">
      <?php
$notif_result = mysqli_query($conn, "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
$unread_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE is_read = 0"));
$unread = $unread_count_row['cnt'];
?>
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <div style="position:relative; display:inline-block;">
    <a href="notificatios.php" style="position:relative; display:inline-block;">
    <button class="notif-btn">🔔
        <?php if($unread > 0): ?>
            <span style="position:absolute; top:-4px; right:-4px; background:red; 
                color:white; border-radius:50%; padding:1px 5px; 
                font-size:10px; font-weight:900;"><?= $unread ?></span>
        <?php endif; ?>
    </button>
</a>
        <?php if($unread > 0): ?>
            <span style="background:red; color:white; border-radius:50%; 
                padding:1px 5px; font-size:10px;"><?= $unread ?></span>
        <?php endif; ?>
    </button>
    <div id="notif-dropdown" style="display:none; position:absolute; right:0;
        background:white; border:1px solid #ddd; width:300px; max-height:350px;
        overflow-y:auto; border-radius:8px; 
        box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:9999;">
        <div style="padding:10px 15px; font-weight:bold; border-bottom:1px solid #eee;">
            Notifications
        </div>
        <?php while($n = mysqli_fetch_assoc($notif_result)): ?>
            <div style="padding:10px 15px; border-bottom:1px solid #eee;
                background:<?= $n['is_read'] ? '#fff' : '#f0f7ff' ?>;">
                <div><?= htmlspecialchars($n['message']) ?></div>
                <div style="font-size:11px; color:#999;"><?= $n['created_at'] ?></div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<script>
function toggleNotif() {
    const d = document.getElementById('notif-dropdown');
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
</script>
    </div>
  </div>

  <!-- Content -->
  <div class="content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="welcome-text">
        <h3>WELCOME BACK</h3>
        <h2>Little Stars Pre School</h2>
        <p>Here's what's happening at your school today. Have a wonderful day!</p>
      </div>
      <div class="welcome-emoji">🎒</div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon sun">👧</div>
          <span class="stat-badge up">▲ Active</span>
        </div>
        <div>
          <div class="stat-number"><?= $total_students ?></div>
          <div class="stat-label">Total Students</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon sky">👩‍🏫</div>
          <span class="stat-badge up">▲ Active</span>
        </div>
        <div>
          <div class="stat-number"><?= $total_teachers ?></div>
          <div class="stat-label">Total Teachers</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon grass">🏫</div>
          <span class="stat-badge up">▲ Running</span>
        </div>
        <div>
          <div class="stat-number"><?= $total_classes ?></div>
          <div class="stat-label">Total Classes</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-top">
          <div class="stat-icon rose">👨‍👩‍👧</div>
          <span class="stat-badge up">▲ Active</span>
        </div>
        <div>
          <div class="stat-number"><?= $total_parents ?></div>
          <div class="stat-label">Total Parents</div>
        </div>
      </div>
    </div>

    <!-- Lower Grid -->
    <div class="lower-grid">

      <!-- Recent Students -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">🌟 Recent Students</div>
          <a href="students.php" class="panel-link">View All →</a>
        </div>
        <?php
          $recent = mysqli_query($conn, "SELECT * FROM students ORDER BY st_id DESC LIMIT 5");
          if($recent && mysqli_num_rows($recent) > 0):
            $colors = ['#FFE0B2','#E0F4FD','#E8F5E9','#FCE4EC','#EDE7F6'];
            $emojis = ['🧒','👦','👧','🧒','👦'];
            $i = 0;
            while($s = mysqli_fetch_assoc($recent)):
              $col = $colors[$i % 5]; $em = $emojis[$i % 5];
        ?>
        <div class="student-row">
          <div class="s-avatar" style="background:<?= $col ?>"><?= $em ?></div>
          <div>
            <div class="s-name"><?= htmlspecialchars($s['name'] ?? $s['full_name'] ?? 'Student') ?></div>
            <div class="s-class">Class: <?= htmlspecialchars($s['class'] ?? $s['class_name'] ?? '—') ?></div>
          </div>
          <span class="s-badge new">New</span>
        </div>
        <?php $i++; endwhile; else: ?>
        <p style="color:var(--muted); font-size:14px; text-align:center; padding:20px 0;">
          No students added yet.<br>
          <a href="students.php" style="color:var(--sky);">Add your first student →</a>
        </p>
        <?php endif; ?>
      </div>

      <!-- Quick Actions -->
      <div class="panel">
        <div class="panel-header">
          <div class="panel-title">⚡ Quick Actions</div>
        </div>
        <div class="actions-grid">
          <a href="students/index.php?action=add" class="action-btn sun-btn">
            <span class="a-icon">➕</span>
            <span class="a-label">Add Student</span>
          </a>
          <a href="teachers.php?action=add" class="action-btn sky-btn">
            <span class="a-icon">👩‍🏫</span>
            <span class="a-label">Add Teacher</span>
          </a>
          <a href="attendance.php" class="action-btn grass-btn">
            <span class="a-icon">📋</span>
            <span class="a-label">Mark Attendance</span>
          </a>
          <a href="payments.php?action=add" class="action-btn rose-btn">
            <span class="a-icon">💳</span>
            <span class="a-label">Record Payment</span>
          </a>
        </div>
      </div>

    </div><!-- /lower-grid -->
  </div><!-- /content -->
</div><!-- /main -->

</body>
</html>

<?php
/*
=====================================================================
 Little Stars Pre School — Parent FAQ Chatbot Widget
=====================================================================
 HOW TO USE:
 1. Save this file as "chatbot-widget.php" inside your project
    (e.g. same folder as parents.php).
 2. Open any PUBLIC page (index.php, about.php, contact.php, etc.)
    and add this line right before the closing </body> tag:

        <?php include 'chatbot-widget.php'; ?>

 3. Done. A floating chat button will appear bottom-right on that
    page. No login required, no database needed — this is a
    simple rule-based (keyword matching) FAQ bot that runs
    entirely in the browser.

 TO EDIT THE ANSWERS:
   Scroll down to the "FAQ_DATA" JavaScript array below and edit
   the question/keywords/answer for each entry, or add new ones
   by copying an existing block.
=====================================================================
*/
?>
<!-- ============== LITTLE STARS FAQ CHATBOT WIDGET ============== -->
<style>
  :root{
    --ls-navy:#1b2338;
    --ls-navy-light:#252e47;
    --ls-orange:#e2622b;
    --ls-orange-light:#f4a15c;
    --ls-cream:#fdf8f2;
    --ls-text:#2b2b2b;
    --ls-muted:#8a8f9c;
  }

  #ls-chat-launcher{
    position:fixed;
    right:24px;
    bottom:24px;
    width:64px;
    height:64px;
    border-radius:50%;
    background:linear-gradient(145deg,var(--ls-orange-light),var(--ls-orange));
    box-shadow:0 8px 24px rgba(226,98,43,0.4), 0 2px 6px rgba(0,0,0,0.15);
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    z-index:99998;
    border:none;
    transition:transform .2s ease, box-shadow .2s ease;
  }
  #ls-chat-launcher:hover{ transform:translateY(-3px) scale(1.04); }
  #ls-chat-launcher svg{ width:28px; height:28px; }
  #ls-chat-launcher .ls-ping{
    position:absolute; top:-3px; right:-3px;
    width:16px; height:16px; border-radius:50%;
    background:#ff4d4f; border:2px solid var(--ls-cream);
  }

  #ls-chat-window{
    position:fixed;
    right:24px;
    bottom:100px;
    width:360px;
    max-width:92vw;
    height:520px;
    max-height:75vh;
    background:var(--ls-cream);
    border-radius:20px;
    box-shadow:0 20px 60px rgba(20,20,30,0.25);
    display:none;
    flex-direction:column;
    overflow:hidden;
    z-index:99999;
    font-family:'Segoe UI', system-ui, -apple-system, sans-serif;
  }
  #ls-chat-window.ls-open{ display:flex; animation:ls-rise .25s ease; }
  @keyframes ls-rise{ from{opacity:0; transform:translateY(16px);} to{opacity:1; transform:translateY(0);} }

  #ls-chat-header{
    background:var(--ls-navy);
    background-image:radial-gradient(circle at 90% -10%, var(--ls-navy-light), var(--ls-navy) 60%);
    color:#fff;
    padding:16px 18px;
    display:flex;
    align-items:center;
    gap:10px;
  }
  #ls-chat-header .ls-avatar{
    width:38px; height:38px; border-radius:12px;
    background:linear-gradient(145deg,var(--ls-orange-light),var(--ls-orange));
    display:flex; align-items:center; justify-content:center;
    font-size:20px; flex-shrink:0;
  }
  #ls-chat-header .ls-title{ font-weight:700; font-size:15px; line-height:1.2; }
  #ls-chat-header .ls-sub{ font-size:12px; color:#a9b0c3; display:flex; align-items:center; gap:5px; }
  #ls-chat-header .ls-dot{ width:7px; height:7px; border-radius:50%; background:#4ade80; display:inline-block; }
  #ls-chat-close{
    margin-left:auto; background:none; border:none; color:#c7cbd8;
    font-size:20px; cursor:pointer; line-height:1; padding:4px;
  }
  #ls-chat-close:hover{ color:#fff; }

  #ls-chat-body{
    flex:1;
    overflow-y:auto;
    padding:16px;
    display:flex;
    flex-direction:column;
    gap:10px;
    background:
      radial-gradient(circle at 15% 10%, rgba(226,98,43,0.05), transparent 40%),
      var(--ls-cream);
  }
  .ls-msg{ max-width:82%; padding:10px 13px; border-radius:14px; font-size:13.5px; line-height:1.45; }
  .ls-msg.bot{
    background:#fff; color:var(--ls-text);
    border:1px solid #eee2d6;
    align-self:flex-start;
    border-bottom-left-radius:4px;
    box-shadow:0 1px 2px rgba(0,0,0,0.03);
  }
  .ls-msg.user{
    background:var(--ls-navy); color:#fff;
    align-self:flex-end;
    border-bottom-right-radius:4px;
  }

  .ls-quick-wrap{ display:flex; flex-wrap:wrap; gap:6px; align-self:flex-start; max-width:100%; }
  .ls-quick-btn{
    background:#fff;
    border:1px solid var(--ls-orange);
    color:var(--ls-orange);
    padding:6px 11px;
    border-radius:20px;
    font-size:12.5px;
    cursor:pointer;
    transition:background .15s, color .15s;
    white-space:nowrap;
  }
  .ls-quick-btn:hover{ background:var(--ls-orange); color:#fff; }

  #ls-chat-input-row{
    display:flex; gap:8px; padding:12px;
    border-top:1px solid #eee2d6; background:#fff;
  }
  #ls-chat-input{
    flex:1; border:1px solid #e5ddd0; border-radius:22px;
    padding:10px 15px; font-size:13.5px; outline:none;
    background:var(--ls-cream);
  }
  #ls-chat-input:focus{ border-color:var(--ls-orange); }
  #ls-chat-send{
    width:40px; height:40px; border-radius:50%; border:none;
    background:var(--ls-orange); color:#fff; cursor:pointer;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  #ls-chat-send:hover{ background:#c94f1c; }

  #ls-chat-body::-webkit-scrollbar{ width:6px; }
  #ls-chat-body::-webkit-scrollbar-thumb{ background:#e5ddd0; border-radius:3px; }

  @media (max-width:480px){
    #ls-chat-window{ right:12px; left:12px; width:auto; bottom:90px; }
    #ls-chat-launcher{ right:16px; bottom:16px; }
  }
</style>

<button id="ls-chat-launcher" aria-label="Open FAQ chat">
  <span class="ls-ping"></span>
  <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
  </svg>
</button>

<div id="ls-chat-window">
  <div id="ls-chat-header">
    <div class="ls-avatar">⭐</div>
    <div>
      <div class="ls-title">Little Stars Help Center</div>
      <div class="ls-sub"><span class="ls-dot"></span> Instant answers for parents</div>
    </div>
    <button id="ls-chat-close" aria-label="Close chat">✕</button>
  </div>
  <div id="ls-chat-body"></div>
  <div id="ls-chat-input-row">
    <input id="ls-chat-input" type="text" placeholder="Type your question…" autocomplete="off" />
    <button id="ls-chat-send" aria-label="Send">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"></line>
        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
      </svg>
    </button>
  </div>
</div>

<script>
(function(){
  // ---------------------------------------------------------------
  // EDIT YOUR FAQ ANSWERS HERE
  // Each entry: question (shown as quick-reply), keywords (words that
  // trigger it when typed), answer (the bot's reply).
  // ---------------------------------------------------------------
  var FAQ_DATA = [
    {
      question: "How do I enroll my child?",
      keywords: ["enroll","enrol","admission","register","registration","join","apply"],
      answer: "To enroll your child, visit our office with your child's birth certificate and immunization record, or call us to schedule a visit. Our admissions team will guide you through the rest!"
    },
    {
      question: "What are the school hours?",
      keywords: ["hours","time","open","close","timing","schedule"],
      answer: "Little Stars is open Monday–Friday, 7:30 AM to 5:00 PM. Regular class hours are 8:30 AM to 1:00 PM, with extended daycare available until 5:00 PM."
    },
    {
      question: "How do I pay fees?",
      keywords: ["fee","fees","pay","payment","invoice","bill","cost","price"],
      answer: "Fees can be paid via bank transfer, card, or cash at the office. You can also check your payment history under the Payments section of the parent portal."
    },
    {
      question: "How can I check attendance?",
      keywords: ["attendance","absent","present","leave"],
      answer: "Daily attendance is recorded by teachers each morning. Parents can view attendance records through the parent portal, or ask your child's class teacher directly."
    },
    {
      question: "What activities does the school offer?",
      keywords: ["activity","activities","program","sports","art","music","play"],
      answer: "We offer a mix of art, music, storytelling, outdoor play, and early-learning activities designed for each age group. Check the Activities section for the current term's schedule."
    },
    {
      question: "How do I contact a teacher?",
      keywords: ["teacher","contact","talk","meet","reach","call"],
      answer: "You can reach your child's teacher through the front office, or leave a message via the parent portal and the teacher will get back to you within a school day."
    },
    {
      question: "What are the holidays this term?",
      keywords: ["holiday","holidays","break","vacation","closed"],
      answer: "Our holiday calendar is posted on the notice board and shared with parents at the start of each term. Please check with the office for the most current list of upcoming holidays."
    }
  ];

  var FALLBACK = "I don't have an answer for that just yet. Please contact the school office directly and our staff will be happy to help!";
  var GREETING = "Hi there! 👋 I'm the Little Stars Help Desk. Ask me about admissions, fees, hours, attendance, or activities — or tap a question below.";

  var launcher = document.getElementById('ls-chat-launcher');
  var win = document.getElementById('ls-chat-window');
  var closeBtn = document.getElementById('ls-chat-close');
  var body = document.getElementById('ls-chat-body');
  var input = document.getElementById('ls-chat-input');
  var sendBtn = document.getElementById('ls-chat-send');
  var started = false;

  function addMessage(text, sender){
    var el = document.createElement('div');
    el.className = 'ls-msg ' + sender;
    el.textContent = text;
    body.appendChild(el);
    body.scrollTop = body.scrollHeight;
  }

  function addQuickReplies(){
    var wrap = document.createElement('div');
    wrap.className = 'ls-quick-wrap';
    FAQ_DATA.forEach(function(item){
      var btn = document.createElement('button');
      btn.className = 'ls-quick-btn';
      btn.textContent = item.question;
      btn.onclick = function(){ handleUserMessage(item.question); };
      wrap.appendChild(btn);
    });
    body.appendChild(wrap);
    body.scrollTop = body.scrollHeight;
  }

  function findAnswer(text){
    var lower = text.toLowerCase();
    for (var i = 0; i < FAQ_DATA.length; i++){
      var item = FAQ_DATA[i];
      for (var j = 0; j < item.keywords.length; j++){
        if (lower.indexOf(item.keywords[j]) !== -1){
          return item.answer;
        }
      }
    }
    return FALLBACK;
  }

  function handleUserMessage(text){
    text = text.trim();
    if (!text) return;
    addMessage(text, 'user');
    input.value = '';
    setTimeout(function(){
      addMessage(findAnswer(text), 'bot');
    }, 350);
  }

  launcher.addEventListener('click', function(){
    win.classList.add('ls-open');
    if (!started){
      started = true;
      addMessage(GREETING, 'bot');
      addQuickReplies();
    }
    input.focus();
  });

  closeBtn.addEventListener('click', function(){
    win.classList.remove('ls-open');
  });

  sendBtn.addEventListener('click', function(){ handleUserMessage(input.value); });
  input.addEventListener('keydown', function(e){
    if (e.key === 'Enter') handleUserMessage(input.value);
  });
})();
</script>
<!-- ============ END LITTLE STARS FAQ CHATBOT WIDGET ============ -->
