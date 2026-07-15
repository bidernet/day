<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quote_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: quotes.php'); exit; }
csrf_check();

$id = (int)($_POST['quote_id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM quotes WHERE id=?"); $st->execute([$id]);
$q = $st->fetch();
$back = 'quote_form.php?id=' . $id;

if (!$q) { flash('ההצעה לא נמצאה.'); header('Location: quotes.php'); exit; }
if (empty($q['phone'])) { flash('לא הוזן טלפון ללקוח.'); header('Location: ' . $back); exit; }

// ודא טוקן ציבורי
if (empty($q['public_token'])) {
    $tok = quote_make_token();
    $pdo->prepare("UPDATE quotes SET public_token=? WHERE id=?")->execute([$tok, $id]);
    $q['public_token'] = $tok;
}
$link = rtrim(APP_URL, '/') . '/sign.php?t=' . $q['public_token'];
$res  = greenapi_send_text($q['phone'], quote_reminder_text($q, $link));

if (!empty($res['ok'])) {
    if ($q['status'] === 'draft') $pdo->prepare("UPDATE quotes SET status='sent' WHERE id=?")->execute([$id]);
    flash('נשלח בוואטסאפ אל ' . ($q['client_name'] ?: $q['phone']) . '.');
} else {
    flash('שליחה נכשלה: ' . ($res['error'] ?? 'שגיאה'));
}
header('Location: ' . $back);
exit;
