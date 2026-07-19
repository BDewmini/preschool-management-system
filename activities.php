<?php
session_start();
include 'db.php';

$user   = $_SESSION['user'] ?? null;
$success = $error = '';
$action  = $_GET['action'] ?? 'list';
$edit_activity = $profile_activity = null;

// ── ADD ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $activity_name = $conn->real_escape_string($_POST['activity_name']);
    $activity_date = $conn->real_escape_string($_POST['activity_date']);
    $activity_time = $conn->real_escape_string($_POST['activity_time']);
    $activity_type = $conn->real_escape_string($_POST['activity_type']);
    $description   = $conn->real_escape_string($_POST['description']);
    $status        = $conn->real_escape_string($_POST['status']);
    $sql = "INSERT INTO activities (activity_name,activity_date,activity_time,activity_type,description,status)
            VALUES ('$activity_name','$activity_date','$activity_time','$activity_type','$description','$status')";
    $conn->query($sql) ? $success = "Activity added!" : $error = $conn->error;
    $action = 'list';
}

// ── EDIT SAVE ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_activity'])) {
    $id            = (int)$_POST['id'];
    $activity_name = $conn->real_escape_string($_POST['activity_name']);
    $activity_date = $conn->real_escape_string($_POST['activity_date']);
    $activity_time = $conn->real_escape_string($_POST['activity_time']);
    $activity_type = $conn->real_escape_string($_POST['activity_type']);
    $description   = $conn->real_escape_string($_POST['description']);
    $status        = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE activities SET activity_name='$activity_name',activity_date='$activity_date',
        activity_time='$activity_time',activity_type='$activity_type',
        description='$description',status='$status' WHERE activity_id=$id")
        ? $success = "Activity updated!" : $error = $conn->error;
    $action = 'list';
}

// ── DELETE ───────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM activities WHERE activity_id=$id")
        ? $success = "Activity deleted." : $error = $conn->error;
    $action = 'list';
}

// ── LOAD FOR EDIT ────────────────────────────────────
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $edit_activity = $conn->query("SELECT * FROM activities WHERE activity_id=$id")->fetch_assoc();
}

// ── PROFILE VIEW ─────────────────────────────────────
if ($action === 'profile' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $profile_activity = $conn->query("SELECT * FROM activities WHERE activity_id=$id")->fetch_assoc();
}

// ── SAVE PROGRESS ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_progress'])) {
    $act_id    = (int)$_POST['activity_id'];
    $students  = $_POST['st_id']  ?? [];
    $progresses= $_POST['progress']    ?? [];
    $remarks_arr = $_POST['remarks']   ?? [];

    foreach ($students as $i => $sid) {
        $sid      = (int)$sid;
        $students = $_POST['st_id'] ?? [];  
        $progress = $conn->real_escape_string($progresses[$i] ?? 'Good');
        $remark   = $conn->real_escape_string($remarks_arr[$i] ?? '');
            $conn->query("INSERT INTO activity_participants (activity_id, student_id, progress, remarks)
              VALUES ($act_id, $sid, '$progress', '$remark')
              ON DUPLICATE KEY UPDATE progress='$progress', remarks='$remark'");
    }
    $success = "Progress saved successfully!";
    $action  = 'progress';
    $_GET['id'] = $act_id;
}

// ── PROGRESS PAGE DATA ───────────────────────────────
$progress_activity   = null;
$participants        = [];
$all_students        = [];

