<?php
session_start();
define('ADMIN_PASSWORD', 'artwood2025');
define('UPLOAD_DIR', __DIR__ . '/');
define('ALLOWED', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('MAX_SIZE', 15 * 1024 * 1024);
function getRealizacje($dir) {
    $all = glob($dir . '/realizacja*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [];
    $groups = [];
    foreach ($all as $path) {
        $f = basename($path);
        if (preg_match('/^realizacja(\d+)_(\d+)\./i', $f, $m))
            $groups[(int)$m[1]][] = ['file'=>$f,'idx'=>(int)$m[2]];
        elseif (preg_match('/^realizacja(\d+)\.[a-z]+$/i', $f, $m))
            $groups[(int)$m[1]][] = ['file'=>$f,'idx'=>1];
    }
    foreach ($groups as &$p) usort($p, fn($a,$b)=>$a['idx']-$b['idx']);
    ksort($groups);
    return $groups;
}
function getCovers($dir) {
    $f=$dir.'/realizacje.json';
    return file_exists($f)?(json_decode(file_get_contents($f),true)['covers']??[]):[];
}
function saveCovers($dir,$covers) {
    file_put_contents($dir.'/realizacje.json',json_encode(['covers'=>$covers],JSON_PRETTY_PRINT));
}

// Logout
if (isset($_POST['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) $_SESSION['admin'] = true;
    else $loginError = true;
}

$slotMsg = '';

// Nowa realizacja (pierwszy kafelek)
if (!empty($_SESSION['admin']) && !empty($_FILES['new_photo']['name'])) {
    $f = $_FILES['new_photo'];
    if ($f['error'] === 0 && in_array($f['type'], ALLOWED) && $f['size'] <= MAX_SIZE) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $next = 1;
        while (count(glob(UPLOAD_DIR."realizacja{$next}*.*")) > 0) $next++;
        move_uploaded_file($f['tmp_name'], UPLOAD_DIR."realizacja{$next}_1.$ext")
            ? ($slotMsg = "✓ Dodano Realizacja $next")
            : ($slotMsg = "✗ Błąd zapisu");
    } else { $slotMsg = "✗ Niedozwolony format lub plik za duży"; }
}

// Dodaj zdjęcie do istniejącej realizacji
if (!empty($_SESSION['admin']) && !empty($_FILES['add_photo']['name']) && !empty($_POST['add_num'])) {
    $num = (int)$_POST['add_num'];
    $f = $_FILES['add_photo'];
    if ($f['error'] === 0 && in_array($f['type'], ALLOWED) && $f['size'] <= MAX_SIZE) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $existing = glob(UPLOAD_DIR."realizacja{$num}_*.{jpg,jpeg,png,webp,gif}", GLOB_BRACE) ?: [];
        $maxIdx = 0;
        foreach ($existing as $e) { preg_match('/realizacja\d+_(\d+)/i', $e, $mm); $maxIdx = max($maxIdx, (int)($mm[1]??0)); }
        $nextIdx = $maxIdx + 1;
        move_uploaded_file($f['tmp_name'], UPLOAD_DIR."realizacja{$num}_{$nextIdx}.$ext")
            ? ($slotMsg = "✓ Dodano zdjęcie $nextIdx do Realizacji $num")
            : ($slotMsg = "✗ Błąd zapisu");
    } else { $slotMsg = "✗ Niedozwolony format lub plik za duży"; }
}

// Usuń pojedyncze zdjęcie
if (!empty($_SESSION['admin']) && isset($_POST['del_photo'])) {
    $f = basename($_POST['del_photo']);
    if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $f)) {
        $covers = getCovers(UPLOAD_DIR);
        @unlink(UPLOAD_DIR.$f);
        // Jeśli to była okładka — usuń wpis
        foreach ($covers as $num => $cv) { if ($cv === $f) unset($covers[$num]); }
        saveCovers(UPLOAD_DIR, $covers);
    }
    header('Location: admin.php'); exit;
}

