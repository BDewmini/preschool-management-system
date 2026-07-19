<?php
session_start();
include 'db.php';

$user = $_SESSION['user'] ?? null;

$success = $error = '';
$action = $_GET['action'] ?? 'list';
$edit_payment = null;

// ── ADD ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $st_id     = (int)$_POST['st_id'];
    $amount    = $conn->real_escape_string($_POST['amount']);
    $month     = $conn->real_escape_string($_POST['month']);
    $paid_date = $conn->real_escape_string($_POST['paid_date']);
    $method    = $conn->real_escape_string($_POST['method']);
    $status    = $conn->real_escape_string($_POST['status']);
    $note      = $conn->real_escape_string($_POST['note']);

    $sql = "INSERT INTO payments (st_id, amount, month, paid_date, method, status, note)
            VALUES ($st_id, '$amount', '$month', '$paid_date', '$method', '$status', '$note')";
    if ($conn->query($sql)) {
        $success = "Payment recorded successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $action = 'list';
}

// ── EDIT SAVE ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_payment'])) {
    $id        = (int)$_POST['id'];
    $st_id     = (int)$_POST['st_id'];
    $amount    = $conn->real_escape_string($_POST['amount']);
    $month     = $conn->real_escape_string($_POST['month']);
    $paid_date = $conn->real_escape_string($_POST['paid_date']);
    $method    = $conn->real_escape_string($_POST['method']);
    $status    = $conn->real_escape_string($_POST['status']);
    $note      = $conn->real_escape_string($_POST['note']);

    $sql = "UPDATE payments SET
                st_id=$st_id, amount='$amount', month='$month',
                paid_date='$paid_date', method='$method',
                status='$status', note='$note'
            WHERE p_id=$id";
    if ($conn->query($sql)) {
        $success = "Payment updated successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $action = 'list';
}

// ── DELETE ────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($conn->query("DELETE FROM payments WHERE p_id=$id")) {
        $success = "Payment deleted.";
    } else {
        $error = "Delete failed: " . $conn->error;
    }
    $action = 'list';
}

// ── LOAD FOR EDIT ────────────────────────────────────
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT p.*, s.name as student_name FROM payments p LEFT JOIN students s ON p.st_id=s.st_id WHERE p.p_id=$id");
    $edit_payment = $res->fetch_assoc();
}

// ── PROFILE VIEW ─────────────────────────────────────
$profile_payment = null;
if ($action === 'profile' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $res = $conn->query("SELECT p.*, s.name as student_name FROM payments p LEFT JOIN students s ON p.st_id=s.st_id WHERE p.p_id=$id");
    $profile_payment = $res->fetch_assoc();
}

// ── FILTERS ──────────────────────────────────────────
$search        = $conn->real_escape_string($_GET['search'] ?? '');
$filter_status = $conn->real_escape_string($_GET['filter_status'] ?? '');
$filter_method = $conn->real_escape_string($_GET['filter_method'] ?? '');

$conditions = [];
if ($search)        $conditions[] = "(s.name LIKE '%$search%' OR p.month LIKE '%$search%' OR p.note LIKE '%$search%')";
if ($filter_status) $conditions[] = "p.status='$filter_status'";
if ($filter_method) $conditions[] = "p.method='$filter_method'";
$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$payments = $conn->query("SELECT p.*, s.name as student_name FROM payments p LEFT JOIN students s ON p.st_id=s.st_id $where ORDER BY p.paid_date DESC");

