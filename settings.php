<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_settings_schema($pdo);

$msg = '';
$test_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        setting_set($pdo, 'greenapi_id', trim($_POST['greenapi_id'] ?? ''));
        setting_set($pdo, 'greenapi_token', trim($_POST['greenapi_token'] ?? ''));
        setting_set($pdo, 'biz_name', trim($_POST['biz_name'] ?? ''));
        flash('ההגדרות נשמרו.');
        header('Location: settings.php'); exit;
    }
    if ($action === 'test') {
        // getStateInstance כדי לוודא חיבור
        $cfg = greenapi_cfg();
        if ($cfg['id'] === '' || $cfg['token'] === '') {
            $test_result = ['ok' => false, 'msg' => 'קודם שמור idInstance ו-apiTokenInstance.'];
        } elseif (!function_exists('curl_init')) {
            $test_result = ['ok' => false, 'msg' => 'cURL אינו זמין בשרת.'];
        } else {
            $url = rtrim(GREENAPI_API_URL, '/') . '/waInstance' . $cfg['id'] . '/getStateInstance/' . $cfg['token'];
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
            $resp = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            $data = json_decode($resp, true);
            if ($code === 200 && is_array($data) && ($data['stateInstance'] ?? '') === 'authorized') {
                $test_result = ['ok' => true, 'msg' => 'מחובר ומאומת (authorized) ✓'];
            } elseif ($code === 200 && is_array($data) && isset($data['stateInstance'])) {
                $test_result = ['ok' => false, 'msg' => 'מצב: ' . $data['stateInstance'] . ' — חבר את המספר ב-green-api.com (סרוק QR).'];
            } else {
                $test_result = ['ok' => false, 'msg' => 'החיבור נכשל (קוד ' . $code . '). בדוק את ה-idInstance וה-Token.'];
            }
        }
    }
}

$ga_id    = setting_get($pdo, 'greenapi_id', '');
$ga_token = setting_get($pdo, 'greenapi_token', '');
$biz_name = setting_get($pdo, 'biz_name', '');
$connected = greenapi_enabled();

$active = 'settings';
$page_title = 'הגדרות';
include __DIR__ . '/includes/header.php';
?>

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

    <?php if ($test_result): ?>
      <div class="alert-box <?= $test_result['ok'] ? 'alert-ok' : 'alert-error' ?>"><?= e($test_result['msg']) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="form-grid">
        <div class="field"><label>idInstance</label><input type="text" name="greenapi_id" value="<?= e($ga_id) ?>" placeholder="1101234567"></div>
        <div class="field"><label>apiTokenInstance</label><input type="text" name="greenapi_token" value="<?= e($ga_token) ?>" placeholder="הטוקן הארוך"></div>
        <div class="field full"><label>שם העסק (יופיע בהודעות)</label><input type="text" name="biz_name" value="<?= e($biz_name) ?>" placeholder="bidernet"></div>
      </div>
      <div class="hint">את הערכים משיגים בלוח הבקרה של GREEN API (green-api.com), לאחר סריקת QR וחיבור מספר הוואטסאפ.</div>
      <div class="form-actions" style="margin-top:14px"><button class="btn" type="submit">שמור</button></div>
    </form>

    <?php if ($connected): ?>
    <form method="post" style="margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <button class="btn btn-ghost" type="submit">בדוק חיבור (getStateInstance)</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
