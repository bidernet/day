<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_hosting_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: hosting.php'); exit; }
csrf_check();

// יעד חזרה בטוח (נתיב יחסי בלבד)
$return = $_POST['return'] ?? 'hosting.php';
if (!preg_match('/^[a-zA-Z0-9_]+\.php(\?[^\s"\']*)?$/', $return)) $return = 'hosting.php';

$id = (int)($_POST['hosting_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM hosting WHERE id=?");
$stmt->execute([$id]);
$site = $stmt->fetch();

if (!$site) {
    flash('האתר לא נמצא.');
    header('Location: ' . $return); exit;
}
if (empty($site['phone'])) {
    flash('לא הוגדר טלפון לאתר זה.');
    header('Location: ' . $return); exit;
}

$result = megasend_send_text($site['phone'], renewal_reminder_text($site));

if (!empty($result['ok'])) {
    $pdo->prepare("UPDATE hosting SET last_reminder_at = NOW() WHERE id=?")->execute([$id]);
    flash('תזכורת נשלחה בוואטסאפ אל ' . ($site['customer_name'] ?: $site['domain']) . '.');
} else {
    flash('שליחת התזכורת נכשלה: ' . ($result['error'] ?? 'שגיאה לא ידועה'));
}

header('Location: ' . $return);
exit;
