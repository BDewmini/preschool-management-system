<?php
$current = basename($_SERVER['PHP_SELF']);
?>
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
    <a href="/preschool/dashboard.php" class="<?= $current=='dashboard.php' ? 'active' : '' ?>">
      <span class="icon">🏠</span> Dashboard
    </a>

    <div class="nav-label">Management</div>
    <a href="/preschool/students/index.php" class="<?= $current=='index.php' ? 'active' : '' ?>">
      <span class="icon">👧</span> Students
    </a>
    <a href="/preschool/teachers.php" class="<?= $current=='teachers.php' ? 'active' : '' ?>">
      <span class="icon">👩‍🏫</span> Teachers
    </a>
    <a href="/preschool/classes.php" class="<?= $current=='classes.php' ? 'active' : '' ?>">
      <span class="icon">🏫</span> Classes
    </a>
    <a href="/preschool/activities.php" class="<?= $current=='activities.php' ? 'active' : '' ?>">
      <span class="icon">🎯</span> Activities
    </a>
    <a href="/preschool/parents.php" class="<?= $current=='parents.php' ? 'active' : '' ?>">
      <span class="icon">👨‍👩‍👧</span> Parents
    </a>
    <a href="/preschool/attendance.php" class="<?= $current=='attendance.php' ? 'active' : '' ?>">
      <span class="icon">✅</span> Attendance
    </a>
    <a href="/preschool/payments.php" class="<?= $current=='payments.php' ? 'active' : '' ?>">
      <span class="icon">💳</span> Payments
    </a>

    <div class="nav-label">System</div>
    <a href="/preschool/reports.php" class="<?= $current=='reports.php' ? 'active' : '' ?>">
      <span class="icon">📊</span> Reports
    </a>
    <a href="/preschool/notifications.php" class="<?= $current=='notifications.php' ? 'active' : '' ?>">
      <span class="icon">🔔</span> Notification
    </a>
    <a href="gallery.php" class="<?= basename($_SERVER['PHP_SELF']) === 'gallery.php' ? 'active' : '' ?>">
  <span class="icon">🖼️</span> Gallery
</a>
    <a href="/preschool/settings.php" class="<?= $current=='settings.php' ? 'active' : '' ?>">
      <span class="icon">⚙️</span> Settings
    </a>

    <div class="nav-label">Account</div>
    <a href="/preschool/logout.php" class="logout-link" onclick="return confirm('Are you sure you want to logout?');">
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
      <a href="/preschool/logout.php" class="logout-btn" title="Logout" onclick="return confirm('Are you sure you want to logout?');">↩</a>
    </div>
  </div>
</aside>
