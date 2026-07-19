<?php
session_start();
include 'db.php';

// Session check - login නැත්නම් redirect
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];  // ?? null ඉවත් කරන්න
$success = $error = '';

// ── HANDLE FORM SUBMISSIONS ──────────────────────────

// 1. School Info Update
if (isset($_POST['save_school'])) {
    $school_name    = mysqli_real_escape_string($conn, $_POST['school_name']);
    $school_address = mysqli_real_escape_string($conn, $_POST['school_address']);
    $school_phone   = mysqli_real_escape_string($conn, $_POST['school_phone']);
    $school_email   = mysqli_real_escape_string($conn, $_POST['school_email']);
    $school_motto   = mysqli_real_escape_string($conn, $_POST['school_motto']);

    $check = mysqli_query($conn, "SELECT id FROM settings WHERE setting_key='school_name'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE settings SET setting_value='$school_name'    WHERE setting_key='school_name'");
        mysqli_query($conn, "UPDATE settings SET setting_value='$school_address' WHERE setting_key='school_address'");
        mysqli_query($conn, "UPDATE settings SET setting_value='$school_phone'   WHERE setting_key='school_phone'");
        mysqli_query($conn, "UPDATE settings SET setting_value='$school_email'   WHERE setting_key='school_email'");
        mysqli_query($conn, "UPDATE settings SET setting_value='$school_motto'   WHERE setting_key='school_motto'");
    } else {
        mysqli_query($conn, "INSERT INTO settings (setting_key,setting_value) VALUES
            ('school_name','$school_name'),('school_address','$school_address'),
            ('school_phone','$school_phone'),('school_email','$school_email'),
            ('school_motto','$school_motto')
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    }
    $success = "School information updated successfully!";
}

// 2. Change Password
if (isset($_POST['change_password'])) {
    $current  = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    $uid = $user['id'] ?? 0;
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id='$uid'"));

    if (!password_verify($current, $row['password'] ?? '')) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_pass) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($new_pass !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE id='$uid'");
        $success = "Password changed successfully!";
    }
}

// 3. User Profile Update
if (isset($_POST['save_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']);
    $uid       = $user['id'] ?? 0;
    mysqli_query($conn, "UPDATE users SET full_name='$full_name', phone='$phone' WHERE id='$uid'");
    $_SESSION['user']['full_name'] = $full_name;
    $success = "Profile updated successfully!";
}

