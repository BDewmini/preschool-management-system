<?php
session_start();
include 'db.php';

$success = '';
$error   = '';

// ── Fetch classes & students for the upload form dropdowns ──
$classes = [];
$cq = $conn->query("SELECT class_id, class_name FROM classes ORDER BY class_name ASC");
if ($cq) { while ($row = $cq->fetch_assoc()) { $classes[] = $row; } }

$students = [];
$sq = $conn->query("SELECT st_id, name, class_id FROM students WHERE status='active' ORDER BY name ASC");
if ($sq) { while ($row = $sq->fetch_assoc()) { $students[] = $row; } }

// ── Handle upload ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $target   = $_POST['target'] ?? 'all';   // all | class | student
    $class_id = ($target === 'class')   ? intval($_POST['class_id'] ?? 0) : null;
    $st_id    = ($target === 'student') ? intval($_POST['st_id'] ?? 0)   : null;
    $caption  = trim((string)($_POST['caption'] ?? ''));

    $file = $_FILES['photo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
    } else {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = 'Only image files (jpg, png, webp, gif) are allowed.';
        } elseif ($file['size'] > 8 * 1024 * 1024) {
            $error = 'File is too large. Max size is 8MB.';
        } else {
            $uploadDir = __DIR__ . '/gallery_photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $safeName = 'gal_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $uploadDir . $safeName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $stmt = $conn->prepare("
                    INSERT INTO gallery (filename, original_name, class_id, st_id, caption, uploaded_by, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $sessionUser = $_SESSION['user'] ?? 'Admin';
                if (is_array($sessionUser)) {
                    $uploadedBy = $sessionUser['name'] ?? $sessionUser['username'] ?? $sessionUser['email'] ?? 'Admin';
                } else {
                    $uploadedBy = (string)$sessionUser;
                }
                $originalName = (string)$file['name'];
                $stmt->bind_param('ssiiss', $safeName, $originalName, $class_id, $st_id, $caption, $uploadedBy);
                $stmt->execute();
                $success = 'Photo uploaded successfully!';
            } else {
                $error = 'Could not save the uploaded file.';
            }
        }
    }
}

// ── Handle delete ─────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT filename FROM gallery WHERE id=$id");
    if ($res && $row = $res->fetch_assoc()) {
        $filePath = __DIR__ . '/uploads/gallery/' . $row['filename'];
        if (file_exists($filePath)) unlink($filePath);
        $conn->query("DELETE FROM gallery WHERE id=$id");
    }
    header('Location: gallery.php');
    exit;
}

