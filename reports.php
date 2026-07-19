<?php
session_start();
include 'db.php';

$user = $_SESSION['user'] ?? null;

// ── FETCH STATS ──────────────────────────────────────
$total_students  = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'] ?? 0;
$total_teachers  = $conn->query("SELECT COUNT(*) as c FROM teachers")->fetch_assoc()['c'] ?? 0;
$total_classes   = $conn->query("SELECT COUNT(*) as c FROM classes")->fetch_assoc()['c'] ?? 0;
$total_parents   = $conn->query("SELECT COUNT(*) as c FROM parents")->fetch_assoc()['c'] ?? 0;
$total_capacity  = $conn->query("SELECT SUM(capacity) as c FROM classes")->fetch_assoc()['c'] ?? 0;
$active_students = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='active'")->fetch_assoc()['c'] ?? 0;

// ── ENROLLMENT PER CLASS ──────────────────────────────
$enrollment_res = $conn->query("
    SELECT c.class_name, COUNT(s.st_id) as enrolled, c.capacity
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.class_id
    GROUP BY c.class_id, c.class_name, c.capacity
    ORDER BY enrolled DESC
");
$enrollment_labels = [];
$enrollment_data   = [];
$enrollment_cap    = [];
while ($row = mysqli_fetch_assoc($enrollment_res)) {
    $enrollment_labels[] = $row['class_name'];
    $enrollment_data[]   = (int)$row['enrolled'];
    $enrollment_cap[]    = (int)$row['capacity'];
}

// ── ATTENDANCE LAST 7 DAYS ───────────────────────────
$attendance_labels = [];
$attendance_present = [];
$attendance_absent  = [];
for ($d = 6; $d >= 0; $d--) {
    $date = date('Y-m-d', strtotime("-$d days"));
    $label = date('D', strtotime($date));
    $attendance_labels[] = $label;
    $present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date='$date' AND status='present'")->fetch_assoc()['c'] ?? 0;
    $absent  = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date='$date' AND status='absent'")->fetch_assoc()['c'] ?? 0;
    $attendance_present[] = (int)$present;
    $attendance_absent[]  = (int)$absent;
}

// ── PAYMENTS THIS MONTH ──────────────────────────────
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');
$paid_amount = $conn->query("SELECT SUM(amount) as t FROM payments WHERE status='paid' AND paid_date BETWEEN '$month_start' AND '$month_end'")->fetch_assoc()['t'] ?? 0;
$pending_amount = $conn->query("SELECT SUM(amount) as t FROM payments WHERE status='pending'")->fetch_assoc()['t'] ?? 0;

// ── STUDENTS BY AGE GROUP ────────────────────────────
$age_res = $conn->query("
    SELECT c.age_group, COUNT(s.st_id) as cnt
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.class_id
    GROUP BY c.age_group
    ORDER BY cnt DESC
");
$age_labels = [];
$age_data   = [];
while ($row = mysqli_fetch_assoc($age_res)) {
    $age_labels[] = $row['age_group'];
    $age_data[]   = (int)$row['cnt'];
}

// ── REPORT TABLE (st_id, name, activity_name, a_id, p_id, paid_date) ──
$sel_month  = $_GET['report_month'] ?? date('Y-m');
$rpt_start  = $sel_month . '-01';
$rpt_end    = date('Y-m-t', strtotime($rpt_start));
$search_st  = $conn->real_escape_string($_GET['search_st'] ?? '');
$search_where = $search_st ? "AND (r.name LIKE '%$search_st%' OR r.st_id LIKE '%$search_st%')" : '';

$report_res = $conn->query("
    SELECT
        r.st_id,
        r.name,
        r.activity_name,
        r.a_id,
        r.p_id,
        r.paid_date,
        s.status   AS student_status,
        c.class_name
    FROM report r
    LEFT JOIN students s ON s.st_id   = r.st_id
    LEFT JOIN classes  c ON c.class_id = s.class_id
    WHERE (r.paid_date BETWEEN '$rpt_start' AND '$rpt_end' OR r.paid_date IS NULL)
    $search_where
    ORDER BY r.st_id ASC
");

// Chart data from report table
$chart_report = $conn->query("
    SELECT r.name,
           COUNT(DISTINCT r.a_id) as act_count,
           COUNT(DISTINCT r.p_id) as pay_count
    FROM report r
    WHERE r.paid_date BETWEEN '$rpt_start' AND '$rpt_end' AND r.paid_date != '0000-00-00'
    GROUP BY r.st_id, r.name
    ORDER BY act_count DESC
    LIMIT 8
");
$rpt_chart_labels = [];
$rpt_chart_acts   = [];
$rpt_chart_pays   = [];
while ($rc = mysqli_fetch_assoc($chart_report)) {
    $rpt_chart_labels[] = $rc['name'];
    $rpt_chart_acts[]   = (int)$rc['act_count'];
    $rpt_chart_pays[]   = (int)$rc['pay_count'];
}

// Paid vs Not paid this month from report table
$paid_count    = $conn->query("SELECT COUNT(*) as c FROM report WHERE paid_date BETWEEN '$rpt_start' AND '$rpt_end'")->fetch_assoc()['c'] ?? 0;
$notpaid_count = $conn->query("SELECT COUNT(*) as c FROM report WHERE paid_date IS NULL OR paid_date NOT BETWEEN '$rpt_start' AND '$rpt_end'")->fetch_assoc()['c'] ?? 0;

// ── CLASS-WISE ENROLLMENT SUMMARY TABLE ──────────────
$class_summary_res = $conn->query("
    SELECT
        c.class_id,
        c.class_name,
        c.age_group,
        c.capacity,
        COUNT(s.st_id) as enrolled,
        SUM(CASE WHEN s.status='active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN s.status='inactive' THEN 1 ELSE 0 END) as inactive_count,
        t.full_name as teacher_name
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.class_id
    LEFT JOIN teachers t ON t.class_id = c.class_id
    GROUP BY c.class_id, c.class_name, c.age_group, c.capacity, t.full_name
    ORDER BY c.class_name ASC
");

// ── MONTHLY PAYMENTS TREND (last 6 months, based on latest data) ──
// Find the most recent paid_date in the table; fall back to today if none.
$latest_paid_row = $conn->query("SELECT MAX(paid_date) as latest FROM payments WHERE status='Paid'")->fetch_assoc();
$trend_anchor = $latest_paid_row['latest'] ?? date('Y-m-d');

$pay_labels = [];
$pay_data   = [];
for ($m = 5; $m >= 0; $m--) {
    $lbl = date('M Y', strtotime("$trend_anchor -$m months"));
    $ms  = date('Y-m-01', strtotime("$trend_anchor -$m months"));
    $me  = date('Y-m-t',  strtotime("$trend_anchor -$m months"));
    $pay_labels[] = $lbl;
    $total = $conn->query("SELECT SUM(amount) as t FROM payments WHERE status='Paid' AND paid_date BETWEEN '$ms' AND '$me'")->fetch_assoc()['t'] ?? 0;
    $pay_data[] = (float)$total;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports – Little Stars Pre School</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Fredoka+One&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
.btn-sm { padding: 6px 14px; font-size: 12px; }

/* CONTENT */
.content { padding: 28px 32px; flex: 1; }

/* STAT CARDS */
.stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
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

/* CHART GRID */
.charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.charts-row-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }

/* CHART CARD */
.chart-card {
  background: var(--card); border-radius: 18px;
  padding: 24px 28px; box-shadow: 0 2px 12px rgba(0,0,0,.05);
}
.chart-card-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
}
.chart-card-title { font-size: 15px; font-weight: 800; }
.chart-badge {
  padding: 4px 12px; border-radius: 20px;
  font-size: 11px; font-weight: 800;
  background: var(--bg); color: var(--muted);
}
.chart-wrap { position: relative; width: 100%; }
.chart-wrap canvas { width: 100% !important; }

/* SUMMARY ROW */
.summary-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
.summary-card {
  background: var(--card); border-radius: 16px; padding: 20px 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
  display: flex; flex-direction: column; gap: 6px;
}
.summary-label { font-size: 12px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
.summary-val { font-size: 22px; font-weight: 900; }
.summary-sub { font-size: 12px; color: var(--muted); font-weight: 600; }

/* PROGRESS BAR */
.progress-bar {
  height: 8px; background: #E0E8F0; border-radius: 99px; overflow: hidden; margin-top: 8px;
}
.progress-fill { height: 100%; border-radius: 99px; }

@media (max-width: 900px) {
  .charts-row, .charts-row-3 { grid-template-columns: 1fr; }
  .stat-row { grid-template-columns: repeat(2, 1fr); }
  .summary-row { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; }
  .stat-row { grid-template-columns: 1fr; }
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
    <div class="page-title">📊 <span>Reports</span></div>
    <div class="topbar-right">
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <button class="btn btn-primary btn-sm" onclick="window.print()">🖨️ Print Report</button>
    </div>
  </div>

  <div class="content">

    <!-- ── OVERVIEW STATS ── -->
    <div class="stat-row">
      <div class="stat-card">
        <div class="stat-icon" style="background:#E0F4FD;">👧</div>
        <div>
          <div class="stat-num" style="color:var(--sky)"><?= $total_students ?></div>
          <div class="stat-label">Total Students</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#E8F5E9;">👩‍🏫</div>
        <div>
          <div class="stat-num" style="color:var(--grass)"><?= $total_teachers ?></div>
          <div class="stat-label">Total Teachers</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#FFF3CD;">🏫</div>
        <div>
          <div class="stat-num" style="color:var(--sun)"><?= $total_classes ?></div>
          <div class="stat-label">Total Classes</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#FCE4EC;">👨‍👩‍👧</div>
        <div>
          <div class="stat-num" style="color:var(--rose)"><?= $total_parents ?></div>
          <div class="stat-label">Total Parents</div>
        </div>
      </div>
    </div>

    <!-- ── SUMMARY CARDS ── -->
    <div class="summary-row">
      <div class="summary-card">
        <div class="summary-label">💰 Payments Collected (This Month)</div>
        <div class="summary-val" style="color:var(--grass)">LKR <?= number_format($paid_amount, 2) ?></div>
        <div class="summary-sub">Paid invoices for <?= date('F Y') ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label">⏳ Pending Payments</div>
        <div class="summary-val" style="color:var(--rose)">LKR <?= number_format($pending_amount, 2) ?></div>
        <div class="summary-sub">Unpaid / outstanding invoices</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">🪑 Capacity Utilisation</div>
        <?php $util = $total_capacity > 0 ? round(($total_students / $total_capacity) * 100) : 0; ?>
        <div class="summary-val" style="color:var(--purple)"><?= $util ?>%</div>
        <div class="summary-sub"><?= $total_students ?> students / <?= $total_capacity ?> seats</div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $util ?>%; background:<?= $util >= 90 ? 'var(--rose)' : ($util >= 70 ? 'var(--sun)' : 'var(--grass)') ?>;"></div>
        </div>
      </div>
    </div>

    <!-- ── CHART ROW 1: Enrollment Bar + Attendance Line ── -->
    <div class="charts-row">

      <!-- Enrollment per class -->
      <div class="chart-card">
        <div class="chart-card-header">
          <div class="chart-card-title">🏫 Enrollment per Class</div>
          <span class="chart-badge">Current</span>
        </div>
        <div class="chart-wrap" style="height:260px;">
          <canvas id="enrollmentChart"></canvas>
        </div>
      </div>

      <!-- Attendance last 7 days -->
      <div class="chart-card">
        <div class="chart-card-header">
          <div class="chart-card-title">✅ Attendance – Last 7 Days</div>
          <span class="chart-badge">Weekly</span>
        </div>
        <div class="chart-wrap" style="height:260px;">
          <canvas id="attendanceChart"></canvas>
        </div>
      </div>

    </div>

    <!-- ── CHART ROW 2: Payment Trend + Donut ── -->
    <div class="charts-row-3">

      <!-- Monthly payment trend -->
      <div class="chart-card">
        <div class="chart-card-header">
          <div class="chart-card-title">💳 Monthly Payment Trend</div>
          <span class="chart-badge">Last 6 Months</span>
        </div>
        <div class="chart-wrap" style="height:240px;">
          <canvas id="paymentChart"></canvas>
        </div>
      </div>

      <!-- Students by age group donut -->
      <div class="chart-card">
        <div class="chart-card-header">
          <div class="chart-card-title">👶 Students by Age Group</div>
          <span class="chart-badge">Distribution</span>
        </div>
        <div class="chart-wrap" style="height:240px; display:flex; align-items:center; justify-content:center;">
          <canvas id="ageChart"></canvas>
        </div>
      </div>

    </div>

    <!-- ── CLASS-WISE ENROLLMENT SUMMARY TABLE ── -->
    <div class="chart-card" style="margin-bottom:24px;">
      <div class="chart-card-header">
        <div class="chart-card-title">🏫 Class-wise Enrollment Summary</div>
        <span class="chart-badge">All Classes</span>
      </div>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Class Name</th>
              <th>Age Group</th>
              <th>Teacher</th>
              <th>Enrolled</th>
              <th>Active</th>
              <th>Inactive</th>
              <th>Capacity</th>
              <th>Available</th>
              <th>Utilisation</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $accent_colors = ['#4FC3F7','#66BB6A','#F06292','#FFB830','#9575CD','#26C6DA','#FF8A65'];
            $icons = ['🦋','🌈','🌟','🌻'];
            $row_i = 0;
            if ($class_summary_res && mysqli_num_rows($class_summary_res) > 0):
              while ($cr = mysqli_fetch_assoc($class_summary_res)):
                $ac      = $accent_colors[$row_i % count($accent_colors)];
                $ic      = $icons[$row_i % count($icons)];
                $enrolled = (int)$cr['enrolled'];
                $cap      = max(1, (int)$cr['capacity']);
                $avail    = $cap - $enrolled;
                $pct      = min(100, round(($enrolled / $cap) * 100));
                $fill     = $pct >= 90 ? '#F06292' : ($pct >= 70 ? '#FFB830' : '#66BB6A');
                $row_i++;
            ?>
            <tr>
              <td style="color:var(--muted); font-size:12px;"><?= $row_i ?></td>
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:34px; height:34px; border-radius:10px; background:<?= $ac ?>22; color:<?= $ac ?>; display:flex; align-items:center; justify-content:center; font-size:16px;"><?= $ic ?></div>
                  <span style="font-weight:800;"><?= htmlspecialchars($cr['class_name']) ?></span>
                </div>
              </td>
              <td><span class="badge badge-sky">👶 <?= htmlspecialchars($cr['age_group']) ?></span></td>
              <td style="font-weight:700;"><?= $cr['teacher_name'] ? htmlspecialchars($cr['teacher_name']) : '<span style="color:var(--muted);">—</span>' ?></td>
              <td><span style="font-size:16px; font-weight:900; color:<?= $ac ?>;"><?= $enrolled ?></span></td>
              <td><span class="badge badge-grass"><?= (int)$cr['active_count'] ?></span></td>
              <td><span class="badge badge-rose"><?= (int)$cr['inactive_count'] ?></span></td>
              <td style="font-weight:700;"><?= $cr['capacity'] ?></td>
              <td><span style="font-weight:800; color:<?= $avail > 0 ? 'var(--grass)' : 'var(--rose)' ?>;"><?= $avail > 0 ? $avail : 'Full' ?></span></td>
              <td style="min-width:120px;">
                <div style="display:flex; align-items:center; gap:8px;">
                  <div style="flex:1; height:8px; background:#E0E8F0; border-radius:99px; overflow:hidden;">
                    <div style="height:100%; width:<?= $pct ?>%; background:<?= $fill ?>; border-radius:99px;"></div>
                  </div>
                  <span style="font-size:11px; font-weight:800; color:var(--muted);"><?= $pct ?>%</span>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr><td colspan="10" style="text-align:center; padding:40px; color:var(--muted);">🏫 No classes found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── REPORT TABLE CHARTS ── -->
    <div class="charts-row" style="margin-bottom:20px;">
      <div class="chart-card">
        <div class="chart-card-header">
          <div class="chart-card-title">🎯 Activities & Payments per Student</div>
          <span class="chart-badge"><?= date('F Y', strtotime($rpt_start)) ?></span>
        </div>
        <div class="chart-wrap" style="height:240px;">
          <canvas id="rptBarChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-card-header">
          <div class="chart-card-title">💳 Payment Status</div>
          <span class="chart-badge"><?= date('F Y', strtotime($rpt_start)) ?></span>
        </div>
        <div class="chart-wrap" style="height:240px; display:flex; align-items:center; justify-content:center;">
          <canvas id="rptDonut"></canvas>
        </div>
      </div>
    </div>

    <!-- ── MAIN REPORT TABLE ── -->
    <div class="chart-card" style="margin-bottom:24px;">
      <div class="chart-card-header" style="flex-wrap:wrap; gap:10px;">
        <div class="chart-card-title">📋 Student Report – <span style="color:var(--sky)"><?= date('F Y', strtotime($rpt_start)) ?></span></div>
        <form method="GET" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <input type="text" name="search_st" placeholder="🔍 Search name / ID..."
                 value="<?= htmlspecialchars($search_st) ?>"
                 style="padding:7px 14px; border-radius:10px; border:1.5px solid #E0E8F0;
                        font-family:'Nunito',sans-serif; font-size:13px; font-weight:700;
                        color:var(--text); outline:none; width:190px;">
          <input type="month" name="report_month"
                 value="<?= htmlspecialchars($sel_month) ?>"
                 style="padding:7px 14px; border-radius:10px; border:1.5px solid #E0E8F0;
                        font-family:'Nunito',sans-serif; font-size:13px; font-weight:700;
                        color:var(--text); outline:none; cursor:pointer;">
          <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
          <?php if ($search_st): ?>
            <a href="?report_month=<?= htmlspecialchars($sel_month) ?>" class="btn btn-sm" style="background:#F0F4F8; color:var(--muted);">✕ Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>ST ID</th>
              <th>Student Name</th>
              <th>Class</th>
              <th>Activity Name</th>
              <th>Activity ID</th>
              <th>Payment ID</th>
              <th>Paid Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $colors = ['#4FC3F7','#66BB6A','#F06292','#FFB830','#9575CD','#26C6DA','#FF8A65'];
            $ri = 0;
            if ($report_res && mysqli_num_rows($report_res) > 0):
              while ($rr = mysqli_fetch_assoc($report_res)):
                $clr     = $colors[$ri % count($colors)];
                $has_paid = !empty($rr['paid_date']) && $rr['paid_date'] !== '0000-00-00';
                $ri++;
            ?>
            <tr>
              <td style="font-size:12px; color:var(--muted);"><?= $ri ?></td>
              <td style="font-weight:800; color:var(--sky);">#<?= htmlspecialchars($rr['st_id']) ?></td>
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <div style="width:34px; height:34px; border-radius:50%;
                              background:<?= $clr ?>22; color:<?= $clr ?>;
                              display:flex; align-items:center; justify-content:center;
                              font-weight:900; font-size:14px; border:2px solid <?= $clr ?>44;">
                    <?= strtoupper(substr($rr['name'] ?? 'S', 0, 1)) ?>
                  </div>
                  <span style="font-weight:800;"><?= htmlspecialchars($rr['name'] ?? '—') ?></span>
                </div>
              </td>
              <td>
                <?php if (!empty($rr['class_name'])): ?>
                  <span class="badge badge-sky">🏫 <?= htmlspecialchars($rr['class_name']) ?></span>
                <?php else: ?><span style="color:var(--muted);">—</span><?php endif; ?>
              </td>
              <td style="font-weight:700; color:var(--purple);">🎯 <?= htmlspecialchars($rr['activity_name'] ?? '—') ?></td>
              <td style="text-align:center; font-weight:800; color:var(--muted);"><?= $rr['a_id'] ? '#'.$rr['a_id'] : '—' ?></td>
              <td style="text-align:center; font-weight:800; color:var(--muted);"><?= $rr['p_id'] ? '#'.$rr['p_id'] : '—' ?></td>
              <td style="font-weight:700;"><?= $has_paid ? '📅 '.date('d M Y', strtotime($rr['paid_date'])) : '<span style="color:var(--muted);">—</span>' ?></td>
              <td><?= $has_paid ? '<span class="badge badge-grass">✅ Paid</span>' : '<span class="badge badge-rose">⏳ Pending</span>' ?></td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr>
              <td colspan="9" style="text-align:center; padding:40px; color:var(--muted);">
                📋 No report data found for <?= date('F Y', strtotime($rpt_start)) ?>.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
// ── PHP arrays → JS ──────────────────────────────────
const enrollmentLabels = <?= json_encode($enrollment_labels) ?>;
const enrollmentData   = <?= json_encode($enrollment_data) ?>;
const enrollmentCap    = <?= json_encode($enrollment_cap) ?>;

const attendanceLabels  = <?= json_encode($attendance_labels) ?>;
const attendancePresent = <?= json_encode($attendance_present) ?>;
const attendanceAbsent  = <?= json_encode($attendance_absent) ?>;

const payLabels = <?= json_encode($pay_labels) ?>;
const payData   = <?= json_encode($pay_data) ?>;

const ageLabels = <?= json_encode($age_labels) ?>;
const ageData   = <?= json_encode($age_data) ?>;

// Chart defaults
Chart.defaults.font.family = "'Nunito', sans-serif";
Chart.defaults.font.weight = '700';
Chart.defaults.color = '#8A9BB0';

// ── 1. ENROLLMENT BAR CHART ───────────────────────────
new Chart(document.getElementById('enrollmentChart'), {
  type: 'bar',
  data: {
    labels: enrollmentLabels,
    datasets: [
      {
        label: 'Enrolled',
        data: enrollmentData,
        backgroundColor: '#4FC3F7CC',
        borderRadius: 8,
        borderSkipped: false,
      },
      {
        label: 'Capacity',
        data: enrollmentCap,
        backgroundColor: '#E0E8F0',
        borderRadius: 8,
        borderSkipped: false,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'top' }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#F0F4F8' }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});

// ── 2. ATTENDANCE LINE CHART ─────────────────────────
new Chart(document.getElementById('attendanceChart'), {
  type: 'line',
  data: {
    labels: attendanceLabels,
    datasets: [
      {
        label: 'Present',
        data: attendancePresent,
        borderColor: '#66BB6A',
        backgroundColor: '#66BB6A22',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#66BB6A',
        pointRadius: 5,
      },
      {
        label: 'Absent',
        data: attendanceAbsent,
        borderColor: '#F06292',
        backgroundColor: '#F0629222',
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#F06292',
        pointRadius: 5,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'top' }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#F0F4F8' }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});

// ── 3. PAYMENT TREND LINE CHART ───────────────────────
new Chart(document.getElementById('paymentChart'), {
  type: 'line',
  data: {
    labels: payLabels,
    datasets: [{
      label: 'Revenue (LKR)',
      data: payData,
      borderColor: '#9575CD',
      backgroundColor: '#9575CD22',
      tension: 0.4,
      fill: true,
      pointBackgroundColor: '#9575CD',
      pointRadius: 5,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'top' }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#F0F4F8' },
        ticks: {
          callback: v => 'LKR ' + v.toLocaleString()
        }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});

// ── 4. AGE GROUP DOUGHNUT ────────────────────────────
new Chart(document.getElementById('ageChart'), {
  type: 'doughnut',
  data: {
    labels: ageLabels,
    datasets: [{
      data: ageData,
      backgroundColor: [
        '#4FC3F7', '#66BB6A', '#FFB830',
        '#F06292', '#9575CD', '#26C6DA', '#FF8A65'
      ],
      borderWidth: 2,
      borderColor: '#fff',
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { padding: 12, font: { size: 11 } }
      }
    },
    cutout: '65%'
  }
});
// ── PHP arrays for report charts → JS ────────────────
const rptChartLabels = <?= json_encode($rpt_chart_labels) ?>;
const rptChartActs   = <?= json_encode($rpt_chart_acts) ?>;
const rptChartPays   = <?= json_encode($rpt_chart_pays) ?>;
const paidCount      = <?= (int)$paid_count ?>;
const notPaidCount   = <?= (int)$notpaid_count ?>;

// ── 5. ACTIVITIES & PAYMENTS PER STUDENT (BAR) ───────
new Chart(document.getElementById('rptBarChart'), {
  type: 'bar',
  data: {
    labels: rptChartLabels,
    datasets: [
      {
        label: 'Activities',
        data: rptChartActs,
        backgroundColor: '#9575CDCC',
        borderRadius: 8,
        borderSkipped: false,
      },
      {
        label: 'Payments',
        data: rptChartPays,
        backgroundColor: '#4FC3F7CC',
        borderRadius: 8,
        borderSkipped: false,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#F0F4F8' }, ticks: { stepSize: 1 } },
      x: { grid: { display: false } }
    }
  }
});

// ── 6. PAYMENT STATUS DONUT ───────────────────────────
new Chart(document.getElementById('rptDonut'), {
  type: 'doughnut',
  data: {
    labels: ['Paid', 'Pending'],
    datasets: [{
      data: [paidCount, notPaidCount],
      backgroundColor: ['#66BB6A', '#F06292'],
      borderWidth: 2,
      borderColor: '#fff',
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { padding: 12, font: { size: 11 } } }
    },
    cutout: '65%'
  }
});
</script>
</body>
</html>