// 4. System Preferences
if (isset($_POST['save_prefs'])) {
    $timezone = mysqli_real_escape_string($conn, $_POST['timezone']);
    $language = mysqli_real_escape_string($conn, $_POST['language']);
    $currency = mysqli_real_escape_string($conn, $_POST['currency']);
    mysqli_query($conn, "INSERT INTO settings (setting_key,setting_value) VALUES
        ('timezone','$timezone'),('language','$language'),('currency','$currency')
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $success = "Preferences saved successfully!";
}

// ── FETCH CURRENT SETTINGS ───────────────────────────
function getSetting($conn, $key, $default = '') {
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key='$key'"));
    return $r ? $r['setting_value'] : $default;
}

$s_name    = getSetting($conn, 'school_name',    'Little Stars Pre School');
$s_address = getSetting($conn, 'school_address', '');
$s_phone   = getSetting($conn, 'school_phone',   '');
$s_email   = getSetting($conn, 'school_email',   '');
$s_motto   = getSetting($conn, 'school_motto',   '');
$s_tz      = getSetting($conn, 'timezone',       'Asia/Colombo');
$s_lang    = getSetting($conn, 'language',       'English');
$s_currency= getSetting($conn, 'currency',       'LKR');

// Notification counts for bell
$pay_cnt  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payments WHERE status IN ('pending','overdue')"))['cnt'];
$today_n  = date('Y-m-d');
$abs_cnt  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM attendance WHERE date='$today_n' AND status='absent'"))['cnt'];
$bday_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM students WHERE MONTH(date_of_birth)=MONTH(CURDATE()) AND status='active'"))['cnt'];
$unread   = $pay_cnt + $abs_cnt + $bday_cnt;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings – Little Stars Pre School</title>
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
body { font-family: 'Nunito', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

/* SIDEBAR */
.sidebar {
  width: var(--sidebar-w); background: linear-gradient(160deg, #1a2a4a 0%, #243756 100%);
  min-height: 100vh; display: flex; flex-direction: column;
  position: fixed; top: 0; left: 0; z-index: 100;
}
.sidebar-brand { padding: 28px 24px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); }
.brand-icon { width: 42px; height: 42px; background: var(--sun); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.brand-name { font-family: 'Fredoka One', cursive; font-size: 20px; color: #fff; line-height: 1.1; }
.brand-sub  { font-size: 11px; color: rgba(255,255,255,0.45); letter-spacing: .5px; }
.nav { padding: 20px 12px; flex: 1; }
.nav-label { font-size: 10px; font-weight: 800; letter-spacing: 1.5px; color: rgba(255,255,255,0.3); text-transform: uppercase; padding: 0 12px; margin: 16px 0 6px; }
.nav a { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 10px; color: rgba(255,255,255,0.65); text-decoration: none; font-size: 14px; font-weight: 600; transition: all .18s; margin-bottom: 2px; }
.nav a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.nav a.active { background: rgba(79,195,247,0.2); color: var(--sky); }
.nav a .icon { font-size: 18px; width: 22px; text-align: center; }
.nav-badge { margin-left: auto; background: var(--rose); color: #fff; border-radius: 20px; font-size: 10px; font-weight: 900; padding: 2px 7px; }
.sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.08); }
.user-info { display: flex; align-items: center; gap: 10px; }
.avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--rose); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; font-size: 15px; }
.user-name { font-size: 13px; font-weight: 700; color: #fff; }
.user-role  { font-size: 11px; color: rgba(255,255,255,0.4); }
.logout-btn { margin-left: auto; background: rgba(240,98,146,0.2); border: none; border-radius: 8px; padding: 6px 10px; cursor: pointer; color: var(--rose); font-size: 18px; text-decoration: none; display: flex; align-items: center; }
.logout-btn:hover { background: rgba(240,98,146,0.4); }

/* MAIN */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
.topbar { background: var(--card); padding: 18px 32px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #E8EEF5; position: sticky; top: 0; z-index: 50; }
.page-title { font-size: 22px; font-weight: 800; }
.page-title span { color: var(--sky); }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.date-badge { background: var(--bg); border-radius: 20px; padding: 6px 14px; font-size: 13px; font-weight: 600; color: var(--muted); }

/* CONTENT */
.content { padding: 28px 32px; flex: 1; }

/* TABS */
.tabs { display: flex; gap: 8px; margin-bottom: 28px; flex-wrap: wrap; }
.tab-btn {
  padding: 10px 20px; border-radius: 12px; border: 2px solid #E8EEF5;
  background: var(--card); font-family: 'Nunito', sans-serif;
  font-size: 14px; font-weight: 700; color: var(--muted);
  cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: 8px;
}
.tab-btn:hover { border-color: var(--sky); color: var(--sky); }
.tab-btn.active { background: var(--sky); border-color: var(--sky); color: #fff; }

/* SETTINGS CARD */
.settings-card {
  background: var(--card); border-radius: 20px;
  box-shadow: 0 2px 16px rgba(0,0,0,.06);
  padding: 32px; margin-bottom: 24px;
}
.card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--bg); }
.card-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.card-title { font-size: 18px; font-weight: 900; }
.card-sub   { font-size: 12px; color: var(--muted); font-weight: 600; }

/* FORM */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-grid.single { grid-template-columns: 1fr; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 13px; font-weight: 800; color: var(--text); }
.form-group input,
.form-group select,
.form-group textarea {
  padding: 12px 16px; border-radius: 12px;
  border: 2px solid #E8EEF5; font-family: 'Nunito', sans-serif;
  font-size: 14px; font-weight: 600; color: var(--text);
  background: var(--bg); transition: border-color .2s;
  outline: none;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color: var(--sky); background: #fff; }
.form-group textarea { resize: vertical; min-height: 80px; }

/* SAVE BTN */
.save-btn {
  margin-top: 24px; padding: 13px 32px;
  border-radius: 14px; border: none;
  font-family: 'Nunito', sans-serif; font-size: 15px; font-weight: 800;
  cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 8px;
}
.save-btn.blue   { background: var(--sky);    color: #fff; }
.save-btn.green  { background: var(--grass);  color: #fff; }
.save-btn.purple { background: var(--purple); color: #fff; }
.save-btn.sun    { background: var(--sun);    color: #fff; }
.save-btn:hover  { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.15); }

/* ALERT */
.alert { padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert.success { background: #E8F5E9; color: #2E7D32; border-left: 4px solid var(--grass); }
.alert.error   { background: #FCE4EC; color: #C62828; border-left: 4px solid var(--rose); }

/* PASSWORD STRENGTH */
.strength-bar { height: 6px; border-radius: 4px; margin-top: 6px; background: #E8EEF5; overflow: hidden; }
.strength-fill { height: 100%; border-radius: 4px; transition: width .3s, background .3s; width: 0%; }

/* TOGGLE */
.toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--bg); }
.toggle-row:last-child { border-bottom: none; }
.toggle-label { font-size: 14px; font-weight: 700; }
.toggle-sub   { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.toggle { position: relative; width: 48px; height: 26px; }
.toggle input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; inset: 0; background: #E8EEF5; border-radius: 26px; cursor: pointer; transition: .3s; }
.slider::before { content: ''; position: absolute; width: 20px; height: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .3s; box-shadow: 0 2px 6px rgba(0,0,0,.2); }
.toggle input:checked + .slider { background: var(--grass); }
.toggle input:checked + .slider::before { transform: translateX(22px); }

/* TAB PANELS */
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* DANGER ZONE */
.danger-zone { border: 2px solid #FCE4EC; border-radius: 20px; padding: 24px; }
.danger-btn { background: #FCE4EC; color: #C62828; border: 2px solid #F06292; border-radius: 12px; padding: 10px 20px; font-family: 'Nunito', sans-serif; font-size: 14px; font-weight: 800; cursor: pointer; transition: all .2s; }
.danger-btn:hover { background: var(--rose); color: #fff; }
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<!-- SIDEBAR -->
<?php include 'sidebar.php'; ?>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">⚙️ <span>Settings</span></div>
    <div class="topbar-right">
      <div class="date-badge">📅 <?= date('D, d M Y') ?></div>
      <a href="notifications.php" style="position:relative; display:inline-block;">
        <button class="notif-btn" style="background:var(--bg); border:none; border-radius:50%; width:40px; height:40px; font-size:20px; cursor:pointer;">🔔
          <?php if($unread > 0): ?>
            <span style="position:absolute; top:-2px; right:-2px; background:red; color:white; border-radius:50%; padding:1px 5px; font-size:10px; font-weight:900;"><?= $unread ?></span>
          <?php endif; ?>
        </button>
      </a>
    </div>
  </div>

  <div class="content">

    <?php if ($success): ?>
      <div class="alert success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error">❌ <?= $error ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
      <button class="tab-btn active" onclick="switchTab('school')">🏫 School Info</button>
      <button class="tab-btn" onclick="switchTab('profile')">👤 User Profile</button>
      <button class="tab-btn" onclick="switchTab('password')">🔒 Change Password</button>
      <button class="tab-btn" onclick="switchTab('prefs')">🌐 Preferences</button>
      <button class="tab-btn" onclick="switchTab('system')">🛡️ System</button>
    </div>

    <!-- ══ TAB 1: SCHOOL INFO ══ -->
    <div class="tab-panel active" id="tab-school">
      <div class="settings-card">
        <div class="card-header">
          <div class="card-icon" style="background:#E0F4FD;">🏫</div>
          <div>
            <div class="card-title">School Information</div>
            <div class="card-sub">Update your school's basic details</div>
          </div>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group">
              <label>School Name</label>
              <input type="text" name="school_name" value="<?= htmlspecialchars($s_name) ?>" required>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" name="school_phone" value="<?= htmlspecialchars($s_phone) ?>">
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="school_email" value="<?= htmlspecialchars($s_email) ?>">
            </div>
            <div class="form-group">
              <label>School Motto</label>
              <input type="text" name="school_motto" value="<?= htmlspecialchars($s_motto) ?>" placeholder="e.g. Learn, Grow, Shine">
            </div>
            <div class="form-group full">
              <label>Address</label>
              <textarea name="school_address"><?= htmlspecialchars($s_address) ?></textarea>
            </div>
          </div>
          <button type="submit" name="save_school" class="save-btn blue">💾 Save School Info</button>
        </form>
      </div>
    </div>

    <!-- ══ TAB 2: USER PROFILE ══ -->
    <div class="tab-panel" id="tab-profile">
      <div class="settings-card">
        <div class="card-header">
          <div class="card-icon" style="background:#FCE4EC;">👤</div>
          <div>
            <div class="card-title">User Profile</div>
            <div class="card-sub">Update your personal information</div>
          </div>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Email (read-only)</label>
              <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled style="opacity:.6;">
            </div>
            <div class="form-group">
              <label>Phone</label>
              <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Role</label>
              <input type="text" value="Administrator" disabled style="opacity:.6;">
            </div>
          </div>
          <button type="submit" name="save_profile" class="save-btn green">💾 Save Profile</button>
        </form>
      </div>
    </div>

    <!-- ══ TAB 3: CHANGE PASSWORD ══ -->
    <div class="tab-panel" id="tab-password">
      <div class="settings-card">
        <div class="card-header">
          <div class="card-icon" style="background:#EDE7F6;">🔒</div>
          <div>
            <div class="card-title">Change Password</div>
            <div class="card-sub">Keep your account secure</div>
          </div>
        </div>
        <form method="POST">
          <div class="form-grid single">
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
              <label>New Password</label>
              <input type="password" name="new_password" id="new_pass" oninput="checkStrength(this.value)" required>
              <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
              <small id="strength-text" style="color:var(--muted); font-size:11px; font-weight:700;"></small>
            </div>
            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" required>
            </div>
          </div>
          <button type="submit" name="change_password" class="save-btn purple">🔒 Change Password</button>
        </form>
      </div>
    </div>

    <!-- ══ TAB 4: PREFERENCES ══ -->
    <div class="tab-panel" id="tab-prefs">
      <div class="settings-card">
        <div class="card-header">
          <div class="card-icon" style="background:#FFF3CD;">🌐</div>
          <div>
            <div class="card-title">System Preferences</div>
            <div class="card-sub">Language, timezone & currency settings</div>
          </div>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group">
              <label>Language</label>
              <select name="language">
                <option value="English"   <?= $s_lang==='English'   ?'selected':'' ?>>🇬🇧 English</option>
                <option value="Sinhala"   <?= $s_lang==='Sinhala'   ?'selected':'' ?>>🇱🇰 Sinhala</option>
                <option value="Tamil"     <?= $s_lang==='Tamil'     ?'selected':'' ?>>🇱🇰 Tamil</option>
              </select>
            </div>
            <div class="form-group">
              <label>Timezone</label>
              <select name="timezone">
                <option value="Asia/Colombo"    <?= $s_tz==='Asia/Colombo'    ?'selected':'' ?>>🕐 Asia/Colombo (LKT)</option>
                <option value="Asia/Kolkata"    <?= $s_tz==='Asia/Kolkata'    ?'selected':'' ?>>🕐 Asia/Kolkata (IST)</option>
                <option value="UTC"             <?= $s_tz==='UTC'             ?'selected':'' ?>>🕐 UTC</option>
                <option value="Asia/Dubai"      <?= $s_tz==='Asia/Dubai'      ?'selected':'' ?>>🕐 Asia/Dubai (GST)</option>
                <option value="Europe/London"   <?= $s_tz==='Europe/London'   ?'selected':'' ?>>🕐 Europe/London (GMT)</option>
              </select>
            </div>
            <div class="form-group">
              <label>Currency</label>
              <select name="currency">
                <option value="LKR" <?= $s_currency==='LKR'?'selected':'' ?>>🇱🇰 LKR – Sri Lankan Rupee</option>
                <option value="USD" <?= $s_currency==='USD'?'selected':'' ?>>🇺🇸 USD – US Dollar</option>
                <option value="EUR" <?= $s_currency==='EUR'?'selected':'' ?>>🇪🇺 EUR – Euro</option>
                <option value="GBP" <?= $s_currency==='GBP'?'selected':'' ?>>🇬🇧 GBP – British Pound</option>
                <option value="INR" <?= $s_currency==='INR'?'selected':'' ?>>🇮🇳 INR – Indian Rupee</option>
              </select>
            </div>
          </div>
          <button type="submit" name="save_prefs" class="save-btn sun">💾 Save Preferences</button>
        </form>
      </div>
    </div>

    <!-- ══ TAB 5: SYSTEM ══ -->
    <div class="tab-panel" id="tab-system">
      <div class="settings-card">
        <div class="card-header">
          <div class="card-icon" style="background:#E8F5E9;">🛡️</div>
          <div>
            <div class="card-title">System Settings</div>
            <div class="card-sub">Notifications & system preferences</div>
          </div>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">🔔 Payment Reminders</div>
            <div class="toggle-sub">Send alerts for pending/overdue payments</div>
          </div>
          <label class="toggle"><input type="checkbox" checked><span class="slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">📋 Attendance Alerts</div>
            <div class="toggle-sub">Notify when students are absent</div>
          </div>
          <label class="toggle"><input type="checkbox" checked><span class="slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">🎂 Birthday Reminders</div>
            <div class="toggle-sub">Alert for upcoming student birthdays</div>
          </div>
          <label class="toggle"><input type="checkbox" checked><span class="slider"></span></label>
        </div>
        <div class="toggle-row">
          <div>
            <div class="toggle-label">📊 Weekly Reports</div>
            <div class="toggle-sub">Auto-generate weekly summary reports</div>
          </div>
          <label class="toggle"><input type="checkbox"><span class="slider"></span></label>
        </div>
      </div>

      <!-- DANGER ZONE -->
      <div class="danger-zone">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
          <span style="font-size:22px;">⚠️</span>
          <div>
            <div style="font-size:16px; font-weight:900; color:#C62828;">Danger Zone</div>
            <div style="font-size:12px; color:var(--muted); font-weight:600;">These actions cannot be undone</div>
          </div>
        </div>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <button class="danger-btn" onclick="return confirm('Clear all attendance records? This cannot be undone!')">🗑️ Clear Attendance Records</button>
          <button class="danger-btn" onclick="return confirm('Reset all settings to default?')">🔄 Reset Settings</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function switchTab(name) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  event.target.classList.add('active');
}

function checkStrength(val) {
  const fill = document.getElementById('strength-fill');
  const text = document.getElementById('strength-text');
  let score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    {w:'0%',   c:'#E8EEF5', t:''},
    {w:'25%',  c:'#F06292', t:'Weak'},
    {w:'50%',  c:'#FFB830', t:'Fair'},
    {w:'75%',  c:'#4FC3F7', t:'Good'},
    {w:'100%', c:'#66BB6A', t:'Strong 💪'},
  ];
  const l = levels[Math.min(score, 4)];
  fill.style.width = l.w; fill.style.background = l.c; text.textContent = l.t;
}

// Auto-hide alerts
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => a.style.opacity = '0');
}, 3000);
</script>

</body>
</html>