// Ustaw okładkę
if (!empty($_SESSION['admin']) && !empty($_POST['set_cover']) && !empty($_POST['cover_num'])) {
    $covers = getCovers(UPLOAD_DIR);
    $covers[(int)$_POST['cover_num']] = basename($_POST['set_cover']);
    saveCovers(UPLOAD_DIR, $covers);
    $slotMsg = "✓ Okładka ustawiona";
}

// Usuń całą realizację
if (!empty($_SESSION['admin']) && !empty($_POST['del_real'])) {
    $num = (int)$_POST['del_real'];
    foreach (glob(UPLOAD_DIR."realizacja{$num}*.*") as $f) @unlink($f);
    $covers = getCovers(UPLOAD_DIR); unset($covers[$num]); saveCovers(UPLOAD_DIR, $covers);
    header('Location: admin.php'); exit;
}

// Upload logo partnera
$partnerMsg = '';
if (!empty($_SESSION['admin']) && !empty($_FILES['partner_photo']['name'])) {
    $f = $_FILES['partner_photo'];
    if ($f['error'] === 0 && in_array($f['type'], ALLOWED) && $f['size'] <= MAX_SIZE) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $existing = glob(UPLOAD_DIR."partner*.{jpg,jpeg,png,webp,gif,svg}", GLOB_BRACE) ?: [];
        $maxN = 0;
        foreach ($existing as $e) { preg_match('/^partner(\d+)\./i', basename($e), $mm); $maxN = max($maxN, (int)($mm[1]??0)); }
        move_uploaded_file($f['tmp_name'], UPLOAD_DIR."partner".($maxN+1).".$ext")
            ? ($partnerMsg = "✓ Logo dodane")
            : ($partnerMsg = "✗ Błąd zapisu");
    } else { $partnerMsg = "✗ Niedozwolony format lub plik za duży"; }
}

// Usuń logo partnera
if (!empty($_SESSION['admin']) && isset($_POST['del_partner'])) {
    $f = basename($_POST['del_partner']);
    if (preg_match('/^partner\d+\.(jpg|jpeg|png|webp|gif|svg)$/i', $f)) @unlink(UPLOAD_DIR.$f);
    header('Location: admin.php'); exit;
}

// Upload zdjęcia "O nas"
$onasMsg = '';
if (!empty($_SESSION['admin']) && !empty($_FILES['onas_photo']['name'])) {
    $f = $_FILES['onas_photo'];
    if ($f['error'] === 0 && in_array($f['type'], ALLOWED) && $f['size'] <= MAX_SIZE) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        foreach (glob(UPLOAD_DIR . "onas.*") as $old) @unlink($old);
        $dest = UPLOAD_DIR . "onas.$ext";
        move_uploaded_file($f['tmp_name'], $dest)
            ? ($onasMsg = "✓ Zdjęcie 'O nas' zaktualizowane")
            : ($onasMsg = "✗ Błąd zapisu");
    } else {
        $onasMsg = "✗ Niedozwolony format lub plik za duży";
    }
}

