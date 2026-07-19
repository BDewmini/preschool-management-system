<?php
session_start();
include 'db.php';

// Only logged-in parents can see this page
if (!isset($_SESSION['parent_logged_in']) || $_SESSION['parent_logged_in'] !== true) {
    header('Location: parent_login.php');
    exit;
}

$parent_id   = $_SESSION['parent_id'];
$parent_name = $_SESSION['parent_name'] ?? 'Parent';

// Get this parent's children (id + class)
$children = [];
$q = mysqli_query($conn, "SELECT st_id, name, class_id FROM students WHERE parent_id = '$parent_id'");
if ($q) { while ($row = mysqli_fetch_assoc($q)) { $children[] = $row; } }

$student_ids = array_column($children, 'st_id');
$class_ids   = array_unique(array_filter(array_column($children, 'class_id')));

$student_ids_in = count($student_ids) > 0 ? implode(',', array_map('intval', $student_ids)) : '0';
$class_ids_in   = count($class_ids) > 0   ? implode(',', array_map('intval', $class_ids))   : '0';

// Photos that are: for everyone (both null), OR for one of their children's classes,
// OR tagged directly to one of their children
$photos = [];
$gq = mysqli_query($conn, "
    SELECT g.*, c.class_name, s.name AS student_name
    FROM gallery g
    LEFT JOIN classes c ON c.class_id = g.class_id
    LEFT JOIN students s ON s.st_id = g.st_id
    WHERE (g.class_id = 0 AND g.st_id = 0)
       OR g.class_id IN ($class_ids_in)
       OR g.st_id IN ($student_ids_in)
    ORDER BY g.uploaded_at DESC
");
if ($gq) { while ($row = mysqli_fetch_assoc($gq)) { $photos[] = $row; } }
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
    --sun: #FFB830; --sky: #4FC3F7; --grass: #66BB6A; --rose: #F06292; --purple: #9575CD;
    --bg: #F0F7FF; --card: #FFFFFF; --text: #2D3A4A; --muted: #8A9BB0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Nunito', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
  .topbar {
    background: linear-gradient(120deg, #1a2a4a 0%, #243756 60%, #1e3a5f 100%);
    padding: 24px 28px; display: flex; align-items: center; justify-content: space-between; color: #fff;
  }
  .brand { display: flex; align-items: center; gap: 12px; }
  .brand-icon { width: 42px; height: 42px; background: var(--sun); border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
  .brand-name { font-family: 'Fredoka One', cursive; font-size: 19px; }
  .brand-sub { font-size: 11px; color: rgba(255,255,255,0.5); letter-spacing: .5px; }
  .topbar-right { display: flex; align-items: center; gap: 14px; }
  .back-link { color: rgba(255,255,255,0.8); text-decoration: none; font-size: 13px; font-weight: 700; }

  .content { padding: 28px; max-width: 1000px; margin: 0 auto; }
  .page-heading { font-size: 20px; font-weight: 900; margin-bottom: 4px; }
  .page-sub { color: var(--muted); font-size: 13px; margin-bottom: 24px; }

  .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap: 18px; }
  .photo-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
  .photo-card img { width: 100%; height: 180px; object-fit: cover; display: block; cursor: pointer; }
  .photo-info { padding: 14px 16px; }
  .photo-tag {
    display: inline-block; font-size: 10px; font-weight: 800; padding: 3px 10px;
    border-radius: 20px; background: #EDE7F6; color: #6A1B9A; margin-bottom: 6px;
  }
  .photo-caption { font-size: 13px; font-weight: 700; margin-bottom: 4px; }
  .photo-meta { font-size: 11px; color: var(--muted); }

  .empty { background: #fff; border-radius: 16px; padding: 48px; text-align: center; color: var(--muted); font-weight: 600; }

  /* Lightbox */
  .lightbox {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
    z-index: 999; align-items: center; justify-content: center; padding: 20px;
  }
  .lightbox.open { display: flex; }
  .lightbox img { max-width: 100%; max-height: 85vh; border-radius: 12px; }
  .lightbox-close {
    position: absolute; top: 20px; right: 28px; color: #fff; font-size: 32px;
    cursor: pointer; font-weight: 300;
  }
</style>
  <link rel="stylesheet" href="/preschool/sidebar.css">
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-icon">🖼️</div>
    <div>
      <div class="brand-name">Little Stars</div>
      <div class="brand-sub">GALLERY</div>
    </div>
  </div>
  <div class="topbar-right">
    <a href="parent_dashboard.php" class="back-link">← Back to Dashboard</a>
  </div>
</div>

<div class="content">
  <div class="page-heading">📸 Photo Gallery</div>
  <div class="page-sub">Moments from school, shared just for you.</div>

  <?php if (count($photos) === 0): ?>
    <div class="empty">No photos yet — check back soon! 🌟</div>
  <?php else: ?>
    <div class="gallery-grid">
      <?php foreach ($photos as $p):
        $tagText = 'School-wide';
        if (!empty($p['st_id']))        $tagText = htmlspecialchars($p['student_name'] ?? 'Your child');
        elseif (!empty($p['class_id'])) $tagText = htmlspecialchars($p['class_name'] ?? 'Class');
      ?>
      <div class="photo-card">
        <img src="gallery_photos/<?= htmlspecialchars($p['filename']) ?>"
             alt="<?= htmlspecialchars($p['caption']) ?>"
             onclick="openLightbox(this.src)">
        <div class="photo-info">
          <span class="photo-tag"><?= $tagText ?></span>
          <?php if (!empty($p['caption'])): ?>
            <div class="photo-caption"><?= htmlspecialchars($p['caption']) ?></div>
          <?php endif; ?>
          <div class="photo-meta"><?= date('d M Y', strtotime($p['uploaded_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <span class="lightbox-close">&times;</span>
  <img id="lightboxImg" src="" alt="">
</div>

<script>
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
}
</script>

</body>
</html>
