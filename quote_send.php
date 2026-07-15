<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quotes_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: quotes.php'); exit; }
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM quotes WHERE id=?");
$st->execute([$id]);
$q = $st->fetch();

if (!$q) { flash('ההצעה לא נמצאה.'); header('Location: quotes.php'); exit; }
if (empty($q['phone'])) { flash('לא הוזן טלפון ללקוח.'); header('Location: quote_form.php?id=' . $id); exit; }

$typeLabel = $q['mode'] === 'order' ? 'הזמנת העבודה' : 'הצעת המחיר';
$link = rtrim(APP_URL, '/') . '/quote_sign.php?t=' . $q['public_token'];
$biz  = setting_get($pdo, 'biz_name', 'bidernet');
$msg  = 'שלום' . ($q['client_name'] ? ' ' . $q['client_name'] : '') . ",\n"
      . 'מצורפת ' . $typeLabel . ' מ-' . $biz . ".\n"
      . 'לצפייה' . ($q['mode'] === 'order' ? ' ולחתימה' : '') . ': ' . $link;

$result = greenapi_send_text($q['phone'], $msg);

if (!empty($result['ok'])) {
    if ($q['status'] === 'draft') {
        $pdo->prepare("UPDATE quotes SET status='sent' WHERE id=?")->execute([$id]);
    }
    flash('נשלח בוואטסאפ אל ' . ($q['client_name'] ?: $q['phone']) . '.');
} else {
    flash('השליחה נכשלה: ' . ($result['error'] ?? 'שגיאה'));
}
header('Location: quote_form.php?id=' . $id);
exit;