?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Artwood Koncept — Panel admina</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--brown:#2C1A0E;--gold:#C8943A;--cream:#F7F2EB;--border:#DDD0C0;--red:#c0392b;--green:#27ae60}
body{font-family:'Segoe UI',sans-serif;background:var(--cream);color:var(--brown);min-height:100vh}
header{background:var(--brown);padding:16px 32px;display:flex;align-items:center;justify-content:space-between}
header h1{color:#fff;font-size:1.15rem;font-weight:600;letter-spacing:.04em}
header h1 span{color:var(--gold)}
.logout-btn{background:none;border:1px solid rgba(255,255,255,.4);color:rgba(255,255,255,.8);padding:6px 16px;border-radius:4px;cursor:pointer;font-size:.85rem}
.logout-btn:hover{background:rgba(255,255,255,.1)}
.container{max-width:960px;margin:0 auto;padding:32px 24px}

/* LOGIN */
.login-wrap{max-width:360px;margin:80px auto;background:#fff;border:1px solid var(--border);border-radius:6px;padding:40px}
.login-wrap h2{font-size:1.2rem;margin-bottom:24px;color:var(--brown)}
.login-wrap input{width:100%;border:1px solid var(--border);border-radius:4px;padding:10px 14px;font-size:.95rem;outline:none;margin-bottom:12px}
.login-wrap input:focus{border-color:var(--gold)}
.btn-primary{width:100%;background:var(--brown);color:#fff;border:none;padding:12px;border-radius:4px;font-size:.95rem;font-weight:600;cursor:pointer}
.btn-primary:hover{background:#4a2e18}
.error{color:var(--red);font-size:.85rem;margin-top:8px}

/* UPLOAD */
.upload-box{background:#fff;border:1px solid var(--border);border-radius:6px;padding:28px;margin-bottom:32px}
.upload-box h2{font-size:1rem;font-weight:600;margin-bottom:16px;color:var(--brown)}
.msg{margin-top:12px;padding:10px 14px;border-radius:4px;font-size:.88rem;font-weight:500}
.msg.ok{background:#eafaf1;color:var(--green);border:1px solid #a9dfbf}
.msg.err{background:#fdf3f3;color:var(--red);border:1px solid #f1a9a9}

.empty{color:#7A6252;font-size:.9rem;padding:24px 0}
.slots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:8px}
.slot-card{background:var(--cream);border:1px solid var(--border);border-radius:6px;overflow:hidden}
.slot-preview{aspect-ratio:4/3;background:#e8e0d5;display:flex;align-items:center;justify-content:center}
.slot-preview img{width:100%;height:100%;object-fit:cover;display:block}
.slot-empty{text-align:center;color:#a08878;font-size:.82rem;line-height:2}
.slot-info{padding:8px 12px;display:flex;align-items:center;justify-content:space-between;font-size:.85rem}
.slot-info strong{color:var(--brown)}
.slot-ok{color:var(--green);font-size:.78rem}
.slot-missing{color:#a08878;font-size:.78rem}
.slot-btn{display:block;width:100%;padding:9px;background:var(--brown);color:#fff;text-align:center;cursor:pointer;font-size:.85rem;font-weight:600;transition:.2s}
.slot-btn:hover{background:#4a2e18}
</style>
</head>
<body>

<?php if (empty($_SESSION['admin'])): ?>
<!-- LOGIN -->
<div class="login-wrap">
  <h2>Panel admina — Artwood Koncept</h2>
  <form method="post">
    <input type="password" name="password" placeholder="Hasło" autofocus>
    <?php if (!empty($loginError)): ?><div class="error">Złe hasło — spróbuj ponownie</div><?php endif ?>
    <br>
    <button type="submit" class="btn-primary">Zaloguj się</button>
  </form>
</div>

<?php else: ?>
<!-- PANEL -->
<header>
  <h1>ARTWOOD <span>KONCEPT</span> — Panel admina</h1>
  <form method="post"><button name="logout" class="logout-btn">Wyloguj</button></form>
</header>

<div class="container">
  <!-- ZDJĘCIE O NAS -->
  <div class="upload-box">
    <h2>👤 Zdjęcie — sekcja "O nas"</h2>
    <?php if ($onasMsg): ?>
      <div class="msg <?= strpos($onasMsg,'✓')===0 ? 'ok' : 'err' ?>" style="margin-bottom:16px"><?= htmlspecialchars($onasMsg) ?></div>
    <?php endif ?>
    <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
      <?php
        $onas = null;
        foreach(['jpg','jpeg','png','webp','gif'] as $e) {
          if(file_exists(UPLOAD_DIR."onas.$e")){ $onas="onas.$e"; break; }
        }
      ?>
      <div style="width:160px;height:120px;background:var(--cream);border:1px solid var(--border);border-radius:4px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center">
        <?php if($onas): ?>
          <img src="<?=$onas?>?t=<?=filemtime(UPLOAD_DIR.$onas)?>" style="width:100%;height:100%;object-fit:cover" alt="">
        <?php else: ?>
          <span style="font-size:.8rem;color:#a08878;text-align:center">📷<br>Brak zdjęcia</span>
        <?php endif ?>
      </div>
      <form method="post" enctype="multipart/form-data">
        <label class="slot-btn" style="display:inline-block;padding:10px 24px;border-radius:4px">
          📂 <?= $onas ? 'Zmień zdjęcie' : 'Wgraj zdjęcie' ?>
          <input type="file" name="onas_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
        </label>
      </form>
    </div>
  </div>

  <!-- GALERIA REALIZACJI -->
  <div class="upload-box">
    <h2>🖼 Galeria realizacji</h2>
    <?php if ($slotMsg): ?>
      <div class="msg <?= strpos($slotMsg,'✓')===0 ? 'ok' : 'err' ?>" style="margin-bottom:16px"><?= htmlspecialchars($slotMsg) ?></div>
    <?php endif ?>

    <!-- Dodaj nową realizację -->
    <div style="margin-bottom:24px;padding:16px;background:var(--cream);border-radius:4px;border:1px solid var(--border)">
      <strong style="font-size:.9rem">➕ Dodaj nową realizację</strong>
      <p style="font-size:.82rem;color:#7A6252;margin:6px 0 12px">Pierwsze zdjęcie stworzy nowy kafelek w galerii.</p>
      <form method="post" enctype="multipart/form-data">
        <label class="slot-btn" style="display:inline-block;padding:10px 24px;border-radius:4px;background:var(--gold);color:var(--brown)">
          📂 Wybierz zdjęcie
          <input type="file" name="new_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
        </label>
      </form>
    </div>

    <!-- Istniejące realizacje -->
    <?php
      $realizacje = getRealizacje(UPLOAD_DIR);
      $covers     = getCovers(UPLOAD_DIR);
    ?>
    <?php if (empty($realizacje)): ?>
      <p style="color:#a08878;font-size:.85rem">Brak realizacji — dodaj pierwszą powyżej.</p>
    <?php else: foreach ($realizacje as $num => $photos):
      $cover = $covers[$num] ?? $photos[0]['file'];
    ?>
    <div style="background:var(--cream);border:1px solid var(--border);border-radius:6px;padding:16px;margin-bottom:16px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px">
        <strong>Realizacja <?=$num?> <span style="font-size:.8rem;color:#7A6252;font-weight:400">(<?=count($photos)?> <?=count($photos)===1?'zdjęcie':'zdjęć'?>)</span></strong>
        <div style="display:flex;gap:8px;align-items:center">
          <!-- Dodaj zdjęcie do tej realizacji -->
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="add_num" value="<?=$num?>">
            <label style="background:var(--brown);color:#fff;padding:6px 14px;border-radius:4px;font-size:.82rem;cursor:pointer;display:inline-block">
              ➕ Dodaj zdjęcie
              <input type="file" name="add_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
            </label>
          </form>
          <!-- Usuń całą realizację -->
          <form method="post" onsubmit="return confirm('Usunąć całą Realizację <?=$num?> ze wszystkimi zdjęciami?')">
            <input type="hidden" name="del_real" value="<?=$num?>">
            <button style="background:#c0392b;color:#fff;border:none;padding:6px 14px;border-radius:4px;font-size:.82rem;cursor:pointer">🗑 Usuń realizację</button>
          </form>
        </div>
      </div>

      <!-- Zdjęcia tej realizacji -->
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach ($photos as $p):
          $iscover = ($p['file'] === $cover);
        ?>
        <div style="width:140px;flex-shrink:0">
          <div style="position:relative;aspect-ratio:4/3;border-radius:4px;overflow:hidden;border:3px solid <?=$iscover?'var(--gold)':'var(--border)'?>">
            <img src="<?=htmlspecialchars($p['file'])?>?t=<?=filemtime(UPLOAD_DIR.$p['file'])?>" style="width:100%;height:100%;object-fit:cover" alt="">
            <?php if($iscover): ?>
              <span style="position:absolute;top:4px;left:4px;background:var(--gold);color:var(--brown);font-size:.68rem;font-weight:700;padding:2px 6px;border-radius:10px">⭐ okładka</span>
            <?php endif ?>
          </div>
          <div style="display:flex;gap:4px;margin-top:6px">
            <?php if(!$iscover): ?>
            <form method="post" style="flex:1">
              <input type="hidden" name="set_cover" value="<?=htmlspecialchars($p['file'])?>">
              <input type="hidden" name="cover_num" value="<?=$num?>">
              <button style="width:100%;background:var(--gold);color:var(--brown);border:none;padding:4px;border-radius:3px;font-size:.72rem;font-weight:700;cursor:pointer">⭐ okładka</button>
            </form>
            <?php else: ?>
              <span style="flex:1;text-align:center;font-size:.72rem;color:var(--gold);font-weight:700;padding:4px 0">⭐ okładka</span>
            <?php endif ?>
            <form method="post" onsubmit="return confirm('Usunąć to zdjęcie?')">
              <input type="hidden" name="del_photo" value="<?=htmlspecialchars($p['file'])?>">
              <button style="background:#c0392b;color:#fff;border:none;padding:4px 8px;border-radius:3px;font-size:.82rem;cursor:pointer" title="Usuń">🗑</button>
            </form>
          </div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
    <?php endforeach; endif ?>
  </div>

  <!-- WSPÓŁPRACA — LOGOTYPY PARTNERÓW -->
  <div class="upload-box">
    <h2>🤝 Współpraca — logotypy partnerów</h2>
    <?php if ($partnerMsg): ?>
      <div class="msg <?= strpos($partnerMsg,'✓')===0 ? 'ok' : 'err' ?>" style="margin-bottom:16px"><?= htmlspecialchars($partnerMsg) ?></div>
    <?php endif ?>
    <p style="font-size:.82rem;color:#7A6252;margin-bottom:16px">Loga pojawią się w sekcji "Współpraca" na stronie (szare, kolorowe po najechaniu). Obsługiwane formaty: JPG, PNG, WebP, SVG.</p>
    <form method="post" enctype="multipart/form-data" style="margin-bottom:20px">
      <label class="slot-btn" style="display:inline-block;padding:10px 24px;border-radius:4px;background:var(--gold);color:var(--brown)">
        📂 Dodaj logo partnera
        <input type="file" name="partner_photo" accept="image/*" style="display:none" onchange="this.form.submit()">
      </label>
    </form>
    <?php
      $partners = [];
      foreach (glob(UPLOAD_DIR."partner*.{jpg,jpeg,png,webp,gif,svg}", GLOB_BRACE) ?: [] as $p) {
        $f = basename($p); if (preg_match('/^partner(\d+)\./i', $f, $m)) $partners[(int)$m[1]] = $f;
      }
      ksort($partners);
    ?>
    <?php if (empty($partners)): ?>
      <p style="color:#a08878;font-size:.85rem">Brak logotypów — dodaj pierwsze powyżej.</p>
    <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:16px">
        <?php foreach ($partners as $n => $f): ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:6px;padding:12px;width:160px;text-align:center">
          <img src="<?=htmlspecialchars($f)?>?t=<?=filemtime(UPLOAD_DIR.$f)?>" style="max-width:120px;max-height:60px;object-fit:contain;display:block;margin:0 auto 8px" alt="">
          <span style="font-size:.75rem;color:#7A6252;display:block;margin-bottom:8px"><?=htmlspecialchars($f)?></span>
          <form method="post" onsubmit="return confirm('Usunąć to logo?')">
            <input type="hidden" name="del_partner" value="<?=htmlspecialchars($f)?>">
            <button style="background:#c0392b;color:#fff;border:none;padding:4px 12px;border-radius:3px;font-size:.78rem;cursor:pointer">🗑 Usuń</button>
          </form>
        </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>
  </div>

</div>
<?php endif ?>

</body>
</html>