// ── Fetch all gallery photos (newest first) ───────────
$photos = [];
$gq = $conn->query("
    SELECT g.*, c.class_name, s.name AS student_name, sc.class_name AS student_class_name
    FROM gallery g
    LEFT JOIN classes c ON c.class_id = g.class_id
    LEFT JOIN students s ON s.st_id = g.st_id
    LEFT JOIN classes sc ON sc.class_id = s.class_id
    ORDER BY g.uploaded_at DESC
");
if ($gq) { while ($row = $gq->fetch_assoc()) { $photos[] = $row; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gallery – Little Stars Pre School</title>
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
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar {
  background: var(--card); padding: 18px 32px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid #E8EEF5; position: sticky; top: 0; z-index: 50;
}
.page-title { font-size: 22px; font-weight: 800; }
.page-title span { color: var(--sky); }
.content { padding: 28px 32px; flex: 1; }

.panel { background: var(--card); border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.05); margin-bottom: 24px; }
.panel h2 { font-size: 16px; font-weight: 900; margin-bottom: 16px; }

.form-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 14px; }
.form-group { flex: 1; min-width: 180px; }
.form-group label { display: block; font-size: 12px; font-weight: 800; margin-bottom: 6px; color: var(--muted); }
.form-group input[type="text"],
.form-group input[type="file"],
.form-group select {
  width: 100%; padding: 10px 12px; border-radius: 10px;
  border: 2px solid #E8EEF5; font-size: 14px; font-family: inherit; outline: none;
}
.form-group input:focus, .form-group select:focus { border-color: var(--sky); }

.target-tabs { display: flex; gap: 8px; margin-bottom: 14px; }
.target-tab {
  padding: 8px 16px; border-radius: 10px; border: 2px solid #E8EEF5;
  cursor: pointer; font-size: 13px; font-weight: 700; color: var(--muted);
  background: #fff;
}
.target-tab.active { border-color: var(--sky); color: var(--sky); background: #E0F4FD; }

.btn-upload {
  background: var(--rose); color: #fff; border: none; border-radius: 10px;
  padding: 12px 24px; font-size: 14px; font-weight: 800; cursor: pointer; font-family: inherit;
}
.btn-upload:hover { opacity: .9; }

.alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 16px; }
.alert-success { background: #E8F5E9; color: #2E7D32; }
.alert-error   { background: #FCE4EC; color: #C62828; }

.gallery-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 16px;
}
.photo-card {
  background: var(--card); border-radius: 14px; overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,.05);
}
.photo-card img { width: 100%; height: 160px; object-fit: cover; display: block; }
.photo-info { padding: 12px 14px; }
.photo-caption { font-size: 13px; font-weight: 700; margin-bottom: 4px; }
.photo-tag {
  display: inline-block; font-size: 10px; font-weight: 800; padding: 3px 10px;
  border-radius: 20px; background: #EDE7F6; color: #6A1B9A; margin-bottom: 6px;
}
.photo-meta { font-size: 11px; color: var(--muted); }
.photo-actions { padding: 0 14px 14px; }
.del-link { font-size: 12px; color: #C62828; font-weight: 700; text-decoration: none; }

.empty { text-align: center; color: var(--muted); padding: 40px; font-weight: 600; }
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="page-title">🖼️ <span>Gallery</span></div>
  </div>

  <div class="content">

    <div class="panel">
      <h2>Upload a Photo</h2>

      <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="form-group" style="margin-bottom:14px;">
          <label>Photo</label>
          <input type="file" name="photo" accept="image/*" required>
        </div>

        <label style="display:block; font-size:12px; font-weight:800; margin-bottom:6px; color:var(--muted);">Who is this for?</label>
        <div class="target-tabs">
          <div class="target-tab active" data-target="all" onclick="selectTarget('all')">🌟 Everyone</div>
          <div class="target-tab" data-target="class" onclick="selectTarget('class')">🏫 A Class</div>
          <div class="target-tab" data-target="student" onclick="selectTarget('student')">🧒 One Child</div>
        </div>
        <input type="hidden" name="target" id="targetInput" value="all">

        <div class="form-row" id="classRow" style="display:none;">
          <div class="form-group">
            <label>Class</label>
            <select name="class_id">
              <?php foreach ($classes as $c): ?>
                <option value="<?= (int)$c['class_id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row" id="studentRow" style="display:none;">
          <div class="form-group">
            <label>Student</label>
            <select name="st_id" id="studentSelect" onchange="updateStudentClass()">
              <option value="">-- Select a student --</option>
              <?php foreach ($students as $s):
                $sClassName = '';
                foreach ($classes as $c) {
                  if ($c['class_id'] == $s['class_id']) { $sClassName = $c['class_name']; break; }
                }
              ?>
                <option value="<?= (int)$s['st_id'] ?>" data-class="<?= htmlspecialchars($sClassName) ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div id="studentClassHint" style="margin-top:6px; font-size:12px; color:var(--muted); font-weight:700;"></div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Caption (optional)</label>
            <input type="text" name="caption" placeholder="e.g. Art class fun day!">
          </div>
        </div>

        <button type="submit" class="btn-upload">📤 Upload Photo</button>
      </form>
    </div>

    <div class="panel">
      <h2>All Photos (<?= count($photos) ?>)</h2>

      <?php if (count($photos) === 0): ?>
        <div class="empty">No photos uploaded yet. Upload the first one above! 📸</div>
      <?php else: ?>
        <div class="gallery-grid">
          <?php foreach ($photos as $p):
            $tagText = 'Everyone';
            if (!empty($p['st_id']))       $tagText = htmlspecialchars($p['student_name'] ?? 'Student');
            elseif (!empty($p['class_id'])) $tagText = htmlspecialchars($p['class_name'] ?? 'Class');
          ?>
          <div class="photo-card">
            <img src="gallery_photos/<?= htmlspecialchars($p['filename']) ?>" alt="<?= htmlspecialchars($p['caption']) ?>">
            <div class="photo-info">
              <span class="photo-tag"><?= $tagText ?></span>
              <?php if (!empty($p['st_id']) && !empty($p['student_class_name'])): ?>
                <div class="photo-meta" style="margin-bottom:4px;">🏫 <?= htmlspecialchars($p['student_class_name']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p['caption'])): ?>
                <div class="photo-caption"><?= htmlspecialchars($p['caption']) ?></div>
              <?php endif; ?>
              <div class="photo-meta"><?= date('d M Y, h:i A', strtotime($p['uploaded_at'])) ?></div>
            </div>
            <div class="photo-actions">
              <a href="gallery.php?delete=<?= (int)$p['id'] ?>" class="del-link"
                 onclick="return confirm('Delete this photo?');">🗑️ Delete</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
function updateStudentClass() {
  const select = document.getElementById('studentSelect');
  const hint = document.getElementById('studentClassHint');
  const opt = select.options[select.selectedIndex];
  const className = opt ? opt.getAttribute('data-class') : '';
  hint.textContent = className ? ('🏫 Class: ' + className) : '';
}

function selectTarget(target) {
  document.getElementById('targetInput').value = target;
  document.querySelectorAll('.target-tab').forEach(t => t.classList.remove('active'));
  document.querySelector('.target-tab[data-target="' + target + '"]').classList.add('active');
  document.getElementById('classRow').style.display   = (target === 'class')   ? 'flex' : 'none';
  document.getElementById('studentRow').style.display = (target === 'student') ? 'flex' : 'none';
}
</script>

</body>
</html>