// Stats
$total_payments  = $conn->query("SELECT COUNT(*) as c FROM payments")->fetch_assoc()['c'];
$total_revenue   = $conn->query("SELECT SUM(amount) as s FROM payments WHERE status='Paid'")->fetch_assoc()['s'] ?? 0;
$pending_count   = $conn->query("SELECT COUNT(*) as c FROM payments WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
$overdue_count   = $conn->query("SELECT COUNT(*) as c FROM payments WHERE status='Overdue'")->fetch_assoc()['c'] ?? 0;
$pending_amount  = $conn->query("SELECT SUM(amount) as s FROM payments WHERE status='Pending'")->fetch_assoc()['s'] ?? 0;

// Students dropdown
$students_list = $conn->query("SELECT st_id, name FROM students ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments – Little Stars Pre School</title>
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
.nav a.active { background: rgba(102,187,106,0.18); color: var(--grass); }
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
.page-title span { color: var(--grass); }
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
.btn-primary { background: var(--grass); color: #fff; }
.btn-primary:hover { background: #43A047; }
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
.stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
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
.stat-num   { font-size: 22px; font-weight: 900; line-height: 1; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; margin-top: 2px; }

/* TABLE CARD */
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

/* Filter bar */
.filter-bar {
  display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
}
.search-box {
  padding: 9px 16px; border-radius: 10px;
  border: 1.5px solid #E0E8F0;
  font-family: 'Nunito', sans-serif; font-size: 14px;
  outline: none; width: 220px; transition: border .18s;
}
.search-box:focus { border-color: var(--grass); }
.filter-select {
  padding: 9px 14px; border-radius: 10px;
  border: 1.5px solid #E0E8F0;
  font-family: 'Nunito', sans-serif; font-size: 13px;
  font-weight: 700; outline: none; background: #fff;
  color: var(--text); transition: border .18s; cursor: pointer;
}
.filter-select:focus { border-color: var(--grass); }

table { width: 100%; border-collapse: collapse; font-size: 14px; }
th {
  background: var(--bg); padding: 10px 14px; text-align: left;
  font-size: 12px; color: var(--muted); font-weight: 800;
}
td { padding: 11px 14px; border-bottom: 1px solid #F0F4F8; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #FAFCFF; }

/* Method icons */
.method-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 8px;
  font-size: 12px; font-weight: 800;
}
.method-cash  { background: #E8F5E9; color: #2E7D32; }
.method-bank  { background: #E0F4FD; color: #0277BD; }
.method-card  { background: #EDE7F6; color: #6A1B9A; }

/* Status badges */
.badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; }
.badge-paid    { background: #E8F5E9; color: #2E7D32; }
.badge-pending { background: #FFF3CD; color: #F57F17; }
.badge-overdue { background: #FCE4EC; color: #C62828; }

/* Amount highlight */
.amount-paid    { color: #2E7D32; font-weight: 900; font-size: 15px; }
.amount-pending { color: #F57F17; font-weight: 900; font-size: 15px; }
.amount-overdue { color: #C62828; font-weight: 900; font-size: 15px; }

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
.form-group select,
.form-group textarea {
  padding: 10px 14px; border-radius: 10px;
  border: 1.5px solid #E0E8F0; font-family: 'Nunito', sans-serif;
  font-size: 14px; outline: none; transition: border .18s; color: var(--text); background: #fff;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color: var(--grass); }
.form-group textarea { resize: vertical; min-height: 70px; }
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
.profile-sub  { color: var(--muted); font-size: 14px; margin-top: 6px; }
.info-grid {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 14px; margin-top: 18px;
}
.info-item { display: flex; flex-direction: column; gap: 3px; }
.info-item .lbl { font-size: 11px; color: var(--muted); font-weight: 800; letter-spacing: .5px; }
.info-item .val { font-size: 15px; font-weight: 800; }

/* Note block */
.note-block {
  background: #FFF9EC; border-left: 4px solid var(--sun);
  border-radius: 10px; padding: 14px 18px;
  margin-top: 16px; font-size: 14px; line-height: 1.6;
  color: var(--text);
}

/* Empty */
.empty-state { text-align: center; padding: 50px 20px; }
.empty-state .ei { font-size: 52px; margin-bottom: 12px; }
.empty-state p   { color: var(--muted); font-weight: 600; }

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; }
  .form-grid, .stat-row, .info-grid { grid-template-columns: 1fr; }
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
    <div class="page-title">💳 <span>Payments</span></div>
    <div class="topbar-right">
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">➕ Add Payment</a>
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
          <div class="stat-icon" style="background:#E8F5E9;">💰</div>
          <div>
            <div class="stat-num" style="color:var(--grass)">Rs. <?= number_format($total_revenue, 2) ?></div>
            <div class="stat-label">Total Revenue (Paid)</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#E0F4FD;">💳</div>
          <div>
            <div class="stat-num" style="color:var(--sky)"><?= $total_payments ?></div>
            <div class="stat-label">Total Records</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#FFF3CD;">⏳</div>
          <div>
            <div class="stat-num" style="color:#F57F17"><?= $pending_count ?></div>
            <div class="stat-label">Pending Payments</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background:#FCE4EC;">⚠️</div>
          <div>
            <div class="stat-num" style="color:var(--rose)"><?= $overdue_count ?></div>
            <div class="stat-label">Overdue Payments</div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="card" style="padding:16px 20px; margin-bottom:20px;">
        <form method="GET" class="filter-bar">
          <input type="hidden" name="action" value="list">
          <input type="text" name="search" class="search-box"
                 placeholder="🔍 Search student, month, note..."
                 value="<?= htmlspecialchars($search) ?>">
          <select name="filter_status" class="filter-select">
            <option value="">All Statuses</option>
            <option value="Paid"    <?= $filter_status==='Paid'    ? 'selected':'' ?>>✅ Paid</option>
            <option value="Pending" <?= $filter_status==='Pending' ? 'selected':'' ?>>⏳ Pending</option>
            <option value="Overdue" <?= $filter_status==='Overdue' ? 'selected':'' ?>>⚠️ Overdue</option>
          </select>
          <select name="filter_method" class="filter-select">
            <option value="">All Methods</option>
            <option value="Cash"         <?= $filter_method==='Cash'         ? 'selected':'' ?>>💵 Cash</option>
            <option value="Bank Transfer" <?= $filter_method==='Bank Transfer' ? 'selected':'' ?>>🏦 Bank Transfer</option>
            <option value="Card"         <?= $filter_method==='Card'         ? 'selected':'' ?>>💳 Card</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <?php if ($search || $filter_status || $filter_method): ?>
            <a href="?action=list" class="btn btn-sm btn-back">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Payments Table -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">💳 Payment Records</div>
          <?php if ($payments): ?>
            <span style="font-size:13px; color:var(--muted);"><?= mysqli_num_rows($payments) ?> record(s)</span>
          <?php endif; ?>
        </div>

        <?php if ($payments && mysqli_num_rows($payments) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Month</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Paid Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($p = mysqli_fetch_assoc($payments)):
              $status_class = match($p['status']) {
                'Paid'    => 'badge-paid',
                'Pending' => 'badge-pending',
                'Overdue' => 'badge-overdue',
                default   => 'badge-pending',
              };
              $amount_class = match($p['status']) {
                'Paid'    => 'amount-paid',
                'Pending' => 'amount-pending',
                'Overdue' => 'amount-overdue',
                default   => 'amount-pending',
              };
              $method_class = match($p['method']) {
                'Cash'          => 'method-cash',
                'Bank Transfer' => 'method-bank',
                'Card'          => 'method-card',
                default         => 'method-cash',
              };
              $method_icon = match($p['method']) {
                'Cash'          => '💵',
                'Bank Transfer' => '🏦',
                'Card'          => '💳',
                default         => '💵',
              };
            ?>
            <tr>
              <td style="color:var(--muted); font-size:12px;"><?= $p['p_id'] ?></td>
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:34px; height:34px; border-radius:50%; background:#E8F5E9; color:var(--grass); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; border:2px solid #C8E6C9;">
                    <?= strtoupper(substr($p['student_name'] ?? '?', 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;"><?= htmlspecialchars($p['student_name'] ?? 'Unknown') ?></div>
                    <div style="font-size:12px; color:var(--muted);">ID: <?= $p['st_id'] ?></div>
                  </div>
                </div>
              </td>
              <td style="font-weight:700;"><?= htmlspecialchars($p['month']) ?></td>
              <td>
                <span class="<?= $amount_class ?>">Rs. <?= number_format($p['amount'], 2) ?></span>
              </td>
              <td>
                <span class="method-badge <?= $method_class ?>">
                  <?= $method_icon ?> <?= htmlspecialchars($p['method']) ?>
                </span>
              </td>
              <td style="font-size:13px; color:var(--muted);">
                <?= $p['paid_date'] ? date('d M Y', strtotime($p['paid_date'])) : '—' ?>
              </td>
              <td>
                <span class="badge <?= $status_class ?>"><?= htmlspecialchars($p['status']) ?></span>
              </td>
              <td>
                <div style="display:flex; gap:6px;">
                  <a href="?action=profile&id=<?= $p['p_id'] ?>" class="btn btn-edit btn-sm">👁</a>
                  <a href="?action=edit&id=<?= $p['p_id'] ?>" class="btn btn-edit btn-sm">✏️</a>
                  <a href="?action=delete&id=<?= $p['p_id'] ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete this payment record?')">🗑</a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
          <div class="ei">💳</div>
          <p>No payment records found. <a href="?action=add" style="color:var(--grass);">Add one!</a></p>
        </div>
        <?php endif; ?>
      </div>

    <!-- ══════════════ ADD ══════════════ -->
    <?php elseif ($action === 'add'): ?>
      <div class="form-card">
        <div class="form-title">➕ Add New Payment</div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group full">
              <label>Student *</label>
              <select name="st_id" required>
                <option value="">-- Select Student --</option>
                <?php
                  mysqli_data_seek($students_list, 0);
                  while ($s = mysqli_fetch_assoc($students_list)):
                ?>
                  <option value="<?= $s['st_id'] ?>"><?= htmlspecialchars($s['name']) ?> (ID: <?= $s['st_id'] ?>)</option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Month *</label>
              <input type="text" name="month" placeholder="e.g. June 2026" required>
            </div>
            <div class="form-group">
              <label>Amount (Rs.) *</label>
              <input type="number" name="amount" placeholder="e.g. 5000.00" step="0.01" min="0" required>
            </div>
            <div class="form-group">
              <label>Payment Method *</label>
              <select name="method" required>
                <option value="Cash">💵 Cash</option>
                <option value="Bank Transfer">🏦 Bank Transfer</option>
                <option value="Card">💳 Card</option>
              </select>
            </div>
            <div class="form-group">
              <label>Paid Date</label>
              <input type="date" name="paid_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
              <label>Status *</label>
              <select name="status" required>
                <option value="Paid">✅ Paid</option>
                <option value="Pending">⏳ Pending</option>
                <option value="Overdue">⚠️ Overdue</option>
              </select>
            </div>
            <div class="form-group full">
              <label>Note</label>
              <textarea name="note" placeholder="Optional note..."></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="add_payment" class="btn btn-primary">💾 Record Payment</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══════════════ EDIT ══════════════ -->
    <?php elseif ($action === 'edit' && $edit_payment): ?>
      <div class="form-card">
        <div class="form-title">✏️ Edit Payment</div>
        <form method="POST">
          <input type="hidden" name="id" value="<?= $edit_payment['p_id'] ?>">
          <div class="form-grid">
            <div class="form-group full">
              <label>Student *</label>
              <select name="st_id" required>
                <?php
                  mysqli_data_seek($students_list, 0);
                  while ($s = mysqli_fetch_assoc($students_list)):
                ?>
                  <option value="<?= $s['st_id'] ?>" <?= $edit_payment['st_id'] == $s['st_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?> (ID: <?= $s['st_id'] ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Month *</label>
              <input type="text" name="month" value="<?= htmlspecialchars($edit_payment['month']) ?>" required>
            </div>
            <div class="form-group">
              <label>Amount (Rs.) *</label>
              <input type="number" name="amount" value="<?= $edit_payment['amount'] ?>" step="0.01" min="0" required>
            </div>
            <div class="form-group">
              <label>Payment Method *</label>
              <select name="method" required>
                <?php foreach (['Cash','Bank Transfer','Card'] as $m): ?>
                <option value="<?= $m ?>" <?= $edit_payment['method'] === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Paid Date</label>
              <input type="date" name="paid_date" value="<?= $edit_payment['paid_date'] ?>">
            </div>
            <div class="form-group">
              <label>Status *</label>
              <select name="status" required>
                <?php foreach (['Paid','Pending','Overdue'] as $st): ?>
                <option value="<?= $st ?>" <?= $edit_payment['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full">
              <label>Note</label>
              <textarea name="note"><?= htmlspecialchars($edit_payment['note'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" name="edit_payment" class="btn btn-primary">💾 Save Changes</button>
            <a href="?action=list" class="btn btn-back">Cancel</a>
          </div>
        </form>
      </div>

    <!-- ══════════════ PROFILE ══════════════ -->
    <?php elseif ($action === 'profile' && $profile_payment): ?>
      <?php
        $status_class = match($profile_payment['status']) {
          'Paid'    => 'badge-paid',
          'Pending' => 'badge-pending',
          'Overdue' => 'badge-overdue',
          default   => 'badge-pending',
        };
        $icon_bg = match($profile_payment['status']) {
          'Paid'    => '#E8F5E9',
          'Pending' => '#FFF3CD',
          'Overdue' => '#FCE4EC',
          default   => '#FFF3CD',
        };
        $icon_emoji = match($profile_payment['status']) {
          'Paid'    => '✅',
          'Pending' => '⏳',
          'Overdue' => '⚠️',
          default   => '💳',
        };
      ?>
      <div class="profile-header">
        <div class="profile-icon" style="background:<?= $icon_bg ?>;"><?= $icon_emoji ?></div>
        <div style="flex:1;">
          <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <div class="profile-name">Payment #<?= $profile_payment['p_id'] ?></div>
            <span class="badge <?= $status_class ?>"><?= htmlspecialchars($profile_payment['status']) ?></span>
          </div>
          <div class="profile-sub">
            👧 <?= htmlspecialchars($profile_payment['student_name'] ?? 'Unknown') ?> &nbsp;·&nbsp;
            <?= htmlspecialchars($profile_payment['month']) ?>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <span class="lbl">💰 AMOUNT</span>
              <span class="val" style="color:var(--grass); font-size:18px;">Rs. <?= number_format($profile_payment['amount'], 2) ?></span>
            </div>
            <div class="info-item">
              <span class="lbl">💳 METHOD</span>
              <span class="val"><?= htmlspecialchars($profile_payment['method']) ?></span>
            </div>
            <div class="info-item">
              <span class="lbl">📅 PAID DATE</span>
              <span class="val"><?= $profile_payment['paid_date'] ? date('d M Y', strtotime($profile_payment['paid_date'])) : '—' ?></span>
            </div>
            <div class="info-item">
              <span class="lbl">📆 MONTH</span>
              <span class="val"><?= htmlspecialchars($profile_payment['month']) ?></span>
            </div>
          </div>
          <?php if (!empty($profile_payment['note'])): ?>
          <div class="note-block">
            📝 <strong>Note:</strong> <?= nl2br(htmlspecialchars($profile_payment['note'])) ?>
          </div>
          <?php endif; ?>
        </div>
        <div style="display:flex; flex-direction:column; gap:8px; flex-shrink:0;">
          <a href="?action=edit&id=<?= $profile_payment['p_id'] ?>" class="btn btn-edit">✏️ Edit</a>
          <a href="?action=delete&id=<?= $profile_payment['p_id'] ?>"
             class="btn btn-danger"
             onclick="return confirm('Delete this payment?')">🗑 Delete</a>
        </div>
      </div>

      <!-- Other payments by same student -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">📋 Other Payments – <?= htmlspecialchars($profile_payment['student_name'] ?? '') ?></div>
        </div>
        <?php
          $sid = (int)$profile_payment['st_id'];
          $cur = (int)$profile_payment['p_id'];
          $others = $conn->query("SELECT * FROM payments WHERE st_id=$sid AND p_id!=$cur ORDER BY paid_date DESC");
        ?>
        <?php if ($others && mysqli_num_rows($others) > 0): ?>
        <table>
          <thead>
            <tr><th>Month</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php while ($o = mysqli_fetch_assoc($others)):
              $oc = match($o['status']) { 'Paid'=>'badge-paid','Pending'=>'badge-pending','Overdue'=>'badge-overdue',default=>'badge-pending' };
            ?>
            <tr>
              <td style="font-weight:700;"><?= htmlspecialchars($o['month']) ?></td>
              <td style="font-weight:800;">Rs. <?= number_format($o['amount'],2) ?></td>
              <td><?= htmlspecialchars($o['method']) ?></td>
              <td style="color:var(--muted); font-size:13px;"><?= $o['paid_date'] ? date('d M Y', strtotime($o['paid_date'])) : '—' ?></td>
              <td><span class="badge <?= $oc ?>"><?= $o['status'] ?></span></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p style="color:var(--muted); text-align:center; padding:20px 0;">No other payment records for this student.</p>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->
</body>
</html>
