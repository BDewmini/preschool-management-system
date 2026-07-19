<?php
session_start();
include 'db.php';

$user = $_SESSION['user'] ?? null;

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st_id  = $_POST['st_id'];
    $date   = $_POST['date'];
    $status = $_POST['status'];
    $note   = !empty($_POST['note']) ? $_POST['note'] : NULL;

    $stmt = $conn->prepare("INSERT INTO attendance (st_id, date, status, note) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $st_id, $date, $status, $note);
    if ($stmt->execute()) {
        $success = "Attendance marked successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
    $stmt->close();
}

// Fetch students with their class name for dropdown
$students = mysqli_query($conn, "
    SELECT s.st_id, s.name, c.class_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.class_id
    ORDER BY s.name
");

// Fetch today's attendance WITH class name
$today = date('Y-m-d');
$records = mysqli_query($conn, "
    SELECT a.*, s.name AS student_name, c.class_name
    FROM attendance a
    JOIN students s ON a.st_id = s.st_id
    LEFT JOIN classes c ON s.class_id = c.class_id
    WHERE a.date = '$today'
    ORDER BY a.a_id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Attendance – Pre School</title>
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
      min-height: 100vh;
      display: flex; flex-direction: column;
      position: fixed; top: 0; left: 0; z-index: 100;
    }
    .sidebar-brand {
      padding: 28px 24px 20px;
      display: flex; align-items: center; gap: 12px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .brand-icon {
      width: 42px; height: 42px;
      background: var(--sun); border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
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
    .nav a.active { background: rgba(79,195,247,0.2); color: var(--sky); }
    .nav a span.icon { font-size: 18px; width: 22px; text-align: center; }

    .sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.08); }
    .user-info { display: flex; align-items: center; gap: 10px; }
    .avatar {
      width: 36px; height: 36px; border-radius: 50%;
      background: var(--rose);
      display: flex; align-items: center; justify-content: center;
      font-weight: 800; color: #fff; font-size: 15px;
    }
    .user-name { font-size: 13px; font-weight: 700; color: #fff; }
    .user-role  { font-size: 11px; color: rgba(255,255,255,0.4); }
    .logout-btn {
      margin-left: auto;
      background: rgba(240,98,146,0.2); border: none; border-radius: 8px;
      padding: 6px 10px; cursor: pointer; color: var(--rose);
      font-size: 18px; transition: background .18s;
      text-decoration: none; display: flex; align-items: center;
    }
    .logout-btn:hover { background: rgba(240,98,146,0.4); }

    /* ── MAIN ── */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1; display: flex; flex-direction: column; min-height: 100vh;
    }

    /* ── TOPBAR ── */
    .topbar {
      background: var(--card); padding: 18px 32px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1px solid #E8EEF5;
      position: sticky; top: 0; z-index: 50;
    }
    .page-title { font-size: 22px; font-weight: 800; }
    .page-title span { color: var(--sky); }
    .date-badge {
      background: var(--bg); border-radius: 20px;
      padding: 6px 14px; font-size: 13px; font-weight: 600; color: var(--muted);
    }

    /* ── CONTENT ── */
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

    /* ── FORM CARD ── */
    .form-card {
      background: var(--card); border-radius: 18px;
      padding: 28px 32px; margin-bottom: 24px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    }
    .card-title { font-size: 16px; font-weight: 800; margin-bottom: 18px; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 13px; font-weight: 700; color: var(--muted); }
    .form-group input,
    .form-group select {
      padding: 10px 14px; border-radius: 10px;
      border: 1.5px solid #E0E8F0;
      font-family: 'Nunito', sans-serif;
      font-size: 14px; outline: none; color: var(--text);
      transition: border .18s;
    }
    .form-group input:focus,
    .form-group select:focus { border-color: var(--sky); }

    /* Student info box shown after selection */
    .student-info-box {
      display: none;
      background: #F0F7FF; border-radius: 10px;
      padding: 10px 14px; margin-top: 6px;
      font-size: 13px; color: var(--muted); font-weight: 600;
    }
    .student-info-box span { color: var(--text); font-weight: 700; }

    .submit-btn {
      margin-top: 20px;
      background: var(--sky); color: #fff;
      border: none; border-radius: 10px;
      padding: 12px 28px; font-size: 15px; font-weight: 800;
      cursor: pointer; font-family: 'Nunito', sans-serif;
      transition: background .18s; display: inline-flex; align-items: center; gap: 8px;
    }
    .submit-btn:hover { background: #0288D1; }

    /* ── TABLE ── */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 600px; }
    thead tr { background: var(--bg); }
    th {
      padding: 11px 14px; text-align: left;
      font-size: 12px; color: var(--muted); font-weight: 800;
      text-transform: uppercase; letter-spacing: .6px;
    }
    td { padding: 11px 14px; border-bottom: 1px solid #F0F4F8; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tbody tr { transition: background .12s; }
    tbody tr:hover { background: #F8FBFF; }

    /* class pill */
    .class-pill {
      display: inline-block; padding: 3px 10px;
      border-radius: 6px; font-size: 12px; font-weight: 700;
      background: #EDE7F6; color: var(--purple);
    }

    /* badges */
    .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; }
    .badge.present { background: #E8F5E9; color: var(--grass); }
    .badge.absent  { background: #FCE4EC; color: var(--rose); }
    .badge.late    { background: #FFF3CD; color: #F9A825; }
    .badge.excused { background: #E0F4FD; color: var(--sky); }

    .empty-msg { text-align: center; padding: 30px; color: var(--muted); font-size: 14px; font-weight: 600; }

    /* ── STATS ROW ── */
    .stats-row {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 14px; margin-bottom: 22px;
    }
    .stat-chip {
      background: var(--card); border-radius: 14px;
      padding: 16px 18px; display: flex; align-items: center; gap: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .stat-chip-icon {
      width: 40px; height: 40px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .stat-chip-num  { font-size: 1.5rem; font-weight: 800; line-height: 1; }
    .stat-chip-label{ font-size: .75rem; color: var(--muted); font-weight: 600; margin-top: 2px; }
    .chip-present .stat-chip-icon { background:#E8F5E9; }
    .chip-absent  .stat-chip-icon { background:#FCE4EC; }
    .chip-late    .stat-chip-icon { background:#FFF3CD; }
    .chip-excused .stat-chip-icon { background:#E0F4FD; }
    .chip-present .stat-chip-num { color: var(--grass); }
    .chip-absent  .stat-chip-num { color: var(--rose); }
    .chip-late    .stat-chip-num { color: #F9A825; }
    .chip-excused .stat-chip-num { color: var(--sky); }

    @media (max-width: 900px) {
      .stats-row { grid-template-columns: repeat(2,1fr); }
    }
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main { margin-left: 0; }
      .form-grid { grid-template-columns: 1fr; }
      .content { padding: 20px 16px; }
      .topbar { padding: 14px 16px; }
    }
  </style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<!-- ── SIDEBAR ── -->
<?php include 'sidebar.php'; ?>

<!-- ── MAIN ── -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="page-title">✅ <span>Attendance</span></div>
    <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
  </div>

  <div class="content">

    <?php if ($success): ?>
      <div class="alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php
    // Quick stats for today
    $cnt = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    $all_today = mysqli_query($conn,"SELECT status FROM attendance WHERE date='$today'");
    while($row=mysqli_fetch_assoc($all_today)) $cnt[$row['status']] = ($cnt[$row['status']]??0)+1;
    ?>
    <!-- Stats row -->
    <div class="stats-row">
      <div class="stat-chip chip-present">
        <div class="stat-chip-icon">✅</div>
        <div><div class="stat-chip-num"><?= $cnt['present'] ?></div><div class="stat-chip-label">Present</div></div>
      </div>
      <div class="stat-chip chip-absent">
        <div class="stat-chip-icon">❌</div>
        <div><div class="stat-chip-num"><?= $cnt['absent'] ?></div><div class="stat-chip-label">Absent</div></div>
      </div>
      <div class="stat-chip chip-late">
        <div class="stat-chip-icon">⏰</div>
        <div><div class="stat-chip-num"><?= $cnt['late'] ?></div><div class="stat-chip-label">Late</div></div>
      </div>
      <div class="stat-chip chip-excused">
        <div class="stat-chip-icon">📝</div>
        <div><div class="stat-chip-num"><?= $cnt['excused'] ?></div><div class="stat-chip-label">Excused</div></div>
      </div>
    </div>

    <!-- Mark Attendance Form -->
    <div class="form-card">
      <div class="card-title">📋 Mark Attendance</div>
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label>Student</label>
            <select name="st_id" id="studentSelect" required onchange="showClass(this)">
              <option value="">-- Select Student --</option>
              <?php
              $student_list = [];
              while($s = mysqli_fetch_assoc($students)):
                $student_list[] = $s;
              ?>
                <option value="<?= $s['st_id'] ?>"
                        data-class="<?= htmlspecialchars($s['class_name'] ?? 'No Class') ?>">
                  <?= htmlspecialchars($s['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
            <!-- Class info shown after student select -->
            <div class="student-info-box" id="classInfoBox">
              🏫 Class: <span id="classInfoText">—</span>
            </div>
          </div>

          <div class="form-group">
            <label>Date</label>
            <input type="date" name="date" value="<?= $today ?>" required>
          </div>

          <div class="form-group">
            <label>Status</label>
            <select name="status" required>
              <option value="present">✅ Present</option>
              <option value="absent">❌ Absent</option>
              <option value="late">⏰ Late</option>
              <option value="excused">📝 Excused</option>
            </select>
          </div>

          <div class="form-group">
            <label>Note (optional)</label>
            <input type="text" name="note" placeholder="e.g. Sick, Family event...">
          </div>
        </div>
        <button type="submit" class="submit-btn">✅ Mark Attendance</button>
      </form>
    </div>

    <!-- Today's Records -->
    <div class="form-card">
      <div class="card-title">📅 Today's Attendance</div>
      <div class="table-wrap">
        <?php if ($records && mysqli_num_rows($records) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Student</th>
              <th>Class</th><!-- ✅ NEW CLASS COLUMN -->
              <th>Date</th>
              <th>Status</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <?php $i=1; while($r = mysqli_fetch_assoc($records)): ?>
            <tr>
              <td style="color:var(--muted);font-weight:700"><?= $i++ ?></td>
              <td style="font-weight:700"><?= htmlspecialchars($r['student_name']) ?></td>
              <td><!-- ✅ CLASS NAME WITH PILL STYLE -->
                <?php if(!empty($r['class_name'])): ?>
                  <span class="class-pill"><?= htmlspecialchars($r['class_name']) ?></span>
                <?php else: ?>
                  <span style="color:var(--muted)">—</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted)"><?= $r['date'] ?></td>
              <td><span class="badge <?= strtolower($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
              <td style="color:var(--muted)"><?= htmlspecialchars($r['note'] ?? '—') ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-msg">📭 No attendance records for today yet.</div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
// Show class name below student dropdown when selected
function showClass(select) {
  const box  = document.getElementById('classInfoBox');
  const text = document.getElementById('classInfoText');
  const opt  = select.options[select.selectedIndex];
  if (select.value) {
    text.textContent = opt.getAttribute('data-class') || '—';
    box.style.display = 'block';
  } else {
    box.style.display = 'none';
  }
}
</script>
</body>
</html>
