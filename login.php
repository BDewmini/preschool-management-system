<?php
session_start();
include 'db.php';

if(isset($_SESSION['user'])){
    header("Location: dashboard.php");
    exit();
}

$error = '';
if(isset($_POST['login'])){
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    $q    = mysqli_query($conn, "SELECT * FROM users WHERE email='".mysqli_real_escape_string($conn,$email)."'");
    $user = mysqli_fetch_assoc($q);

    if($user && password_verify($pass, $user['password'])){
        $_SESSION['user'] = $user;
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Incorrect email or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – Little Stars Pre School</title>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --sun:    #FFB830;
      --sky:    #29B6F6;
      --grass:  #66BB6A;
      --rose:   #F06292;
      --purple: #AB8EE8;
      --navy:   #1A2A4A;
      --navy2:  #243756;
      --card:   #FFFFFF;
      --text:   #1A2A4A;
      --muted:  #7A8EAA;
      --error:  #F44336;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Nunito', sans-serif;
      min-height: 100vh;
      display: flex;
      background: #EEF5FF;
      overflow: hidden;
    }

    /* ═══ LEFT PANEL ═══ */
    .left-panel {
      width: 52%;
      background: linear-gradient(145deg, #1A2A4A 0%, #243756 55%, #1B3A6B 100%);
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px;
      overflow: hidden;
    }

    /* Decorative blobs */
    .blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(0px);
      opacity: 0.12;
    }
    .blob-1 { width: 380px; height: 380px; background: var(--sky);    top: -100px; left: -80px; }
    .blob-2 { width: 260px; height: 260px; background: var(--sun);    bottom: 40px; right: -60px; }
    .blob-3 { width: 160px; height: 160px; background: var(--purple); top: 45%;  left: 65%; }

    /* Floating shapes */
    .shape {
      position: absolute;
      border-radius: 18px;
      display: flex; align-items: center; justify-content: center;
      font-size: 28px;
      animation: float 4s ease-in-out infinite;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    }
    .shape-1 { width:64px; height:64px; background:#FFB830; top:10%;  left:8%;  animation-delay:0s;    border-radius:20px; transform:rotate(-12deg); }
    .shape-2 { width:54px; height:54px; background:#F06292; top:22%;  right:12%;animation-delay:0.7s;  border-radius:50%; }
    .shape-3 { width:58px; height:58px; background:#66BB6A; bottom:22%;left:12%; animation-delay:1.4s;  border-radius:16px; transform:rotate(10deg); }
    .shape-4 { width:50px; height:50px; background:#AB8EE8; bottom:12%;right:15%;animation-delay:2.1s;  border-radius:50%; }
    .shape-5 { width:44px; height:44px; background:#29B6F6; top:52%;  left:5%;  animation-delay:0.4s;  border-radius:14px; transform:rotate(-6deg); }

    @keyframes float {
      0%,100% { transform: translateY(0) rotate(var(--r, 0deg)); }
      50%      { transform: translateY(-14px) rotate(var(--r, 0deg)); }
    }

    /* Stars bg */
    .stars {
      position: absolute; inset: 0;
      background-image:
        radial-gradient(circle, rgba(255,255,255,0.55) 1px, transparent 1px),
        radial-gradient(circle, rgba(255,255,255,0.3) 1px, transparent 1px);
      background-size: 60px 60px, 90px 90px;
      background-position: 0 0, 30px 30px;
    }

    .left-content {
      position: relative; z-index: 2;
      text-align: center;
      animation: slideUp .8s cubic-bezier(.22,.68,0,1.2) both;
    }
    .school-logo {
      width: 100px; height: 100px;
      background: linear-gradient(135deg, var(--sun) 0%, #FF9800 100%);
      border-radius: 28px;
      display: flex; align-items: center; justify-content: center;
      font-size: 52px;
      margin: 0 auto 28px;
      box-shadow: 0 16px 40px rgba(255,184,48,0.4);
      animation: popIn .6s .2s cubic-bezier(.22,.68,0,1.2) both;
    }
    @keyframes popIn {
      from { transform: scale(0.5); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }
    .school-name {
      font-family: 'Fredoka One', cursive;
      font-size: 42px;
      color: #fff;
      line-height: 1;
      margin-bottom: 8px;
      letter-spacing: 0.5px;
    }
    .school-tagline {
      font-size: 15px;
      color: rgba(255,255,255,0.5);
      font-weight: 600;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      margin-bottom: 40px;
    }

    /* Feature pills */
    .features { display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: 340px; }
    .feature {
      display: flex; align-items: center; gap: 14px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 14px;
      padding: 14px 18px;
      animation: slideUp .6s cubic-bezier(.22,.68,0,1.2) both;
    }
    .feature:nth-child(1){ animation-delay:.3s }
    .feature:nth-child(2){ animation-delay:.45s }
    .feature:nth-child(3){ animation-delay:.6s }
    .feat-icon {
      width: 42px; height: 42px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; flex-shrink: 0;
    }
    .feat-title { font-size: 14px; font-weight: 800; color: #fff; }
    .feat-desc  { font-size: 12px; color: rgba(255,255,255,0.45); }

    /* ═══ RIGHT PANEL ═══ */
    .right-panel {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 48px;
      position: relative;
    }

    /* Subtle dot grid */
    .right-panel::before {
      content: '';
      position: absolute; inset: 0;
      background-image: radial-gradient(circle, #c8d8f0 1px, transparent 1px);
      background-size: 28px 28px;
      opacity: 0.5;
    }

    .login-card {
      width: 100%; max-width: 420px;
      background: var(--card);
      border-radius: 28px;
      padding: 44px 40px;
      box-shadow: 0 20px 60px rgba(26,42,74,0.13), 0 4px 16px rgba(26,42,74,0.07);
      position: relative; z-index: 1;
      animation: slideUp .7s .1s cubic-bezier(.22,.68,0,1.2) both;
    }

    @keyframes slideUp {
      from { transform: translateY(30px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }

    /* Corner accent */
    .login-card::before {
      content: '';
      position: absolute;
      top: 0; right: 0;
      width: 90px; height: 90px;
      background: linear-gradient(135deg, var(--sun) 0%, #FF9800 100%);
      border-radius: 0 28px 0 90px;
      opacity: 0.12;
    }

    .card-header { margin-bottom: 32px; }
    .card-eyebrow {
      font-size: 11px; font-weight: 800;
      letter-spacing: 2px; text-transform: uppercase;
      color: var(--sky); margin-bottom: 8px;
    }
    .card-title {
      font-family: 'Fredoka One', cursive;
      font-size: 32px; color: var(--navy);
      line-height: 1.1; margin-bottom: 6px;
    }
    .card-subtitle { font-size: 14px; color: var(--muted); font-weight: 600; }

    /* Error */
    .error-box {
      background: #FFF0F0;
      border: 1.5px solid #FFCDD2;
      border-left: 4px solid var(--error);
      border-radius: 12px;
      padding: 12px 16px;
      margin-bottom: 22px;
      display: flex; align-items: center; gap: 10px;
      animation: shake .4s cubic-bezier(.36,.07,.19,.97);
    }
    @keyframes shake {
      0%,100%{ transform:translateX(0) }
      20%    { transform:translateX(-6px) }
      40%    { transform:translateX(6px) }
      60%    { transform:translateX(-4px) }
      80%    { transform:translateX(4px) }
    }
    .error-icon { font-size: 18px; }
    .error-text { font-size: 13px; font-weight: 700; color: #C62828; }

    /* Form */
    .form-group { margin-bottom: 20px; }
    .form-label {
      display: block;
      font-size: 13px; font-weight: 800;
      color: var(--navy); margin-bottom: 8px;
      letter-spacing: .3px;
    }
    .input-wrap {
      position: relative;
    }
    .input-icon {
      position: absolute; left: 16px; top: 50%;
      transform: translateY(-50%);
      font-size: 18px; pointer-events: none;
      opacity: 0.5;
    }
    .form-input {
      width: 100%;
      padding: 14px 16px 14px 46px;
      border: 2px solid #E4EDF5;
      border-radius: 14px;
      font-family: 'Nunito', sans-serif;
      font-size: 15px; font-weight: 600;
      color: var(--navy);
      background: #F7FAFF;
      outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .form-input::placeholder { color: #B0BECC; font-weight: 600; }
    .form-input:focus {
      border-color: var(--sky);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(41,182,246,0.12);
    }

    /* Password toggle */
    .toggle-pass {
      position: absolute; right: 14px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      font-size: 18px; color: var(--muted);
      transition: color .18s;
      padding: 4px;
    }
    .toggle-pass:hover { color: var(--sky); }

    /* Remember row */
    .form-row {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 26px;
    }
    .remember {
      display: flex; align-items: center; gap: 8px;
      cursor: pointer;
    }
    .remember input[type=checkbox] { display: none; }
    .check-box {
      width: 20px; height: 20px;
      border: 2px solid #C8D8E8;
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      transition: all .18s;
      font-size: 12px;
    }
    .remember input:checked + .check-box {
      background: var(--sky); border-color: var(--sky); color: #fff;
    }
    .remember-text { font-size: 13px; font-weight: 700; color: var(--muted); }
    .forgot-link { font-size: 13px; font-weight: 700; color: var(--sky); text-decoration: none; }
    .forgot-link:hover { text-decoration: underline; }

    /* Login button */
    .login-btn {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #1A2A4A 0%, #243756 100%);
      color: #fff;
      border: none; border-radius: 14px;
      font-family: 'Nunito', sans-serif;
      font-size: 16px; font-weight: 800;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 10px;
      transition: transform .18s, box-shadow .18s;
      box-shadow: 0 6px 20px rgba(26,42,74,0.25);
      letter-spacing: .3px;
    }
    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 32px rgba(26,42,74,0.32);
    }
    .login-btn:active { transform: translateY(0); }
    .btn-arrow { font-size: 20px; transition: transform .2s; }
    .login-btn:hover .btn-arrow { transform: translateX(4px); }

    /* Divider */
    .divider {
      display: flex; align-items: center; gap: 12px;
      margin: 22px 0;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1;
      height: 1px; background: #E4EDF5;
    }
    .divider-text { font-size: 12px; color: var(--muted); font-weight: 700; }

    /* Credential hint */
    .hint-box {
      background: linear-gradient(135deg, #F0FBFF 0%, #F7FAFF 100%);
      border: 1.5px solid #C8E8FA;
      border-radius: 12px;
      padding: 12px 16px;
      display: flex; align-items: center; gap: 10px;
    }
    .hint-icon { font-size: 20px; }
    .hint-label { font-size: 11px; font-weight: 800; color: var(--sky); letter-spacing: .5px; text-transform: uppercase; margin-bottom: 2px; }
    .hint-creds { font-size: 12px; font-weight: 700; color: var(--navy); font-family: monospace; }

    /* Footer */
    .card-footer {
      margin-top: 24px;
      text-align: center;
      font-size: 12px; color: var(--muted); font-weight: 600;
    }
    .card-footer span { color: var(--sun); font-weight: 800; }

    /* Responsive */
    @media (max-width: 900px) {
      .left-panel { display: none; }
      .right-panel { padding: 24px; }
    }
  </style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<!-- ══════════ LEFT PANEL ══════════ -->
<div class="left-panel">
  <div class="stars"></div>
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <div class="shape shape-1">🌟</div>
  <div class="shape shape-2">🎨</div>
  <div class="shape shape-3">📚</div>
  <div class="shape shape-4">🎵</div>
  <div class="shape shape-5">🧩</div>

  <div class="left-content">
    <div class="school-logo">🌟</div>
    <div class="school-name">Little Stars</div>
    <div class="school-tagline">Pre School Management</div>

    <div class="features">
      <div class="feature">
        <div class="feat-icon" style="background:rgba(255,184,48,0.15);">👧</div>
        <div>
          <div class="feat-title">Student Management</div>
          <div class="feat-desc">Enroll, track & manage every child</div>
        </div>
      </div>
      <div class="feature">
        <div class="feat-icon" style="background:rgba(41,182,246,0.15);">✅</div>
        <div>
          <div class="feat-title">Attendance Tracking</div>
          <div class="feat-desc">Daily records with one click</div>
        </div>
      </div>
      <div class="feature">
        <div class="feat-icon" style="background:rgba(102,187,106,0.15);">💳</div>
        <div>
          <div class="feat-title">Fee & Payments</div>
          <div class="feat-desc">Monitor payments & send reminders</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════ RIGHT PANEL ══════════ -->
<div class="right-panel">
  <div class="login-card">

    <div class="card-header">
      <div class="card-eyebrow">Welcome Back</div>
      <div class="card-title">Sign in to your<br>account 👋</div>
      <div class="card-subtitle">Enter your credentials to continue</div>
    </div>

    <?php if($error): ?>
    <div class="error-box">
      <span class="error-icon">⚠️</span>
      <span class="error-text"><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">📧</span>
          <input
            type="email" id="email" name="email"
            class="form-input"
            placeholder="admin@gmail.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            required autofocus
          >
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input
            type="password" id="password" name="password"
            class="form-input"
            placeholder="Enter your password"
            required
          >
          <button type="button" class="toggle-pass" onclick="togglePass()" id="toggleBtn">👁️</button>
        </div>
      </div>

      <div class="form-row">
        <label class="remember">
          <input type="checkbox" name="remember">
          <div class="check-box" id="checkBox">✓</div>
          <span class="remember-text">Remember me</span>
        </label>
        <a href="#" class="forgot-link">Forgot password?</a>
      </div>

      <button type="submit" name="login" class="login-btn">
        Sign In <span class="btn-arrow">→</span>
      </button>

    </form>

    <div class="divider"><span class="divider-text">TEST CREDENTIALS</span></div>

    <div class="hint-box">
      <span class="hint-icon">🔑</span>
      <div>
        <div class="hint-label">Demo Account</div>
        <div class="hint-creds">admin@gmail.com &nbsp;/&nbsp; admin123</div>
      </div>
    </div>

    <div class="card-footer">
      Powered by <span>Little Stars</span> School System &nbsp;©&nbsp; <?= date('Y') ?>
    </div>

  </div>
</div>

<script>
  // Password visibility toggle
  function togglePass() {
    const input = document.getElementById('password');
    const btn   = document.getElementById('toggleBtn');
    if(input.type === 'password'){
      input.type = 'text';
      btn.textContent = '🙈';
    } else {
      input.type = 'password';
      btn.textContent = '👁️';
    }
  }

  // Checkbox visual
  document.querySelector('.remember').addEventListener('click', function(){
    const cb  = this.querySelector('input');
    const box = document.getElementById('checkBox');
    setTimeout(() => {
      box.style.background = cb.checked ? 'var(--sky)' : '';
      box.style.borderColor = cb.checked ? 'var(--sky)' : '';
      box.style.color = cb.checked ? '#fff' : '';
    }, 0);
  });

  // Auto-fill demo on hint click
  document.querySelector('.hint-box').style.cursor = 'pointer';
  document.querySelector('.hint-box').addEventListener('click', function(){
    document.getElementById('email').value    = 'admin@gmail.com';
    document.getElementById('password').value = 'admin123';
    document.getElementById('email').focus();
  });
</script>

</body>
</html>
