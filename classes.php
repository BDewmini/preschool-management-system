<?php
session_start();
include 'db.php';

$user = $_SESSION['user'] ?? null;

$success = $error = '';
$action = $_GET['action'] ?? 'list';
$edit_class = null;

// ── ADD ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $age_group  = $conn->real_escape_string($_POST['age_group']);
    $capacity   = (int)$_POST['capacity'];

    $sql = "INSERT INTO classes (class_name, age_group, capacity) VALUES ('$class_name', '$age_group', $capacity)";
    if ($conn->query($sql)) {
        $success = "Class added successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $action = 'list';
}

// ── EDIT SAVE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_class'])) {
    $id         = (int)$_POST['id'];
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $age_group  = $conn->real_escape_string($_POST['age_group']);
    $capacity   = (int)$_POST['capacity'];

    $sql = "UPDATE classes SET class_name='$class_name', age_group='$age_group', capacity=$capacity WHERE class_id=$id";
    if ($conn->query($sql)) {
        $success = "Class updated successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $action = 'list';
}

// ── DELETE ────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($conn->query("DELETE FROM classes WHERE class_id=$id")) {
        $success = "Class deleted.";
    } else {
        $error = "Delete failed: " . $conn->error;
    }
    $action = 'list';
}

// ── LOAD FOR EDIT ────────────────────────────────────
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT * FROM classes WHERE class_id=$id");
    $edit_class = $res->fetch_assoc();
}

// ── PROFILE VIEW ─────────────────────────────────────
$profile_class = null;
if ($action === 'profile' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT * FROM classes WHERE class_id=$id");
    $profile_class = $res->fetch_assoc();
}

// ── CLASS LIST ──────────────────────────────────────
$search  = $conn->real_escape_string($_GET['search'] ?? '');
$where   = $search ? "WHERE class_name LIKE '%$search%' OR age_group LIKE '%$search%'" : '';
$classes = $conn->query("SELECT * FROM classes $where ORDER BY class_name ASC");
$total   = $conn->query("SELECT COUNT(*) as c FROM classes")->fetch_assoc()['c'];
$total_capacity = $conn->query("SELECT SUM(capacity) as c FROM classes")->fetch_assoc()['c'] ?? 0;
$total_students = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Classes – Little Stars Pre School</title>
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
  transition: all .18s; margin-bottom: 2px;
}
.nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.nav a.active { background: rgba(79,195,247,0.2); color: var(--sky); }
.nav a .icon { font-size: 18px; width: 22px; text-align: center; }
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
  border-bottom: 1px solid #E8EEF5; position: sticky; top: 0; z-index: 50;
}
.page-title { font-size: 22px; font-weight: 800; }
.page-title span { color: var(--sky); }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.date-badge {
  background: var(--bg); border-radius: 20px;
  padding: 6px 14px; font-size: 13px; font-weight: 600; color: var(--muted);
}