if ($action === 'progress' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $progress_activity = $conn->query("SELECT * FROM activities WHERE activity_id=$id")->fetch_assoc();

    // Already saved participants
    $res = $conn->query("SELECT a.activity_id, a.st_id,
                            s.name, s.st_id as sid,
                            s.class_id, c.class_name
                     FROM activities a
                     LEFT JOIN students s ON a.st_id = s.st_id
                     LEFT JOIN classes c ON s.class_id = c.class_id
                     WHERE a.activity_id = $id
                     ORDER BY s.name");
    while ($row = $res->fetch_assoc()) $participants[] = $row;

    // All students for assign form
    $res2 = $conn->query("SELECT s.st_id, s.name, c.class_name
                          FROM students s
                          LEFT JOIN classes c ON s.class_id = c.class_id
                          WHERE s.status='active'
                          ORDER BY c.class_name, s.name");
    while ($row = $res2->fetch_assoc()) $all_students[] = $row;
}

// ── MONTHLY REPORT DATA ──────────────────────────────
$report_data   = [];
$report_month  = $_GET['month'] ?? date('Y-m');
$report_year   = substr($report_month, 0, 4);
$report_mon    = substr($report_month, 5, 2);

if ($action === 'report') {
    $res = $conn->query("
        SELECT s.st_id, name, c.class_name,
               COUNT(ap.ac_id)                                             AS total_activities,
               SUM(ap.progress = 'Excellent')                          AS excellent,
               SUM(ap.progress = 'Good')                               AS good,
               SUM(ap.progress = 'Needs Improvement')                  AS needs_improvement,
               SUM(ap.progress = 'Absent')                             AS absent,
               GROUP_CONCAT(
                   CONCAT(a.activity_name,'|',ap.progress)
                   ORDER BY a.activity_date SEPARATOR ';;'
               ) AS activity_list
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN activity_participants ap ON ap.st_id = s.st_id
        LEFT JOIN activities a ON a.activity_id = ap.activity_id
            AND YEAR(a.activity_date)  = '$report_year'
            AND MONTH(a.activity_date) = '$report_mon'
        WHERE s.status = 'active'
        GROUP BY s.st_id
        ORDER BY c.class_name, s.name
    ");
    while ($row = $res->fetch_assoc()) $report_data[] = $row;
}

// ── ACTIVITY LIST ────────────────────────────────────
$search = $conn->real_escape_string($_GET['search'] ?? '');
$where  = $search ? "WHERE activity_name LIKE '%$search%' OR activity_type LIKE '%$search%'" : '';
$activities = $conn->query("SELECT * FROM activities $where ORDER BY activity_date ASC");
$total      = $conn->query("SELECT COUNT(*) c FROM activities")->fetch_assoc()['c'];
$upcoming   = $conn->query("SELECT COUNT(*) c FROM activities WHERE status='Upcoming'")->fetch_assoc()['c'];
$ongoing    = $conn->query("SELECT COUNT(*) c FROM activities WHERE status='Ongoing'")->fetch_assoc()['c'];
$completed  = $conn->query("SELECT COUNT(*) c FROM activities WHERE status='Completed'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activities – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
:root {
  --sun:#FFB830; --sky:#4FC3F7; --grass:#66BB6A;
  --rose:#F06292; --purple:#9575CD;
  --bg:#F0F7FF; --card:#FFFFFF; --text:#2D3A4A; --muted:#8A9BB0;
  --sidebar-w:240px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}

/* SIDEBAR */
.sidebar{width:var(--sidebar-w);background:linear-gradient(160deg,#1a2a4a,#243756);min-height:100vh;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100;}
.sidebar-brand{padding:28px 24px 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.08);}
.brand-icon{width:42px;height:42px;background:var(--sun);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;}
.brand-name{font-family:'Fredoka One',cursive;font-size:20px;color:#fff;line-height:1.1;}
.brand-sub{font-size:11px;color:rgba(255,255,255,.45);letter-spacing:.5px;}
.nav{padding:20px 12px;flex:1;}
.nav-label{font-size:10px;font-weight:800;letter-spacing:1.5px;color:rgba(255,255,255,.3);text-transform:uppercase;padding:0 12px;margin:16px 0 6px;}
.nav a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;color:rgba(255,255,255,.65);text-decoration:none;font-size:14px;font-weight:600;transition:all .18s;margin-bottom:2px;}
.nav a:hover{background:rgba(255,255,255,.1);color:#fff;}
.nav a.active{background:rgba(255,184,48,.18);color:var(--sun);}
.nav a .icon{font-size:18px;width:22px;text-align:center;}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
.user-info{display:flex;align-items:center;gap:10px;}
.avatar{width:36px;height:36px;border-radius:50%;background:var(--rose);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:15px;}
.user-name{font-size:13px;font-weight:700;color:#fff;}
.user-role{font-size:11px;color:rgba(255,255,255,.4);}
.logout-btn{margin-left:auto;background:rgba(240,98,146,.2);border:none;border-radius:8px;padding:6px 10px;cursor:pointer;color:var(--rose);font-size:18px;text-decoration:none;display:flex;align-items:center;}
.logout-btn:hover{background:rgba(240,98,146,.4);}

/* MAIN */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;}
.topbar{background:var(--card);padding:18px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #E8EEF5;position:sticky;top:0;z-index:50;}
.page-title{font-size:22px;font-weight:800;}
.page-title span{color:var(--sun);}
.topbar-right{display:flex;align-items:center;gap:10px;}
.date-badge{background:var(--bg);border-radius:20px;padding:6px 14px;font-size:13px;font-weight:600;color:var(--muted);}

/* BUTTONS */
.btn{padding:9px 20px;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer;border:none;font-family:'Nunito',sans-serif;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .18s;}
.btn-primary{background:var(--sun);color:#fff;}
.btn-primary:hover{background:#e6a520;}
.btn-success{background:var(--grass);color:#fff;}
.btn-success:hover{background:#4caa50;}
.btn-purple{background:var(--purple);color:#fff;}
.btn-purple:hover{background:#7b5fb5;}
.btn-danger{background:#FCE4EC;color:var(--rose);}
.btn-danger:hover{background:var(--rose);color:#fff;}
.btn-edit{background:#F0F7FF;color:var(--sky);}
.btn-edit:hover{background:var(--sky);color:#fff;}
.btn-sm{padding:6px 14px;font-size:12px;}
.btn-back{background:#F0F4F8;color:var(--muted);}
.btn-back:hover{background:#dde6f0;}

/* CONTENT */
.content{padding:28px 32px;flex:1;}
.alert-success{background:#E8F5E9;color:#388E3C;border-radius:10px;padding:12px 18px;font-weight:700;margin-bottom:16px;}
.alert-error{background:#FCE4EC;color:#C62828;border-radius:10px;padding:12px 18px;font-weight:700;margin-bottom:16px;}

/* STATS */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:var(--card);border-radius:16px;padding:20px 24px;box-shadow:0 2px 12px rgba(0,0,0,.05);display:flex;align-items:center;gap:16px;transition:transform .2s;}
.stat-card:hover{transform:translateY(-3px);}
.stat-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;}
.stat-num{font-size:26px;font-weight:900;line-height:1;}
.stat-label{font-size:12px;color:var(--muted);font-weight:700;margin-top:2px;}

/* ACTIVITY CARDS */
.activities-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-bottom:24px;}
.activity-card{background:var(--card);border-radius:18px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden;}
.activity-card:hover{transform:translateY(-4px);box-shadow:0 8px 28px rgba(0,0,0,.10);}
.activity-card-accent{position:absolute;top:0;left:0;right:0;height:5px;border-radius:18px 18px 0 0;}
.activity-card-icon{width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:14px;}
.activity-card-name{font-size:18px;font-weight:900;margin-bottom:4px;}
.activity-card-type{font-size:13px;color:var(--muted);font-weight:600;margin-bottom:14px;}
.activity-meta{display:flex;flex-direction:column;gap:6px;margin-bottom:16px;}
.meta-row{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--text);}
.meta-row span.lbl{color:var(--muted);font-size:12px;min-width:60px;}
.activity-card-actions{display:flex;gap:8px;margin-top:10px;}

/* BADGE */
.badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:800;}
.badge-upcoming{background:#E0F4FD;color:#0277BD;}
.badge-ongoing{background:#FFF3CD;color:#F57F17;}
.badge-completed{background:#E8F5E9;color:#2E7D32;}
.badge-cancelled{background:#FCE4EC;color:#C62828;}
.badge-excellent{background:#E8F5E9;color:#2E7D32;}
.badge-good{background:#E0F4FD;color:#0277BD;}
.badge-needs{background:#FFF3CD;color:#E65100;}
.badge-absent{background:#FCE4EC;color:#C62828;}

/* CARD */
.card{background:var(--card);border-radius:18px;padding:24px 28px;margin-bottom:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.card-title{font-size:16px;font-weight:800;}
.search-box{padding:9px 16px;border-radius:10px;border:1.5px solid #E0E8F0;font-family:'Nunito',sans-serif;font-size:14px;outline:none;width:260px;transition:border .18s;}
.search-box:focus{border-color:var(--sun);}

/* FORM */
.form-card{background:var(--card);border-radius:18px;padding:28px 32px;margin-bottom:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);}
.form-title{font-size:18px;font-weight:900;margin-bottom:20px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group.full{grid-column:1/-1;}
.form-group label{font-size:13px;font-weight:700;color:var(--muted);}
.form-group input,.form-group select,.form-group textarea{padding:10px 14px;border-radius:10px;border:1.5px solid #E0E8F0;font-family:'Nunito',sans-serif;font-size:14px;outline:none;transition:border .18s;color:var(--text);background:#fff;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--sun);}
.form-group textarea{resize:vertical;min-height:70px;}
.form-actions{display:flex;gap:12px;margin-top:20px;}

/* PROGRESS TABLE */
.progress-table{width:100%;border-collapse:collapse;}
.progress-table th{background:var(--bg);padding:12px 16px;text-align:left;font-size:12px;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.progress-table td{padding:12px 16px;border-bottom:1px solid #F0F4F8;vertical-align:middle;}
.progress-table tr:hover td{background:#FAFCFF;}
.progress-table select{padding:7px 12px;border-radius:8px;border:1.5px solid #E0E8F0;font-family:'Nunito',sans-serif;font-size:13px;font-weight:700;outline:none;}
.progress-table input[type=text]{padding:7px 12px;border-radius:8px;border:1.5px solid #E0E8F0;font-family:'Nunito',sans-serif;font-size:13px;width:100%;outline:none;}
.progress-select option[value="Excellent"]{color:#2E7D32;}
.progress-select option[value="Good"]{color:#0277BD;}
.progress-select option[value="Needs Improvement"]{color:#E65100;}
.progress-select option[value="Absent"]{color:#C62828;}

/* PROFILE */
.profile-header{display:flex;align-items:flex-start;gap:24px;background:var(--card);border-radius:18px;padding:28px 32px;margin-bottom:20px;box-shadow:0 2px 12px rgba(0,0,0,.05);}
.profile-icon{width:90px;height:90px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:40px;flex-shrink:0;}
.profile-name{font-size:24px;font-weight:900;}
.profile-sub{color:var(--muted);font-size:14px;margin-top:4px;}
.info-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:18px;}
.info-item{display:flex;flex-direction:column;gap:3px;}
.info-item .lbl{font-size:11px;color:var(--muted);font-weight:800;letter-spacing:.5px;}
.info-item .val{font-size:15px;font-weight:800;}
.desc-block{background:var(--bg);border-radius:12px;padding:16px 20px;margin-top:16px;font-size:14px;line-height:1.7;}

/* MONTHLY REPORT */
.report-table{width:100%;border-collapse:collapse;font-size:13px;}
.report-table th{background:linear-gradient(135deg,#1a2a4a,#243756);color:#fff;padding:13px 16px;text-align:left;font-size:12px;font-weight:800;letter-spacing:.5px;}
.report-table th:first-child{border-radius:12px 0 0 0;}
.report-table th:last-child{border-radius:0 12px 0 0;}
.report-table td{padding:12px 16px;border-bottom:1px solid #F0F4F8;vertical-align:middle;}
.report-table tr:hover td{background:#FAFCFF;}
.report-table tr:last-child td{border-bottom:none;}
.progress-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;margin:2px;}
.pill-excellent{background:#E8F5E9;color:#2E7D32;}
.pill-good{background:#E0F4FD;color:#0277BD;}
.pill-needs{background:#FFF3CD;color:#E65100;}
.pill-absent{background:#FCE4EC;color:#C62828;}
.score-bar-wrap{background:#F0F4F8;border-radius:20px;height:8px;width:100px;display:inline-block;vertical-align:middle;margin-left:8px;}
.score-bar{height:8px;border-radius:20px;display:block;}

/* PRINT */
@media print {
  .sidebar,.topbar,.no-print{display:none!important;}
  .main{margin-left:0;}
  .content{padding:10px;}
  .report-table th{background:#1a2a4a!important;-webkit-print-color-adjust:exact;}
}

/* EMPTY */
.empty-state{text-align:center;padding:50px 20px;}
.empty-state .ei{font-size:52px;margin-bottom:12px;}
.empty-state p{color:var(--muted);font-weight:600;}
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<!-- SIDEBAR -->
<?php include 'sidebar.php'; ?>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">🎯 <span>Activities</span></div>
    <div class="topbar-right">
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <?php if ($action === 'list'): ?>
        <a href="?action=report" class="btn btn-purple no-print">📊 Monthly Report</a>
        <a href="?action=add"    class="btn btn-primary no-print">➕ Add Activity</a>
      <?php else: ?>
        <a href="?action=list" class="btn btn-back no-print">← Back to List</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">

    <?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert-error">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- ══ LIST ══ -->
    <?php if ($action === 'list'): ?>
      <div class="stat-row">
        <div class="stat-card">
          <div class="stat-icon" style="background:#FFF3CD;">🎯</div>
          <div><div class="stat-num" style="color:var(--sun)"><?= $total ?></div><div class="stat-label">Total Activities</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#E0F4FD;">📅</div>
          <div><div class="stat-num" style="color:var(--sky)"><?= $upcoming ?></div><div class="stat-label">Upcoming</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#FFF3CD;">⏳</div>
          <div><div class="stat-num" style="color:#F57F17"><?= $ongoing ?></div><div class="stat-label">Ongoing</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E9;">✅</div>
          <div><div class="stat-num" style="color:var(--grass)"><?= $completed ?></div><div class="stat-label">Completed</div></div>
        </div>
      </div>

      <div class="card" style="padding:16px 20px;margin-bottom:20px;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
          <input type="hidden" name="action" value="list">
          <input type="text" name="search" class="search-box" placeholder="🔍 Search activities..."
                 value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-primary btn-sm">Search</button>
          <?php if ($search): ?><a href="?action=list" class="btn btn-sm btn-back">Clear</a><?php endif; ?>
        </form>
      </div>

      <?php
      $type_icons = [
        'Arts'=>['🎨','#F06292','#FCE4EC'], 'Cognitive'=>['🧠','#9575CD','#EDE7F6'],
        'Physical'=>['🏃','#66BB6A','#E8F5E9'], 'Nature'=>['🌿','#26C6DA','#E0F7FA'],
        'Event'=>['🎉','#FFB830','#FFF3CD'], 'Music'=>['🎵','#4FC3F7','#E0F4FD'],
        'Story'=>['📖','#FF8A65','#FBE9E7'], 'Science'=>['🔬','#7986CB','#E8EAF6'],
      ];
      $default_type = ['🎯','#8A9BB0','#F0F4F8'];
      ?>

      <?php if ($activities && $activities->num_rows > 0): ?>
      <div class="activities-grid">
        <?php while ($a = $activities->fetch_assoc()):
          $tc = $type_icons[$a['activity_type']] ?? $default_type;
          $sc = match(strtolower($a['status'])) {
            'upcoming'=>'badge-upcoming','ongoing'=>'badge-ongoing',
            'completed'=>'badge-completed','cancelled'=>'badge-cancelled',default=>'badge-upcoming'
          };
          // Participant count
        $pcnt_result = $conn->query("SELECT COUNT(*) c FROM activity_participants WHERE activity_id={$a['activity_id']}");
$pcnt = $pcnt_result->fetch_assoc()['c'];
        ?>
        <div class="activity-card">
          <div class="activity-card-accent" style="background:<?= $tc[1] ?>;"></div>
          <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div class="activity-card-icon" style="background:<?= $tc[2] ?>;"><?= $tc[0] ?></div>
            <span class="badge <?= $sc ?>"><?= htmlspecialchars($a['status']) ?></span>
          </div>
          <div class="activity-card-name"><?= htmlspecialchars($a['activity_name']) ?></div>
          <div class="activity-card-type">🏷️ <?= htmlspecialchars($a['activity_type']) ?></div>
          <div class="activity-meta">
            <div class="meta-row"><span class="lbl">📅 Date</span><?= date('d M Y', strtotime($a['activity_date'])) ?></div>
            <div class="meta-row"><span class="lbl">⏰ Time</span><?= date('h:i A', strtotime($a['activity_time'])) ?></div>
            <div class="meta-row"><span class="lbl">👧 Students</span><?= $pcnt > 0 ? "<span style='color:var(--grass);font-weight:800;'>$pcnt recorded</span>" : '<span style="color:var(--muted);">Not recorded yet</span>' ?></div>
          </div>
          <div class="activity-card-actions">
            <a href="?action=progress&id=<?= $a['activity_id'] ?>" class="btn btn-success btn-sm" style="flex:1;justify-content:center;">📋 Progress</a>
            <a href="?action=edit&id=<?= $a['activity_id'] ?>"    class="btn btn-edit btn-sm"    style="flex:1;justify-content:center;">✏️ Edit</a>
            <a href="?action=delete&id=<?= $a['activity_id'] ?>"  class="btn btn-danger btn-sm"
               onclick="return confirm('Delete <?= htmlspecialchars($a['activity_name'], ENT_QUOTES) ?>?')">🗑</a>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="ei">🎯</div>
        <p>No activities found. <a href="?action=add" style="color:var(--sun);">Add one!</a></p>
      </div>
      <?php endif; ?>

    <!-- ══ ADD ══ -->
    <?php elseif ($action === 'add'): ?>
      <div class="form-card">
        <div class="form-title">➕ Add New Activity</div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group full">
              <label>Activity Name *</label>
              <input type="text" name="activity_name" placeholder="e.g. Painting Class, Story Time" required>
            </div>
            <div class="form-group">
              <label>Activity Date *</label>
              <input type="date" name="activity_date" required>
            </div>
            <div class="form-group">
              <label>Activity Time *</label>
              <input type="time" name="activity_time" required>
            </div>
            <div class="form-group">
              <label>Activity Type *</label>
              <select name="activity_type" required>
                <option value="">-- Select Type --</option>
                <?php foreach (['Arts'=>'🎨','Cognitive'=>'🧠','Physical'=>'🏃','Nature'=>'🌿','Event'=>'🎉','Music'=>'🎵','Story'=>'📖','Science'=>'🔬'] as $t=>$e): ?>
                <option value="<?= $t ?>"><?= $e ?> <?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Status *</label>
              <select name="status" required>
                <option value="Upcoming">📅 Upcoming</option>
                <option value="Ongoing">⏳ Ongoing</option>
                <option value="Completed">✅ Completed</option>
                <option value="Cancelled">❌ Cancelled</option>
              </select>
            </div>
            <div class="form-group full">
              <label>Description</label>
              <textarea name="description" placeholder="Brief description..."></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="add_activity" class="btn btn-primary">➕ Add Activity</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══ EDIT ══ -->
    <?php elseif ($action === 'edit' && $edit_activity): ?>
      <div class="form-card">
        <div class="form-title">✏️ Edit Activity</div>
        <form method="POST">
          <input type="hidden" name="id" value="<?= $edit_activity['activity_id'] ?>">
          <div class="form-grid">
            <div class="form-group full">
              <label>Activity Name *</label>
              <input type="text" name="activity_name" value="<?= htmlspecialchars($edit_activity['activity_name']) ?>" required>
            </div>
            <div class="form-group">
              <label>Activity Date *</label>
              <input type="date" name="activity_date" value="<?= $edit_activity['activity_date'] ?>" required>
            </div>
            <div class="form-group">
              <label>Activity Time *</label>
              <input type="time" name="activity_time" value="<?= $edit_activity['activity_time'] ?>" required>
            </div>
            <div class="form-group">
              <label>Activity Type *</label>
              <select name="activity_type" required>
                <?php foreach (['Arts','Cognitive','Physical','Nature','Event','Music','Story','Science'] as $t): ?>
                <option value="<?= $t ?>" <?= $edit_activity['activity_type']===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Status *</label>
              <select name="status" required>
                <?php foreach (['Upcoming','Ongoing','Completed','Cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $edit_activity['status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full">
              <label>Description</label>
              <textarea name="description"><?= htmlspecialchars($edit_activity['description'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="edit_activity" class="btn btn-primary">💾 Save Changes</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══ PROGRESS ══ -->
    <?php elseif ($action === 'progress' && $progress_activity): ?>
      <?php
      $default_type = ['🎯', '#8A9BB0', '#F0F4F8'];
        $tc = $type_icons[$progress_activity['activity_type']] ?? $default_type;
        $sc = match(strtolower($progress_activity['status'])) {
          'upcoming'=>'badge-upcoming','ongoing'=>'badge-ongoing',
          'completed'=>'badge-completed','cancelled'=>'badge-cancelled',default=>'badge-upcoming'
        };
        // Build map of existing participant data
        $existing = [];
        foreach ($participants as $p) $existing[$p['sid']] = $p;
      ?>

      <!-- Activity Info Banner -->
      <div class="card" style="display:flex;align-items:center;gap:20px;padding:20px 28px;margin-bottom:20px;">
        <div style="width:60px;height:60px;border-radius:16px;background:<?= $tc[2] ?>;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0;"><?= $tc[0] ?></div>
        <div style="flex:1;">
          <div style="font-size:20px;font-weight:900;"><?= htmlspecialchars($progress_activity['activity_name']) ?></div>
          <div style="color:var(--muted);font-size:13px;margin-top:4px;">
            📅 <?= date('d M Y', strtotime($progress_activity['activity_date'])) ?>
            &nbsp;·&nbsp; ⏰ <?= date('h:i A', strtotime($progress_activity['activity_time'])) ?>
            &nbsp;·&nbsp; <span class="badge <?= $sc ?>"><?= htmlspecialchars($progress_activity['status']) ?></span>
          </div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:28px;font-weight:900;color:var(--grass);"><?= count($participants) ?></div>
          <div style="font-size:12px;color:var(--muted);font-weight:700;">Students Recorded</div>
        </div>
      </div>

      <!-- Progress Form -->
      <div class="form-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
          <div class="form-title" style="margin:0;">📋 Record Student Progress</div>
          <div style="font-size:13px;color:var(--muted);font-weight:600;">
            Select students → assign progress → Save
          </div>
        </div>

        <form method="POST">
          <input type="hidden" name="activity_id" value="<?= $progress_activity['activity_id'] ?>">

          <div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;" class="no-print">
            <button type="button" class="btn btn-sm btn-edit" onclick="selectAll()">☑️ Select All</button>
            <button type="button" class="btn btn-sm btn-back" onclick="deselectAll()">⬜ Deselect All</button>
            <button type="button" class="btn btn-sm" style="background:#E8F5E9;color:#2E7D32;" onclick="setAll('Excellent')">🌟 All Excellent</button>
            <button type="button" class="btn btn-sm" style="background:#E0F4FD;color:#0277BD;" onclick="setAll('Good')">👍 All Good</button>
          </div>

          <div style="overflow-x:auto;">
          <table class="progress-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" id="chk-all" onclick="toggleAll(this)" style="cursor:pointer;width:16px;height:16px;"></th>
                <th>#</th>
                <th>Student Name</th>
                <th>Class</th>
                <th>Progress</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_students as $i => $s):
                $saved = $existing[$s['st_id']] ?? null;
                $checked = $saved ? 'checked' : '';
                $prog = $saved['progress'] ?? 'Good';
                $rem  = $saved['remarks']  ?? '';
              ?>
              <tr id="row-<?= $s['st_id'] ?>" style="<?= $saved ? '' : 'opacity:.65' ?>">
                <td>
                  <input type="checkbox" class="row-chk"
                         onchange="toggleRow(this, <?= $s['st_id'] ?>)"
                         <?= $checked ?>
                         style="cursor:pointer;width:16px;height:16px;">
                  <input type="hidden" name="student_id[]"
                         value="<?= $s['st_id'] ?>"
                         id="sid-<?= $s['st_id'] ?>" <?= $checked ? '' : 'disabled' ?>>
                </td>
                <td style="color:var(--muted);font-weight:700;"><?= $i+1 ?></td>
                <td>
                  <div style="font-weight:800;"><?= htmlspecialchars($s['name']) ?></div>
                </td>
                <td>
                  <span style="background:#F0F4F8;color:var(--muted);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                    <?= htmlspecialchars($s['class_name'] ?? 'N/A') ?>
                  </span>
                </td>
                <td>
                  <select name="progress[]" class="progress-select"
                          id="prog-<?= $s['st_id'] ?>"
                          style="color:<?= match($prog){ 'Excellent'=>'#2E7D32','Good'=>'#0277BD','Needs Improvement'=>'#E65100','Absent'=>'#C62828',default=>'var(--text)' } ?>;"
                          onchange="this.style.color=getColor(this.value)"
                          <?= $saved ? '' : 'disabled' ?>>
                    <option value="Excellent"         <?= $prog==='Excellent'         ?'selected':'' ?>>🌟 Excellent</option>
                    <option value="Good"              <?= $prog==='Good'              ?'selected':'' ?>>👍 Good</option>
                    <option value="Needs Improvement" <?= $prog==='Needs Improvement' ?'selected':'' ?>>📈 Needs Improvement</option>
                    <option value="Absent"            <?= $prog==='Absent'            ?'selected':'' ?>>❌ Absent</option>
                  </select>
                </td>
                <td>
                  <input type="text" name="remarks[]"
                         id="rem-<?= $s['st_id'] ?>"
                         placeholder="Optional remark..."
                         value="<?= htmlspecialchars($rem) ?>"
                         <?= $saved ? '' : 'disabled' ?>>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>

          <div class="form-actions">
            <button type="submit" name="save_progress" class="btn btn-success">💾 Save Progress</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══ MONTHLY REPORT ══ -->
    <?php elseif ($action === 'report'): ?>

      <!-- Report Header -->
      <div class="card no-print" style="padding:20px 28px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
          <div>
            <div style="font-size:18px;font-weight:900;">📊 Monthly Activity Progress Report</div>
            <div style="color:var(--muted);font-size:13px;margin-top:4px;">Student-wise activity performance summary</div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:8px;align-items:center;">
              <input type="hidden" name="action" value="report">
              <input type="month" name="month" value="<?= $report_month ?>"
                     style="padding:9px 14px;border-radius:10px;border:1.5px solid #E0E8F0;font-family:'Nunito',sans-serif;font-size:14px;outline:none;">
              <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
            </form>
            <button onclick="window.print()" class="btn btn-purple btn-sm">🖨️ Print</button>
          </div>
        </div>
      </div>

      <!-- Print Header (print only) -->
      <div style="display:none;" class="print-only">
        <div style="text-align:center;margin-bottom:20px;">
          <div style="font-size:22px;font-weight:900;">🌟 Little Stars Pre School</div>
          <div style="font-size:16px;font-weight:700;margin-top:4px;">Monthly Activity Progress Report</div>
          <div style="font-size:13px;color:#666;margin-top:4px;">
            <?= date('F Y', mktime(0,0,0,(int)$report_mon,1,(int)$report_year)) ?>
            &nbsp;·&nbsp; Generated: <?= date('d M Y h:i A') ?>
          </div>
        </div>
      </div>

      <!-- Summary Stats -->
      <?php
        $total_students  = count($report_data);
        $active_students = array_filter($report_data, fn($r) => $r['total_activities'] > 0);
        $avg_excellent   = $total_students > 0
            ? round(array_sum(array_column($report_data,'excellent')) / max(array_sum(array_column($report_data,'total_activities')),1) * 100)
            : 0;
      ?>
      <div class="stat-row" style="margin-bottom:20px;">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E9;">👧</div>
          <div><div class="stat-num" style="color:var(--grass)"><?= $total_students ?></div><div class="stat-label">Total Students</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#E0F4FD;">🎯</div>
          <div><div class="stat-num" style="color:var(--sky)"><?= count($active_students) ?></div><div class="stat-label">Active in Activities</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#FFF3CD;">🌟</div>
          <div><div class="stat-num" style="color:var(--sun)"><?= $avg_excellent ?>%</div><div class="stat-label">Excellent Rate</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#EDE7F6;">📅</div>
          <div>
            <div class="stat-num" style="color:var(--purple)">
              <?= date('M Y', mktime(0,0,0,(int)$report_mon,1,(int)$report_year)) ?>
            </div>
            <div class="stat-label">Report Month</div>
          </div>
        </div>
      </div>

      <!-- Report Table -->
      <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:20px 28px;border-bottom:1px solid #F0F4F8;display:flex;align-items:center;justify-content:space-between;">
          <div style="font-size:16px;font-weight:900;">Student Activity Performance</div>
          <div style="font-size:13px;color:var(--muted);font-weight:600;">
            <?= date('F Y', mktime(0,0,0,(int)$report_mon,1,(int)$report_year)) ?>
          </div>
        </div>
        <div style="overflow-x:auto;">
        <table class="report-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Student Name</th>
              <th>Class</th>
              <th style="text-align:center;">Activities</th>
              <th style="text-align:center;">🌟 Excellent</th>
              <th style="text-align:center;">👍 Good</th>
              <th style="text-align:center;">📈 Needs Improvement</th>
              <th style="text-align:center;">❌ Absent</th>
              <th>Performance</th>
              <th>Activity Details</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($report_data as $i => $r):
            $total_act = (int)$r['total_activities'];
            $exc   = (int)$r['excellent'];
            $good  = (int)$r['good'];
            $needs = (int)$r['needs_improvement'];
            $abs   = (int)$r['absent'];
            $score = $total_act > 0 ? round(($exc*4 + $good*3 + $needs*2) / ($total_act * 4) * 100) : 0;
            $score_color = $score >= 80 ? '#2E7D32' : ($score >= 60 ? '#0277BD' : ($score >= 40 ? '#E65100' : '#C62828'));
            $score_bg    = $score >= 80 ? '#E8F5E9'  : ($score >= 60 ? '#E0F4FD'  : ($score >= 40 ? '#FFF3CD'  : '#FCE4EC'));
            $activities_list = [];
            if ($r['activity_list']) {
                foreach (explode(';;', $r['activity_list']) as $item) {
                    [$aname, $aprog] = explode('|', $item . '|');
                    $activities_list[] = ['name' => $aname, 'progress' => $aprog];
                }
            }
          ?>
          <tr>
            <td style="color:var(--muted);font-weight:700;"><?= $i+1 ?></td>
            <td>
              <div style="font-weight:800;font-size:14px;"><?= htmlspecialchars($r['name']) ?></div>
            </td>
            <td>
              <span style="background:#F0F4F8;color:var(--muted);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                <?= htmlspecialchars($r['class_name'] ?? 'N/A') ?>
              </span>
            </td>
            <td style="text-align:center;font-weight:900;font-size:16px;color:var(--text);"><?= $total_act ?: '—' ?></td>
            <td style="text-align:center;">
              <?php if ($exc > 0): ?>
                <span style="background:#E8F5E9;color:#2E7D32;padding:4px 12px;border-radius:20px;font-weight:800;"><?= $exc ?></span>
              <?php else: echo '—'; endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if ($good > 0): ?>
                <span style="background:#E0F4FD;color:#0277BD;padding:4px 12px;border-radius:20px;font-weight:800;"><?= $good ?></span>
              <?php else: echo '—'; endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if ($needs > 0): ?>
                <span style="background:#FFF3CD;color:#E65100;padding:4px 12px;border-radius:20px;font-weight:800;"><?= $needs ?></span>
              <?php else: echo '—'; endif; ?>
            </td>
            <td style="text-align:center;">
              <?php if ($abs > 0): ?>
                <span style="background:#FCE4EC;color:#C62828;padding:4px 12px;border-radius:20px;font-weight:800;"><?= $abs ?></span>
              <?php else: echo '—'; endif; ?>
            </td>
            <td>
              <?php if ($total_act > 0): ?>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="score-bar-wrap">
                  <span class="score-bar" style="width:<?= $score ?>%;background:<?= $score_color ?>;"></span>
                </div>
                <span style="background:<?= $score_bg ?>;color:<?= $score_color ?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:800;"><?= $score ?>%</span>
              </div>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px;">No data</span>
              <?php endif; ?>
            </td>
            <td>
              <?php foreach ($activities_list as $al):
                $pill_class = match($al['progress']) {
                  'Excellent'=>'pill-excellent','Good'=>'pill-good',
                  'Needs Improvement'=>'pill-needs','Absent'=>'pill-absent',default=>'pill-good'
                };
              ?>
              <span class="progress-pill <?= $pill_class ?>" title="<?= htmlspecialchars($al['progress']) ?>">
                <?= htmlspecialchars(mb_strimwidth($al['name'], 0, 18, '…')) ?>
              </span>
              <?php endforeach; ?>
              <?php if (empty($activities_list)): ?>
                <span style="color:var(--muted);font-size:12px;">No activities</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

      <div class="no-print" style="margin-top:16px;padding:14px 20px;background:#FFF3CD;border-radius:12px;font-size:13px;font-weight:700;color:#E65100;">
        💡 Performance score = (Excellent×4 + Good×3 + Needs Improvement×2) / Max possible score × 100%
      </div>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<script>
// Checkbox row toggle
function toggleRow(chk, sid) {
  const row  = document.getElementById('row-'+sid);
  const sidI = document.getElementById('sid-'+sid);
  const prog = document.getElementById('prog-'+sid);
  const rem  = document.getElementById('rem-'+sid);
  if (chk.checked) {
    row.style.opacity='1';
    sidI.disabled=false; prog.disabled=false; rem.disabled=false;
  } else {
    row.style.opacity='.5';
    sidI.disabled=true; prog.disabled=true; rem.disabled=true;
  }
}

// Toggle all via header checkbox
function toggleAll(masterChk) {
  document.querySelectorAll('.row-chk').forEach(chk => {
    chk.checked = masterChk.checked;
    chk.dispatchEvent(new Event('change'));
    const sid = chk.closest('tr').id.replace('row-','');
    toggleRow(chk, sid);
  });
}

function selectAll()   { document.getElementById('chk-all').checked=true;  toggleAll({checked:true}); }
function deselectAll() { document.getElementById('chk-all').checked=false; toggleAll({checked:false}); }

function setAll(val) {
  document.querySelectorAll('.row-chk:checked').forEach(chk => {
    const sid = chk.closest('tr').id.replace('row-','');
    const sel = document.getElementById('prog-'+sid);
    if (sel && !sel.disabled) { sel.value=val; sel.style.color=getColor(val); }
  });
}

function getColor(v) {
  return {Excellent:'#2E7D32',Good:'#0277BD','Needs Improvement':'#E65100',Absent:'#C62828'}[v] || '#2D3A4A';
}

// Auto-hide alerts
setTimeout(()=>{ document.querySelectorAll('.alert-success,.alert-error').forEach(a=>a.style.transition='opacity 1s',a.style.opacity='0'); },3000);
</script>

<style>
@media print {
  .no-print{display:none!important;}
  .print-only{display:block!important;}
  .sidebar,.topbar{display:none!important;}
  .main{margin-left:0!important;}
  .content{padding:0!important;}
  body{background:#fff!important;}
}
.print-only{display:none;}
</style>
</body>
</html>