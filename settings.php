<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_settings_schema($pdo);

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        setting_set($pdo, 'greenapi_id', trim($_POST['greenapi_id'] ?? ''));
        setting_set($pdo, 'greenapi_token', trim($_POST['greenapi_token'] ?? ''));
        flash('ההגדרות נשמרו.');
        header('Location: settings.php'); exit;
    }
    if ($act === 'test') {
        $phone = trim($_POST['test_phone'] ?? '');
        if ($phone === '') { $notice = 'הזן מספר לבדיקה.'; }
        else {
            $r = greenapi_send_text($phone, 'בדיקת חיבור bidernet ✅');
            $notice = !empty($r['ok']) ? 'נשלחה הודעת בדיקה בהצלחה!' : ('הבדיקה נכשלה: ' . ($r['error'] ?? 'שגיאה'));
        }
    }
}

$cfg = greenapi_cfg();
$connected = greenapi_enabled();

$active = 'settings';
$page_title = 'הגדרות';
include __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert-box <?= strpos($notice,'נכשל')!==false?'alert-error':'alert-success' ?>" style="margin-bottom:14px"><?= e($notice) ?></div><?php endif; ?>

<div class="card">
  <div class="card-head"><h2>חיבור וואטסאפ · GREEN API</h2></div>
  <div class="card-body">
    <div style="margin-bottom:14px">
      <?php if ($connected): ?>
        <span class="badge badge-paid"><span class="pip"></span>מחובר</span>
        <span class="muted">המערכת יכולה לשלוח הודעות וקבצים בוואטסאפ.</span>
      <?php else: ?>
        <span class="badge badge-neutral"><span class="pip"></span>לא מחובר</span>
        <span class="muted">הזן את הפרטים מ-green-api.com כדי להפעיל שליחה בוואטסאפ.</span>
      <?php endif; ?>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="form-grid">
        <div class="field"><label>idInstance</label><input type="text" name="greenapi_id" value="<?= e($cfg['id']) ?>" placeholder="1101234567"></div>
        <div class="field"><label>apiTokenInstance</label><input type="text" name="greenapi_token" value="<?= e($cfg['token']) ?>" placeholder="הטוקן הארוך"></div>
      </div>
      <div class="hint" style="margin-top:8px">משיגים בלוח הבקרה של GREEN API (green-api.com), אחרי סריקת QR וחיבור מספר הוואטסאפ. ודא שהסטטוס שם הוא "authorized".</div>
      <div class="form-actions" style="margin-top:14px"><button class="btn" type="submit">שמור וחבר</button></div>
    </form>

    <?php if ($connected): ?>
    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">
    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <div class="field" style="margin:0"><label>שלח הודעת בדיקה למספר</label><input type="text" name="test_phone" placeholder="0501234567" style="max-width:200px"></div>
      <button class="btn btn-ghost" type="submit">שלח בדיקה</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>כללי</h2></div>
  <div class="card-body"><div class="muted">מע״מ: 17% · שם העסק, לוגו והגדרות נוספות יתווספו כאן בהמשך.</div></div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
