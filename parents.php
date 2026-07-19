<?php
session_start();
include 'db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM parents WHERE parent_id = $id");
    header("Location: parents.php?toast=deleted");
    exit();
}

// Handle Insert / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name    = $conn->real_escape_string(trim($_POST['name']));
        $email   = $conn->real_escape_string(trim($_POST['email']));
        $phone   = $conn->real_escape_string(trim($_POST['phone']));
        $address = $conn->real_escape_string(trim($_POST['address']));
        $job     = $conn->real_escape_string(trim($_POST['occupation']));
        $conn->query("INSERT INTO parents (name, email, phone, address, occupation) VALUES ('$name','$email','$phone','$address','$job')");
        header("Location: parents.php?toast=added");
        exit();
    }
    if ($_POST['action'] === 'edit') {
        $id      = intval($_POST['id']);
        $name    = $conn->real_escape_string(trim($_POST['name']));
        $email   = $conn->real_escape_string(trim($_POST['email']));
        $phone   = $conn->real_escape_string(trim($_POST['phone']));
        $address = $conn->real_escape_string(trim($_POST['address']));
        $job     = $conn->real_escape_string(trim($_POST['occupation']));
        $conn->query("UPDATE parents SET name='$name', email='$email', phone='$phone', address='$address', occupation='$job' WHERE parent_id=$id");
        header("Location: parents.php?toast=updated");
        exit();
    }
}

// Fetch edit row
$editRow = null;
if (isset($_GET['edit'])) {
    $id  = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM parents WHERE parent_id = $id");
    if ($res && $res->num_rows > 0) {
        $editRow = $res->fetch_assoc();
    }
}

// Fetch all parents with search
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$where  = $search ? "WHERE name LIKE '%$search%' OR email LIKE '%$search%' OR occupation LIKE '%$search%'" : '';
$result = $conn->query("SELECT * FROM parents $where ORDER BY parent_id DESC");
$total  = $conn->query("SELECT COUNT(*) as c FROM parents")->fetch_assoc()['c'];
$jobs   = $conn->query("SELECT COUNT(DISTINCT occupation) as c FROM parents WHERE occupation != ''")->fetch_assoc()['c'];
$emails = $conn->query("SELECT COUNT(*) as c FROM parents WHERE email != ''")->fetch_assoc()['c'];