/* BUTTONS */
.btn {
  padding: 9px 20px; border-radius: 10px;
  font-size: 14px; font-weight: 800; cursor: pointer; border: none;
  font-family: 'Nunito', sans-serif; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px; transition: all .18s;
}
.btn-primary { background: var(--sky); color: #fff; }
.btn-primary:hover { background: #0288D1; }
.btn-danger  { background: #FCE4EC; color: var(--rose); }
.btn-danger:hover { background: var(--rose); color: #fff; }
.btn-edit    { background: #F0F7FF; color: var(--sky); }
.btn-edit:hover { background: var(--sky); color: #fff; }
.btn-sm { padding: 6px 14px; font-size: 12px; }
.btn-back { background: #F0F4F8; color: var(--muted); }
.btn-back:hover { background: #dde6f0; }

/* CONTENT */
.content { padding: 28px 32px; flex: 1; }
.alert-success {
  background: #E8F5E9; color: #388E3C;
  border-radius: 10px; padding: 12px 18px;
  font-weight: 700; margin-bottom: 16px;
}
.alert-error {
  background: #FCE4EC; color: #C62828;
  border-radius: 10px; padding: 12px 18px;
  font-weight: 700; margin-bottom: 16px;
}

/* STAT CARDS */
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card {
  background: var(--card); border-radius: 16px; padding: 20px 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
  display: flex; align-items: center; gap: 16px;
  transition: transform .2s; cursor: default;
}
.stat-card:hover { transform: translateY(-3px); }
.stat-icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; font-size: 22px;
}
.stat-num   { font-size: 26px; font-weight: 900; line-height: 1; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; margin-top: 2px; }

/* CLASS CARDS GRID */
.classes-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px; margin-bottom: 24px;
}
.class-card {
  background: var(--card); border-radius: 18px;
  padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.05);
  transition: transform .2s, box-shadow .2s;
  position: relative; overflow: hidden;
}
.class-card:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(0,0,0,.10); }
.class-card-accent {
  position: absolute; top: 0; left: 0; right: 0;
  height: 5px; border-radius: 18px 18px 0 0;
}
.class-card-icon {
  width: 52px; height: 52px; border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 26px; margin-bottom: 14px;
}
.class-card-name { font-size: 18px; font-weight: 900; margin-bottom: 4px; }
.class-card-age  { font-size: 13px; color: var(--muted); font-weight: 600; margin-bottom: 14px; }
.class-card-stats {
  display: flex; gap: 12px; margin-bottom: 16px;
}
.cstat { flex: 1; background: var(--bg); border-radius: 10px; padding: 10px; text-align: center; }
.cstat-num   { font-size: 20px; font-weight: 900; }
.cstat-label { font-size: 10px; color: var(--muted); font-weight: 700; }

/* Progress bar */
.progress-wrap { margin-bottom: 16px; }
.progress-label {
  display: flex; justify-content: space-between;
  font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 6px;
}
.progress-bar {
  height: 8px; background: #E0E8F0; border-radius: 99px; overflow: hidden;
}
.progress-fill { height: 100%; border-radius: 99px; transition: width .4s; }

.class-card-actions { display: flex; gap: 8px; }

/* TABLE CARD (list fallback) */
.card {
  background: var(--card); border-radius: 18px;
  padding: 24px 28px; margin-bottom: 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.card-header {
  display: flex; align-items: center;
  justify-content: space-between; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;
}
.card-title { font-size: 16px; font-weight: 800; }
.search-box {
  padding: 9px 16px; border-radius: 10px;
  border: 1.5px solid #E0E8F0;
  font-family: 'Nunito', sans-serif; font-size: 14px;
  outline: none; width: 220px; transition: border .18s;
}
.search-box:focus { border-color: var(--sky); }

/* FORM */
.form-card {
  background: var(--card); border-radius: 18px;
  padding: 28px 32px; margin-bottom: 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.form-title { font-size: 16px; font-weight: 800; margin-bottom: 20px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1/-1; }
.form-group label { font-size: 13px; font-weight: 700; color: var(--muted); }
.form-group input,
.form-group select {
  padding: 10px 14px; border-radius: 10px;
  border: 1.5px solid #E0E8F0; font-family: 'Nunito', sans-serif;
  font-size: 14px; outline: none; transition: border .18s; color: var(--text); background: #fff;
}
.form-group input:focus,
.form-group select:focus { border-color: var(--sky); }
.form-actions { display: flex; gap: 12px; margin-top: 20px; }

/* PROFILE */
.profile-header {
  display: flex; align-items: flex-start; gap: 24px;
  background: var(--card); border-radius: 18px;
  padding: 28px 32px; margin-bottom: 20px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.profile-icon {
  width: 90px; height: 90px; border-radius: 20px;
  display: flex; align-items: center; justify-content: center;
  font-size: 40px; flex-shrink: 0;
}
.profile-name { font-size: 24px; font-weight: 900; }
.profile-sub  { color: var(--muted); font-size: 14px; margin-top: 4px; }
.info-grid {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 14px; margin-top: 18px;
}
.info-item { display: flex; flex-direction: column; gap: 3px; }
.info-item .lbl { font-size: 11px; color: var(--muted); font-weight: 800; letter-spacing: .5px; }
.info-item .val { font-size: 15px; font-weight: 800; }

/* Student table in profile */
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th {
  background: var(--bg); padding: 10px 14px; text-align: left;
  font-size: 12px; color: var(--muted); font-weight: 800;
}
td { padding: 10px 14px; border-bottom: 1px solid #F0F4F8; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #FAFCFF; }
.badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; }
.badge-grass  { background: #E8F5E9; color: #2E7D32; }
.badge-rose   { background: #FCE4EC; color: #C62828; }
.badge-sky    { background: #E0F4FD; color: #0277BD; }
.badge-purple { background: #EDE7F6; color: #6A1B9A; }
.badge-sun    { background: #FFF3CD; color: #F57F17; }

/* Empty */
.empty-state { text-align: center; padding: 50px 20px; }
.empty-state .ei { font-size: 52px; margin-bottom: 12px; }
.empty-state p   { color: var(--muted); font-weight: 600; }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; }
  .form-grid, .stat-row, .info-grid { grid-template-columns: 1fr; }
  .classes-grid { grid-template-columns: 1fr; }
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
    <div class="page-title">🏫 <span>Classes</span></div>
    <div class="topbar-right">
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">➕ Add Class</a>
      <?php else: ?>
        <a href="?action=list" class="btn btn-back">← Back to List</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="content">

    <?php if ($success): ?>
      <div class="alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ══════════════ LIST ══════════════ -->
    <?php if ($action === 'list'): ?>

      <!-- Stats -->
      <div class="stat-row">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E0F4FD;">🏫</div>
          <div>
            <div class="stat-num" style="color:var(--sky)"><?= $total ?></div>
            <div class="stat-label">Total Classes</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E9;">👧</div>
          <div>
            <div class="stat-num" style="color:var(--grass)"><?= $total_students ?></div>
            <div class="stat-label">Total Students</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#FFF3CD;">🪑</div>
          <div>
            <div class="stat-num" style="color:var(--sun)"><?= $total_capacity ?></div>
            <div class="stat-label">Total Capacity</div>
          </div>
        </div>
      </div>

      <!-- Search -->
      <div class="card" style="padding:16px 20px; margin-bottom:20px;">
        <form method="GET" style="display:flex; gap:8px; align-items:center;">
          <input type="hidden" name="action" value="list">
          <input type="text" name="search" class="search-box"
                 placeholder="🔍 Search class name or age group..."
                 value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-primary btn-sm">Search</button>
          <?php if ($search): ?>
            <a href="?action=list" class="btn btn-sm btn-back">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Class Cards -->
      <?php if ($classes && mysqli_num_rows($classes) > 0): ?>
      <?php
      $accent_colors = [
        ['#4FC3F7','#E0F4FD','#0288D1'],
        ['#66BB6A','#E8F5E9','#2E7D32'],
        ['#F06292','#FCE4EC','#C62828'],
        ['#FFB830','#FFF3CD','#F57F17'],
        ['#9575CD','#EDE7F6','#6A1B9A'],
        ['#26C6DA','#E0F7FA','#00838F'],
      ];
      $icons = ['🦋','🌈','🌟','🌻',];
      $i = 0;
      mysqli_data_seek($classes, 0);
      ?>
      <div class="classes-grid">
        <?php while ($c = mysqli_fetch_assoc($classes)):
          $ac = $accent_colors[$i % count($accent_colors)];
          $ic = $icons[$i % count($icons)];
          $enrolled = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE class_id=".(int)$c['class_id'])->fetch_assoc()['cnt'] ?? 0;
          $cap = max(1, (int)$c['capacity']);
          $pct = min(100, round(($enrolled / $cap) * 100));
          $fill_color = $pct >= 90 ? '#F06292' : ($pct >= 70 ? '#FFB830' : '#66BB6A');
          $i++;
        ?>
        <div class="class-card">
          <div class="class-card-accent" style="background:<?= $ac[0] ?>;"></div>
          <div class="class-card-icon" style="background:<?= $ac[1] ?>;"><?= $ic ?></div>
          <div class="class-card-name"><?= htmlspecialchars($c['class_name']) ?></div>
          <div class="class-card-age">👶 Age Group: <?= htmlspecialchars($c['age_group']) ?></div>

          <div class="class-card-stats">
            <div class="cstat">
              <div class="cstat-num" style="color:<?= $ac[2] ?>"><?= $enrolled ?></div>
              <div class="cstat-label">Enrolled</div>
            </div>
            <div class="cstat">
              <div class="cstat-num" style="color:var(--muted)"><?= $c['capacity'] ?></div>
              <div class="cstat-label">Capacity</div>
            </div>
            <div class="cstat">
              <div class="cstat-num" style="color:<?= $fill_color ?>"><?= $pct ?>%</div>
              <div class="cstat-label">Full</div>
            </div>
          </div>

          <div class="progress-wrap">
            <div class="progress-label">
              <span>Enrollment</span>
              <span><?= $enrolled ?>/<?= $c['capacity'] ?></span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" style="width:<?= $pct ?>%; background:<?= $fill_color ?>;"></div>
            </div>
          </div>

          <div class="class-card-actions">
            <a href="?action=profile&id=<?= $c['class_id'] ?>" class="btn btn-edit btn-sm" style="flex:1; justify-content:center;">👁 View</a>
            <a href="?action=edit&id=<?= $c['class_id'] ?>" class="btn btn-edit btn-sm" style="flex:1; justify-content:center;">✏️ Edit</a>
            <a href="?action=delete&id=<?= $c['class_id'] ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete <?= htmlspecialchars($c['class_name'], ENT_QUOTES) ?>?')">🗑</a>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="ei">🏫</div>
        <p>No classes found. <a href="?action=add" style="color:var(--sky);">Add one!</a></p>
      </div>
      <?php endif; ?>

    <!-- ══════════════ ADD ══════════════ -->
    <?php elseif ($action === 'add'): ?>
      <div class="form-card">
        <div class="form-title">➕ Add New Class</div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group full">
              <label>Class Name *</label>
              <input type="text" name="class_name" placeholder="e.g. Sunshine Class, Rainbow Room" required>
            </div>
            <div class="form-group">
              <label>Age Group *</label>
              <select name="age_group" required>
                <option value="">-- Select Age Group --</option>
                <option value="1-2 years">1-2 years</option>
                <option value="2-3 years">2-3 years</option>
                <option value="3-4 years">3-4 years</option>
                <option value="4-5 years">4-5 years</option>
                <option value="5-6 years">5-6 years</option>
                <option value="Nursery">Nursery</option>
                <option value="LKG">LKG</option>
                <option value="UKG">UKG</option>
                <option value="Grade 1">Grade 1</option>
              </select>
            </div>
            <div class="form-group">
              <label>Capacity *</label>
              <input type="number" name="capacity" placeholder="e.g. 20" min="1" max="100" required>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="add_class" class="btn btn-primary">➕ Add Class</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══════════════ EDIT ══════════════ -->
    <?php elseif ($action === 'edit' && $edit_class): ?>
      <div class="form-card">
        <div class="form-title">✏️ Edit Class</div>
        <form method="POST">
          <input type="hidden" name="id" value="<?= $edit_class['class_id'] ?>">
          <div class="form-grid">
            <div class="form-group full">
              <label>Class Name *</label>
              <input type="text" name="class_name" value="<?= htmlspecialchars($edit_class['class_name']) ?>" required>
            </div>
            <div class="form-group">
              <label>Age Group *</label>
              <select name="age_group" required>
                <?php foreach (['1-2 years','2-3 years','3-4 years','4-5 years','5-6 years','Nursery','LKG','UKG','Grade 1'] as $ag): ?>
                <option value="<?= $ag ?>" <?= $edit_class['age_group'] === $ag ? 'selected' : '' ?>><?= $ag ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Capacity *</label>
              <input type="number" name="capacity" value="<?= $edit_class['capacity'] ?>" min="1" max="100" required>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="edit_class" class="btn btn-primary">💾 Save Changes</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══════════════ PROFILE ══════════════ -->
    <?php elseif ($action === 'profile' && $profile_class): ?>
      <?php
        $cid      = $profile_class['class_id'];
        $enrolled = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE class_id=$cid")->fetch_assoc()['cnt'] ?? 0;
        $cap      = max(1, (int)$profile_class['capacity']);
        $pct      = min(100, round(($enrolled / $cap) * 100));
        $fill_color = $pct >= 90 ? '#F06292' : ($pct >= 70 ? '#FFB830' : '#66BB6A');
        $teacher  = $conn->query("SELECT full_name FROM teachers WHERE class_id=$cid LIMIT 1")->fetch_assoc();
      ?>

      <div class="profile-header">
        <div class="profile-icon" style="background:#E0F4FD;">🏫</div>
        <div style="flex:1;">
          <div class="profile-name"><?= htmlspecialchars($profile_class['class_name']) ?></div>
          <div class="profile-sub">
            <span class="badge badge-sky">👶 <?= htmlspecialchars($profile_class['age_group']) ?></span>
            &nbsp;·&nbsp; Class ID #<?= $profile_class['class_id'] ?>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <span class="lbl">👧 ENROLLED</span>
              <span class="val" style="color:var(--sky)"><?= $enrolled ?> students</span>
            </div>
            <div class="info-item">
              <span class="lbl">🪑 CAPACITY</span>
              <span class="val"><?= $profile_class['capacity'] ?> seats</span>
            </div>
            <div class="info-item">
              <span class="lbl">👩‍🏫 TEACHER</span>
              <span class="val"><?= $teacher ? htmlspecialchars($teacher['full_name']) : '—' ?></span>
            </div>
          </div>

          <!-- Progress -->
          <div style="margin-top:16px; max-width:400px;">
            <div style="display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:var(--muted); margin-bottom:6px;">
              <span>Enrollment Progress</span>
              <span><?= $pct ?>% full</span>
            </div>
            <div style="height:10px; background:#E0E8F0; border-radius:99px; overflow:hidden;">
              <div style="height:100%; width:<?= $pct ?>%; background:<?= $fill_color ?>; border-radius:99px;"></div>
            </div>
          </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:8px; flex-shrink:0;">
          <a href="?action=edit&id=<?= $cid ?>" class="btn btn-edit">✏️ Edit</a>
          <a href="?action=delete&id=<?= $cid ?>"
             class="btn btn-danger"
             onclick="return confirm('Delete this class?')">🗑 Delete</a>
        </div>
      </div>

      <!-- Students in this class -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">👧 Students in <?= htmlspecialchars($profile_class['class_name']) ?></div>
          <span style="font-size:13px; color:var(--muted);"><?= $enrolled ?> student<?= $enrolled != 1 ? 's' : '' ?></span>
        </div>
        <?php
        $class_students = $conn->query("SELECT * FROM students WHERE class_id=$cid ORDER BY name ASC");
        ?>
        <?php if ($class_students && mysqli_num_rows($class_students) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Age</th>
              <th>Parent Phone</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $colors = ['#f97316','#8b5cf6','#22c55e','#f59e0b','#3b82f6','#ec4899'];
            $j = 0;
            while ($s = mysqli_fetch_assoc($class_students)):
              $sc = $colors[$j % count($colors)]; $j++;
            ?>
            <tr>
              <td style="color:var(--muted); font-size:12px;"><?= $s['st_id'] ?></td>
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:34px; height:34px; border-radius:50%; background:<?= $sc ?>22; color:<?= $sc ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; border:2px solid <?= $sc ?>44;">
                    <?= strtoupper(substr($s['name'],0,1)) ?>
                  </div>
                  <span style="font-weight:700;"><?= htmlspecialchars($s['name']) ?></span>
                </div>
              </td>
              <td><?= $s['age'] ?> yrs</td>
              <td style="font-size:13px;"><?= htmlspecialchars($s['parent_phone']) ?></td>
              <td>
                <span class="badge <?= $s['status'] === 'active' ? 'badge-grass' : 'badge-rose' ?>">
                  <?= ucfirst($s['status']) ?>
                </span>
              </td>
              <td>
                <a href="students/index.php?action=profile&id=<?= $s['st_id'] ?>"
                   class="btn btn-edit btn-sm">👁 View</a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p style="color:var(--muted); text-align:center; padding:30px 0;">No students enrolled in this class yet.</p>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->
</body>
</html>
