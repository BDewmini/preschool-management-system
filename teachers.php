<?php
session_start();
include 'db.php';

$user = $_SESSION['user'] ?? null;

$success = $error = '';
$action = $_GET['action'] ?? 'list';
$edit_teacher = null;

// ── ADD ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $name        = $conn->real_escape_string($_POST['name']);
    $phone       = $conn->real_escape_string($_POST['phone']);
    $subject     = $conn->real_escape_string($_POST['subject']);
    $class       = $conn->real_escape_string($_POST['class']);
    $class_id    = (int)$_POST['class_id'];
    $status      = $conn->real_escape_string($_POST['status']);
    $photo       = '';

    if (!empty($_FILES['photo']['name'])) {
        $ext   = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $fname = 'teacher_' . time() . '.' . $ext;
        $dest  = 'upload/teachers/' . $fname;
        if (!is_dir('upload/teachers/')) mkdir('upload/teachers/', 0777, true);
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            $photo = $fname;
        }
    }

    $sql = "INSERT INTO teachers (full_name,  phone, subject, class, class_id, status, photo)
            VALUES ('$name', '$phone', '$subject', '$class', $class_id, '$status', '$photo')";
    if ($conn->query($sql)) {
        $success = "Teacher added successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $action = 'list';
}

// ── EDIT SAVE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_teacher'])) {
    $id       = (int)$_POST['id'];
    $name     = $conn->real_escape_string($_POST['name']);
    $phone    = $conn->real_escape_string($_POST['phone']);
    $subject  = $conn->real_escape_string($_POST['subject']);
    $class    = $conn->real_escape_string($_POST['class']);
    $class_id = (int)$_POST['class_id'];
    $status   = $conn->real_escape_string($_POST['status']);

    $photo_sql = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext   = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $fname = 'teacher_' . time() . '.' . $ext;
        $dest  = 'upload/teachers/' . $fname;
        if (!is_dir('upload/teachers/')) mkdir('upload/teaches/', 0777, true);
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            $photo_sql = ", photo='$fname'";
        }
    }

    $sql = "UPDATE teachers SET name='$name', phone='$phone',
            subject='$subject', class='$class', class_id=$class_id,
            status='$status' $photo_sql WHERE id=$id";
    if ($conn->query($sql)) {
        $success = "Teacher updated successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $action = 'list';
}

// ── DELETE ────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($conn->query("DELETE FROM teachers WHERE id=$id")) {
        $success = "Teacher deleted.";
    } else {
        $error = "Delete failed: " . $conn->error;
    }
    $action = 'list';
}

// ── LOAD FOR EDIT ────────────────────────────────────
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT * FROM teachers WHERE id=$id");
    $edit_teacher = $res->fetch_assoc();
}

// ── PROFILE VIEW ─────────────────────────────────────
$profile_teacher = null;
if ($action === 'profile' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT * FROM teachers WHERE id=$id");
    $profile_teacher = $res->fetch_assoc();
}