$user = $_SESSION['user'] ?? ['email' => 'Admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parents – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<style>
:root {
  --sun:       #FFB830;
  --sky:       #4FC3F7;
  --grass:     #66BB6A;
  --rose:      #c15e38;
  --purple:    #9575CD;
  --bg:        #F0F7FF;
  --card:      #FFFFFF;
  --text:      #2D3A4A;
  --muted:     #8A9BB0;
  --border:    #E0E8F0;
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
.nav a:hover  { background: rgba(255,255,255,0.1); color: #fff; }
.nav a.active { background: rgba(240,98,146,0.18); color: var(--rose); }
.nav a .icon  { font-size: 18px; width: 22px; text-align: center; }
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

/* ── TOPBAR ── */
.topbar {
  background: var(--card); padding: 18px 32px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid var(--border);
  position: sticky; top: 0; z-index: 50;
}
.page-title { font-size: 22px; font-weight: 800; }
.page-title span { color: var(--rose); }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.date-badge {
  background: var(--bg); border-radius: 20px;
  padding: 6px 14px; font-size: 13px; font-weight: 600; color: var(--muted);
}

/* ── BUTTONS ── */
.btn {
  padding: 9px 20px; border-radius: 10px;
  font-size: 14px; font-weight: 800; cursor: pointer; border: none;
  font-family: 'Nunito', sans-serif; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px; transition: all .18s;
}
.btn-primary { background: var(--rose); color: #fff; }
.btn-primary:hover { background: #e0527e; }
.btn-outline { background: transparent; color: var(--muted); border: 1.5px solid var(--border); }
.btn-outline:hover { border-color: var(--rose); color: var(--rose); }
.btn-danger  { background: #FCE4EC; color: var(--rose); }
.btn-danger:hover { background: var(--rose); color: #fff; }
.btn-edit    { background: #F0F7FF; color: var(--sky); }
.btn-edit:hover { background: var(--sky); color: #fff; }
.btn-sm { padding: 6px 14px; font-size: 12px; }

/* ── CONTENT ── */
.content { padding: 28px 32px; flex: 1; }

/* ── STAT CARDS ── */
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.stat-card {
  background: var(--card); border-radius: 16px; padding: 20px 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
  display: flex; align-items: center; gap: 16px;
  transition: transform .2s;
}
.stat-card:hover { transform: translateY(-3px); }
.stat-icon {
  width: 48px; height: 48px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center; font-size: 22px;
}
.stat-num   { font-size: 28px; font-weight: 900; line-height: 1; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; margin-top: 2px; }

/* ── TOOLBAR ── */
.toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
.search-box {
  padding: 10px 16px 10px 40px; border-radius: 10px;
  border: 1.5px solid var(--border);
  font-family: 'Nunito', sans-serif; font-size: 14px;
  outline: none; width: 280px; transition: border .18s;
  background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%238A9BB0' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398l3.85 3.85a1 1 0 0 0 1.415-1.415l-3.868-3.833zm-5.242 1.156a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11z'/%3E%3C/svg%3E") no-repeat 14px center;
}
.search-box:focus { border-color: var(--rose); }

/* ── TABLE CARD ── */
.table-card {
  background: var(--card); border-radius: 18px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05); overflow: hidden;
}
.table-card table { width: 100%; border-collapse: collapse; font-size: 14px; }
.table-card thead th {
  background: var(--bg); padding: 12px 18px; text-align: left;
  font-size: 11px; color: var(--muted); font-weight: 800;
  letter-spacing: .08em; text-transform: uppercase;
  border-bottom: 1px solid var(--border);
}
.table-card tbody tr { border-bottom: 1px solid #F0F4F8; transition: background .15s; }
.table-card tbody tr:last-child { border-bottom: none; }
.table-card tbody tr:hover td { background: #FAFCFF; }
.table-card td { padding: 13px 18px; vertical-align: middle; }

.p-avatar {
  width: 36px; height: 36px; border-radius: 10px;
  background: linear-gradient(135deg, var(--rose), #f48fb1);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 800; font-size: 15px; flex-shrink: 0;
}
.name-cell { display: flex; align-items: center; gap: 11px; }
.name-cell strong { display: block; font-weight: 700; font-size: 14px; }
.name-cell small  { color: var(--muted); font-size: 12px; }

.badge {
  display: inline-block; padding: 4px 12px;
  border-radius: 20px; font-size: 11px; font-weight: 800;
  background: #FCE4EC; color: #C62828;
}

.actions-cell { display: flex; gap: 7px; }

/* ── EMPTY ── */
.empty-state { text-align: center; padding: 52px 20px; }
.empty-state .ei { font-size: 52px; margin-bottom: 12px; }
.empty-state p   { color: var(--muted); font-weight: 600; }

/* ── MODAL ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(26,42,74,.45);
  backdrop-filter: blur(4px);
  z-index: 200; align-items: center; justify-content: center;
}
.modal-overlay.active { display: flex; }
.modal {
  background: #fff; border-radius: 20px; padding: 34px;
  width: 520px; max-width: 95vw;
  box-shadow: 0 24px 60px rgba(26,26,46,.18);
  animation: slideUp .22s ease;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}
.modal-header {
  display: flex; align-items: center;
  justify-content: space-between; margin-bottom: 24px;
}
.modal-header h2 { font-size: 18px; font-weight: 900; }
.modal-close {
  width: 32px; height: 32px; border-radius: 8px;
  border: 1.5px solid var(--border); background: none;
  cursor: pointer; font-size: 16px; color: var(--muted);
  display: flex; align-items: center; justify-content: center;
  transition: all .18s; text-decoration: none; font-weight: 800;
}
.modal-close:hover { background: #FCE4EC; color: var(--rose); border-color: var(--rose); }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 12px; font-weight: 800; color: var(--muted); letter-spacing: .05em; text-transform: uppercase; }
.form-group input,
.form-group textarea {
  padding: 10px 13px; border: 1.5px solid var(--border); border-radius: 10px;
  font-family: 'Nunito', sans-serif; font-size: 14px;
  color: var(--text); outline: none; transition: border .18s; background: #fafafa;
}
.form-group input:focus,
.form-group textarea:focus { border-color: var(--rose); background: #fff; }
.form-group textarea { resize: vertical; min-height: 72px; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  margin-top: 22px; padding-top: 18px;
  border-top: 1px solid var(--border);
}

/* ── ALERTS ── */
.alert-success {
  background: #E8F5E9; color: #388E3C;
  border-radius: 10px; padding: 12px 18px;
  font-weight: 700; margin-bottom: 16px;
}

/* ── TOAST ── */
.toast {
  position: fixed; bottom: 28px; right: 28px;
  background: #1a2a4a; color: #fff;
  padding: 13px 20px; border-radius: 11px;
  font-size: 14px; font-weight: 700;
  display: flex; align-items: center; gap: 9px;
  box-shadow: 0 8px 24px rgba(0,0,0,.18);
  transform: translateY(80px); opacity: 0;
  transition: all .3s ease; z-index: 999;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--grass); }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; }
  .stat-row { grid-template-columns: 1fr; }
  .form-grid { grid-template-columns: 1fr; }
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
    <div class="page-title">👨‍👩‍👧 <span>Parents</span></div>
    <div class="topbar-right">
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <button class="btn btn-primary" onclick="openModal('addModal')">➕ Add Parent</button>
    </div>
  </div>

  <div class="content">

    <!-- STATS -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-icon" style="background:#FCE4EC;">👨‍👩‍👧</div>
        <div>
          <div class="stat-num" style="color:var(--rose)"><?= $total ?></div>
          <div class="stat-label">Total Parents</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#EDE7F6;">💼</div>
        <div>
          <div class="stat-num" style="color:var(--purple)"><?= $jobs ?></div>
          <div class="stat-label">Unique Occupations</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#E0F4FD;">📧</div>
        <div>
          <div class="stat-num" style="color:var(--sky)"><?= $emails ?></div>
          <div class="stat-label">With Email</div>
        </div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar" style="margin-bottom:18px;">
      <form method="GET" style="display:flex; gap:8px; align-items:center;">
        <input type="text" name="search" class="search-box"
               placeholder="Search by name, email, occupation..."
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?>
          <a href="parents.php" class="btn btn-sm" style="background:#F0F4F8; color:var(--muted);">✕ Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- TABLE -->
    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Parent</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Occupation</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$result || $result->num_rows === 0): ?>
            <tr><td colspan="6">
              <div class="empty-state">
                <div class="ei">👨‍👩‍👧</div>
                <p>No parents found<?= $search ? ' for "'.htmlspecialchars($search).'"' : '' ?>. <a href="javascript:openModal('addModal')" style="color:var(--rose);">Add one!</a></p>
              </div>
            </td></tr>
          <?php else: ?>
            <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td style="color:var(--muted); font-size:12px;"><?= $i++ ?></td>
              <td>
                <div class="name-cell">
                  <div class="p-avatar"><?= strtoupper(substr($row['name'], 0, 1)) ?></div>
                  <div>
                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                    <small><?= htmlspecialchars($row['email']) ?></small>
                  </div>
                </div>
              </td>
              <td style="font-size:13px; color:var(--text);"><?= htmlspecialchars($row['phone']) ?></td>
              <td style="max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13px; color:var(--muted);">
                <?= htmlspecialchars($row['address']) ?>
              </td>
              <td><span class="badge">💼 <?= htmlspecialchars($row['occupation']) ?></span></td>
              <td>
                <div class="actions-cell">
                  <a href="?edit=<?= $row['parent_id'] ?>" class="btn btn-edit btn-sm">✏️ Edit</a>
                  <a href="?delete=<?= $row['parent_id'] ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete <?= htmlspecialchars($row['name'], ENT_QUOTES) ?>?')">🗑 Delete</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ── ADD MODAL ── -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h2>➕ Add New Parent</h2>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="form-group full">
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="e.g. Kumari Perera" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="email@example.com">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" placeholder="077 000 0000">
        </div>
        <div class="form-group">
          <label>Occupation</label>
          <input type="text" name="occupation" placeholder="e.g. Teacher">
        </div>
        <div class="form-group full">
          <label>Address</label>
          <textarea name="address" placeholder="Home address…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm" style="background:#F0F4F8; color:var(--muted);" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Parent</button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modal-overlay <?= $editRow ? 'active' : '' ?>" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h2>✏️ Edit Parent</h2>
      <a href="parents.php" class="modal-close">✕</a>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?= $editRow['parent_id'] ?? '' ?>">
      <div class="form-grid">
        <div class="form-group full">
          <label>Full Name *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editRow['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Occupation</label>
          <input type="text" name="occupation" value="<?= htmlspecialchars($editRow['occupation'] ?? '') ?>">
        </div>
        <div class="form-group full">
          <label>Address</label>
          <textarea name="address"><?= htmlspecialchars($editRow['address'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <a href="parents.php" class="btn btn-sm" style="background:#F0F4F8; color:var(--muted);">Cancel</a>
        <button type="submit" class="btn btn-primary">💾 Update Parent</button>
      </div>
    </form>
  </div>
</div>

<!-- ── TOAST ── -->
<div class="toast" id="toast">
  <div class="toast-dot"></div>
  <span id="toast-msg">Action completed!</span>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

document.querySelectorAll('.modal-overlay').forEach(function(o) {
  o.addEventListener('click', function(e) {
    if (e.target === this) closeModal(this.id);
  });
});

// Auto-search debounce
var si = document.querySelector('input[name="search"]'), st;
if (si) si.addEventListener('input', function() {
  clearTimeout(st);
  st = setTimeout(function() { si.form.submit(); }, 500);
});

// Toast
var msgs = { added:'✅ Parent added!', updated:'✅ Parent updated!', deleted:'🗑️ Parent deleted.' };
var key  = new URLSearchParams(location.search).get('toast');
if (key && msgs[key]) {
  var t = document.getElementById('toast');
  document.getElementById('toast-msg').textContent = msgs[key];
  t.classList.add('show');
  setTimeout(function(){ t.classList.remove('show'); }, 3500);
  history.replaceState(null,'','parents.php');
}
</script>
</body>
</html>
<?php $conn->close(); ?>