// ── TEACHER LIST ──────────────────────────────────────
$search  = $conn->real_escape_string($_GET['search'] ?? '');
$where   = $search ? "WHERE name LIKE '%$search%' OR class LIKE '%$search%' OR subject LIKE '%$search%'" : '';
$teachers = $conn->query("SELECT * FROM teachers $where ORDER BY full_name ASC");
$total    = $conn->query("SELECT COUNT(*) as c FROM teachers")->fetch_assoc()['c'];
$active   = $conn->query("SELECT COUNT(*) as c FROM teachers WHERE status='active'")->fetch_assoc()['c'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teachers – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
:root {
  --sun:    #FFB830;
  --sky:    #ab3f60;
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
  background: linear-gradient(160deg,#1a2a4a 0%,#243756 100%);
  min-height: 100vh;
  display: flex; flex-direction: column;
  position: fixed; top:0; left:0; z-index:100;
}
.sidebar-brand {
  padding: 28px 24px 20px;
  display:flex; align-items:center; gap:12px;
  border-bottom:1px solid rgba(255,255,255,.08);
}
.brand-icon {
  width:42px; height:42px; background:var(--sun);
  border-radius:12px; display:flex; align-items:center;
  justify-content:center; font-size:22px;
}
.brand-name { font-family:'Fredoka One',cursive; font-size:20px; color:#fff; line-height:1.1; }
.brand-sub  { font-size:11px; color:rgba(255,255,255,.45); letter-spacing:.5px; }
.nav { padding:20px 12px; flex:1; }
.nav-label {
  font-size:10px; font-weight:800; letter-spacing:1.5px;
  color:rgba(255,255,255,.3); text-transform:uppercase;
  padding:0 12px; margin:16px 0 6px;
}
.nav a {
  display:flex; align-items:center; gap:12px;
  padding:10px 14px; border-radius:10px;
  color:rgba(255,255,255,.65); text-decoration:none;
  font-size:14px; font-weight:600;
  transition:all .18s; margin-bottom:2px;
}
.nav a:hover { background:rgba(255,255,255,.1); color:#fff; }
.nav a.active { background:rgba(79,195,247,.2); color:var(--sky); }
.nav a .icon { font-size:18px; width:22px; text-align:center; }
.sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,.08); }
.user-info { display:flex; align-items:center; gap:10px; }
.avatar {
  width:36px; height:36px; border-radius:50%;
  background:var(--rose);
  display:flex; align-items:center; justify-content:center;
  font-weight:800; color:#fff; font-size:15px;
}
.user-name { font-size:13px; font-weight:700; color:#fff; }
.user-role  { font-size:11px; color:rgba(255,255,255,.4); }
.logout-btn {
  margin-left:auto;
  background:rgba(240,98,146,.2);
  border:none; border-radius:8px;
  padding:6px 10px; cursor:pointer;
  color:var(--rose); font-size:18px;
  transition:background .18s; text-decoration:none;
  display:flex; align-items:center;
}
.logout-btn:hover { background:rgba(240,98,146,.4); }

/* ── TABS ── */
.tab-bar {
  display:flex; gap:4px;
  background:var(--card);
  padding:0 32px;
  border-bottom:1px solid #E8EEF5;
}
.tab-btn {
  padding:14px 20px; border:none; background:none;
  font-family:'Nunito',sans-serif; font-size:14px; font-weight:700;
  color:var(--muted); cursor:pointer;
  border-bottom:3px solid transparent;
  transition:all .18s;
}
.tab-btn.active { color:var(--sky); border-bottom-color:var(--sky); }
.tab-btn:hover  { color:var(--text); }

/* ── MAIN ── */
.main {
  margin-left:var(--sidebar-w);
  flex:1; display:flex; flex-direction:column; min-height:100vh;
}
.topbar {
  background:var(--card);
  padding:18px 32px;
  display:flex; align-items:center; justify-content:space-between;
  border-bottom:1px solid #E8EEF5;
  position:sticky; top:0; z-index:50;
}
.page-title { font-size:22px; font-weight:800; }
.page-title span { color:var(--sky); }
.topbar-right { display:flex; align-items:center; gap:12px; }
.btn {
  padding:9px 20px; border-radius:10px;
  font-size:14px; font-weight:800;
  cursor:pointer; border:none;
  font-family:'Nunito',sans-serif;
  text-decoration:none; display:inline-flex; align-items:center; gap:6px;
  transition:all .18s;
}
.btn-primary { background:var(--sky); color:#fff; }
.btn-primary:hover { background:#0288D1; }
.btn-danger  { background:#FCE4EC; color:var(--rose); }
.btn-danger:hover { background:var(--rose); color:#fff; }
.btn-edit    { background:#F0F7FF; color:var(--sky); }
.btn-edit:hover { background:var(--sky); color:#fff; }
.btn-sm { padding:6px 14px; font-size:12px; }
.btn-back { background:#F0F4F8; color:var(--muted); }
.btn-back:hover { background:#dde6f0; }

/* ── CONTENT ── */
.content { padding:28px 32px; flex:1; }
.alert-success {
  background:#E8F5E9; color:#388E3C;
  border-radius:10px; padding:12px 18px;
  font-weight:700; margin-bottom:16px;
}
.alert-error {
  background:#FCE4EC; color:#C62828;
  border-radius:10px; padding:12px 18px;
  font-weight:700; margin-bottom:16px;
}

/* ── STAT CARDS ── */
.stat-row { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.stat-card {
  background:var(--card); border-radius:16px;
  padding:20px 24px;
  box-shadow:0 2px 12px rgba(0,0,0,.05);
  display:flex; align-items:center; gap:16px;
}
.stat-icon {
  width:48px; height:48px; border-radius:14px;
  display:flex; align-items:center; justify-content:center;
  font-size:22px;
}
.stat-num  { font-size:26px; font-weight:900; line-height:1; }
.stat-label { font-size:12px; color:var(--muted); font-weight:700; margin-top:2px; }

/* ── TABLE CARD ── */
.card {
  background:var(--card); border-radius:18px;
  padding:24px 28px; margin-bottom:24px;
  box-shadow:0 2px 12px rgba(0,0,0,.05);
}
.card-header {
  display:flex; align-items:center;
  justify-content:space-between; margin-bottom:18px;
}
.card-title { font-size:16px; font-weight:800; }
.search-box {
  padding:9px 16px; border-radius:10px;
  border:1.5px solid #E0E8F0;
  font-family:'Nunito',sans-serif; font-size:14px;
  outline:none; width:220px; transition:border .18s;
}
.search-box:focus { border-color:var(--sky); }
table { width:100%; border-collapse:collapse; font-size:14px; }
th {
  background:var(--bg); padding:10px 14px;
  text-align:left; font-size:12px;
  color:var(--muted); font-weight:800;
}
td { padding:10px 14px; border-bottom:1px solid #F0F4F8; vertical-align:middle; }
tr:last-child td { border-bottom:none; }
tr:hover td { background:#FAFCFF; }
.teacher-avatar {
  width:36px; height:36px; border-radius:10px;
  object-fit:cover; background:#E0F4FD;
  display:flex; align-items:center; justify-content:center;
  font-size:16px; font-weight:800; color:var(--sky);
  overflow:hidden;
}
.teacher-name { font-weight:700; }
.teacher-meta { font-size:12px; color:var(--muted); }
.actions { display:flex; gap:6px; }

/* ── BADGES ── */
.badge {
  padding:4px 12px; border-radius:20px;
  font-size:11px; font-weight:800;
}
.badge.active   { background:#E8F5E9; color:var(--grass); }
.badge.inactive { background:#FCE4EC; color:var(--rose); }
.badge.running  { background:#E0F4FD; color:var(--sky); }
.badge.upcoming { background:#FFF3CD; color:#F9A825; }
.badge.completed{ background:#F0F4F8; color:var(--muted); }

/* ── FORM ── */
.form-card {
  background:var(--card); border-radius:18px;
  padding:28px 32px; margin-bottom:24px;
  box-shadow:0 2px 12px rgba(0,0,0,.05);
}
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-group { display:flex; flex-direction:column; gap:6px; }
.form-group label { font-size:13px; font-weight:700; color:var(--muted); }
.form-group input,
.form-group select,
.form-group textarea {
  padding:10px 14px; border-radius:10px;
  border:1.5px solid #E0E8F0;
  font-family:'Nunito',sans-serif;
  font-size:14px; outline:none;
  transition:border .18s; color:var(--text);
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color:var(--sky); }
.form-actions { display:flex; gap:12px; margin-top:20px; }

/* ── PROFILE ── */
.profile-header {
  display:flex; align-items:center; gap:24px;
  background:var(--card); border-radius:18px;
  padding:28px 32px; margin-bottom:20px;
  box-shadow:0 2px 12px rgba(0,0,0,.05);
}
.profile-photo {
  width:90px; height:90px; border-radius:20px;
  object-fit:cover; background:#E0F4FD;
  display:flex; align-items:center; justify-content:center;
  font-size:36px; font-weight:900; color:var(--sky);
  overflow:hidden; flex-shrink:0;
}
.profile-name  { font-size:22px; font-weight:900; }
.profile-meta  { color:var(--muted); font-size:14px; margin-top:4px; }
.info-grid {
  display:grid; grid-template-columns:1fr 1fr;
  gap:12px; margin-top:16px;
}
.info-item { display:flex; flex-direction:column; gap:2px; }
.info-item .lbl { font-size:11px; color:var(--muted); font-weight:800; }
.info-item .val { font-size:14px; font-weight:700; }


</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<!-- ── SIDEBAR ── -->
<?php include 'sidebar.php'; ?>

<!-- ── MAIN ── -->
<div class="main">
  <div class="topbar">
    <div class="page-title">👩‍🏫 <span>Teachers</span></div>
    <div class="topbar-right">
      <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">➕ Add Teacher</a>
      <?php else: ?>
        <a href="?action=list" class="btn btn-back">← Back to List</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab Bar (only on list view) -->
  <?php if ($action === 'list'): ?>
  <div class="tab-bar">
    <button class="tab-btn <?= (!isset($_GET['tab']) || $_GET['tab']==='teachers') ? 'active':'' ?>"
            onclick="showTab('teachers')">👩‍🏫 Teachers</button>
  </div>
  <?php endif; ?>

  <div class="content">

    <?php if ($success): ?>
      <div class="alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ══════════════ LIST VIEW ══════════════ -->
    <?php if ($action === 'list'): ?>

    <!-- TEACHERS TAB -->
    <div id="tab-teachers">
      <div class="stat-row">
        <div class="stat-card">
          <div class="stat-icon" style="background:#E0F4FD;">👩‍🏫</div>
          <div>
            <div class="stat-num" style="color:var(--sky)"><?= $total ?></div>
            <div class="stat-label">Total Teachers</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#E8F5E9;">✅</div>
          <div>
            <div class="stat-num" style="color:var(--grass)"><?= $active ?></div>
            <div class="stat-label">Active Teachers</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#FCE4EC;">📋</div>
          <div>
            <div class="stat-num" style="color:var(--rose)"><?= $total - $active ?></div>
            <div class="stat-label">Inactive Teachers</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">📋 All Teachers</div>
          <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="action" value="list">
            <input type="hidden" name="tab" value="teachers">
            <input type="text" name="search" class="search-box"
                   placeholder="🔍 Search name or class..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search): ?>
              <a href="?action=list&tab=teachers" class="btn btn-sm btn-back">Clear</a>
            <?php endif; ?>
          </form>
        </div>

        <?php if ($teachers && mysqli_num_rows($teachers) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Teacher</th>
              <th>Subject</th>
              <th>Class</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($t = mysqli_fetch_assoc($teachers)): ?>
            <tr>
              <td style="color:var(--muted);font-size:12px;"><?= $t['id'] ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <?php if (!empty($t['photo'])): ?>
                    <img src="upload/teachers/<?= htmlspecialchars($t['photo']) ?>"
                         class="teacher-avatar">
                  <?php else: ?>
                    <div class="teacher-avatar"><?= strtoupper(substr($t['name'],0,1)) ?></div>
                  <?php endif; ?>
                  <div>
                    <div class="teacher-name"><?= htmlspecialchars($t['full_name']) ?></div>
                    <div class="teacher-meta">ID: <?= $t['id'] ?></div>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($t['subject']) ?></td>
              <td><?= htmlspecialchars($t['class']) ?></td>
              <td><?= htmlspecialchars($t['phone']) ?></td>
              <td>
                <span class="badge <?= strtolower($t['status']) ?>">
                  <?= ucfirst($t['status']) ?>
                </span>
              </td>
              <td>
                <div class="actions">
                  <a href="?action=profile&id=<?= $t['id'] ?>" class="btn btn-sm btn-edit">👁 View</a>
                  <a href="?action=edit&id=<?= $t['id'] ?>" class="btn btn-sm btn-edit">✏️ Edit</a>
                  <a href="?action=delete&id=<?= $t['id'] ?>"
                     class="btn btn-sm btn-danger"
                     onclick="return confirm('Delete this teacher?')">🗑 Delete</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p style="color:var(--muted);text-align:center;padding:30px 0;">
            No teachers found. <a href="?action=add" style="color:var(--sky);">Add one!</a>
          </p>
        <?php endif; ?>
      </div>
    </div><!-- /#tab-teachers -->

    

    <!-- ══════════════ ADD FORM ══════════════ -->
    <?php elseif ($action === 'add'): ?>
      <div class="form-card">
        <div style="font-size:16px;font-weight:800;margin-bottom:18px;">➕ Add New Teacher</div>
        <form method="POST" enctype="multipart/form-data">
          <div class="form-grid">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="name" placeholder="Teacher's full name" required>
            </div>
            <div class="form-group">
              <label>Age *</label>
              <input type="number" name="age" placeholder="e.g. 30" min="18" max="70" required>
            </div>
            <div class="form-group">
              <label>Phone *</label>
              <input type="text" name="phone" placeholder="07X XXX XXXX" required>
            </div>
            <div class="form-group">
              <label>Subject *</label>
              <input type="text" name="subject" placeholder="e.g. Mathematics" required>
            </div>
            <div class="form-group">
              <label>Class Name *</label>
              <input type="text" name="class" placeholder="e.g. Sunshine Class" required>
            </div>
            <div class="form-group">
              <label>Class ID *</label>
              <input type="number" name="class_id" placeholder="e.g. 1" required>
            </div>
            <div class="form-group">
              <label>Status *</label>
              <select name="status" required>
                <option value="active">✅ Active</option>
                <option value="inactive">❌ Inactive</option>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label>Photo (optional)</label>
              <input type="file" name="photo" accept="image/*">
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="add_teacher" class="btn btn-primary">➕ Add Teacher</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══════════════ EDIT FORM ══════════════ -->
    <?php elseif ($action === 'edit' && $edit_teacher): ?>
      <div class="form-card">
        <div style="font-size:16px;font-weight:800;margin-bottom:18px;">✏️ Edit Teacher</div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= $edit_teacher['id'] ?>">
          <div class="form-grid">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="name" value="<?= htmlspecialchars($edit_teacher['full_name']) ?>" required>
            </div>
            
            <div class="form-group">
              <label>Phone *</label>
              <input type="text" name="phone" value="<?= htmlspecialchars($edit_teacher['phone']) ?>" required>
            </div>
            <div class="form-group">
              <label>Subject *</label>
              <input type="text" name="subject" value="<?= htmlspecialchars($edit_teacher['subject']) ?>" required>
            </div>
            <div class="form-group">
              <label>Class Name *</label>
              <input type="text" name="class" value="<?= htmlspecialchars($edit_teacher['class']) ?>" required>
            </div>
            <div class="form-group">
              <label>Class ID *</label>
              <input type="number" name="class_id" value="<?= $edit_teacher['class_id'] ?>" required>
            </div>
            <div class="form-group">
              <label>Status *</label>
              <select name="status" required>
                <option value="active"   <?= $edit_teacher['status']==='active'   ? 'selected':'' ?>>✅ Active</option>
                <option value="inactive" <?= $edit_teacher['status']==='inactive' ? 'selected':'' ?>>❌ Inactive</option>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label>Photo</label>
              <div style="display:flex;align-items:center;gap:16px;margin-bottom:10px;">
                <div id="photo-preview-wrap" style="
                  width:80px;height:80px;border-radius:14px;overflow:hidden;
                  background:#E0F4FD;display:flex;align-items:center;
                  justify-content:center;font-size:32px;color:#4FC3F7;
                  flex-shrink:0;border:2px dashed #4FC3F7;">
                  <?php if (!empty($edit_teacher['photo'])): ?>
                    <img src="upload/teachers/<?= htmlspecialchars($edit_teacher['photo']) ?>"
                         style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.parentElement.innerHTML='📷'">
                  <?php else: ?>
                    <span>📷</span>
                  <?php endif; ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                  <label for="photo-input" style="
                    background:#4FC3F7;color:#fff;padding:9px 18px;
                    border-radius:10px;font-size:13px;font-weight:800;
                    cursor:pointer;display:inline-block;">📁 Choose Photo</label>
                  <input type="file" id="photo-input" name="photo"
                         accept="image/*" style="display:none;"
                         onchange="previewPhoto(this)">
                  <span id="photo-filename" style="font-size:12px;color:#8A9BB0;">
                    <?= !empty($edit_teacher['photo']) ? htmlspecialchars($edit_teacher['photo']) : 'No file chosen' ?>
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="edit_teacher" class="btn btn-primary">💾 Save Changes</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══════════════ PROFILE VIEW ══════════════ -->
    <?php elseif ($action === 'profile' && $profile_teacher): ?>
      <div class="profile-header">
        <?php if (!empty($profile_teacher['photo'])): ?>
          <img src="upload/teachers/<?= htmlspecialchars($profile_teacher['photo']) ?>"
               class="profile-photo"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <div class="profile-photo" style="display:none;"><?= strtoupper(substr($profile_teacher['name'],0,1)) ?></div>
        <?php else: ?>
          <div class="profile-photo"><?= strtoupper(substr($profile_teacher['name'],0,1)) ?></div>
        <?php endif; ?>
        <div style="flex:1;">
          <div class="profile-name"><?= htmlspecialchars($profile_teacher['full_name']) ?></div>
          <div class="profile-meta">
            <?= htmlspecialchars($profile_teacher['subject']) ?> &nbsp;·&nbsp;
            <?= htmlspecialchars($profile_teacher['class']) ?> &nbsp;·&nbsp;
            
            <span class="badge <?= $profile_teacher['status'] ?>"><?= ucfirst($profile_teacher['status']) ?></span>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <span class="lbl">📞 PHONE</span>
              <span class="val"><?= htmlspecialchars($profile_teacher['phone']) ?></span>
            </div>
            <div class="info-item">
              <span class="lbl">🏫 CLASS ID</span>
              <span class="val"><?= $profile_teacher['class_id'] ?></span>
            </div>
            <div class="info-item">
              <span class="lbl">📚 SUBJECT</span>
              <span class="val"><?= htmlspecialchars($profile_teacher['subject']) ?></span>
            </div>
            <div class="info-item">
              <span class="lbl">🏷️ STATUS</span>
              <span class="val"><span class="badge <?= $profile_teacher['status'] ?>"><?= ucfirst($profile_teacher['status']) ?></span></span>
            </div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <a href="?action=edit&id=<?= $profile_teacher['id'] ?>" class="btn btn-edit">✏️ Edit</a>
          <a href="?action=delete&id=<?= $profile_teacher['id'] ?>"
             class="btn btn-danger"
             onclick="return confirm('Delete this teacher?')">🗑 Delete</a>
        </div>
      </div>

      <!-- Teacher's Students -->
      <div class="card">
        <div class="card-title" style="margin-bottom:16px;">👧 Students in <?= htmlspecialchars($profile_teacher['class']) ?></div>
        <?php
          $cls = $conn->real_escape_string($profile_teacher['class']);
          $cls_students = $conn->query("SELECT * FROM students WHERE class='$cls' ORDER BY name ASC");
          if ($cls_students && mysqli_num_rows($cls_students) > 0):
        ?>
        <table>
          <thead><tr><th>Student</th><th>Age</th><th>Status</th><th>Parent Phone</th></tr></thead>
          <tbody>
            <?php while($s = mysqli_fetch_assoc($cls_students)): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="teacher-avatar"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                  <span style="font-weight:700;"><?= htmlspecialchars($s['name']) ?></span>
                </div>
              </td>
              <td><?= $s['age'] ?> yrs</td>
              <td><span class="badge <?= strtolower($s['status']) ?>"><?= ucfirst($s['status']) ?></span></td>
              <td><?= htmlspecialchars($s['parent_phone']) ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p style="color:var(--muted);text-align:center;padding:20px 0;">No students in this class yet.</p>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<script>
function showTab(tab) {
  document.getElementById('tab-teachers').style.display   = tab==='teachers'   ? 'block' : 'none';
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  event.target.classList.add('active');
  // update URL without reload
  history.replaceState(null,'','?action=list&tab='+tab);
}

function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('photo-preview-wrap').innerHTML =
        '<img src="'+e.target.result+'" style="width:100%;height:100%;object-fit:cover;">';
      document.getElementById('photo-filename').textContent = input.files[0].name;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>