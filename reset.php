<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();
ensure_admin_schema($pdo);

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = ''; $done = false; $validToken = false; $row = null;

if ($token !== '') {
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token_hash=? AND used=0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    $validToken = (bool)$row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    csrf_check();
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password2'] ?? '';
    if (strlen($p1) < 6) {
        $error = 'הסיסמה חייבת להכיל לפחות 6 תווים.';
    } elseif ($p1 !== $p2) {
        $error = 'הסיסמאות אינן תואמות.';
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
            ->execute([password_hash($p1, PASSWORD_DEFAULT), $row['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$row['id']]);
        // ביטול בקשות פתוחות אחרות של אותו משתמש
        $pdo->prepare("UPDATE password_resets SET used=1 WHERE user_id=? AND used=0")->execute([$row['user_id']]);
        $pdo->commit();
        $done = true;
    }
}
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<title>סיסמה חדשה · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <img class="logo-img" src="assets/logo.png" alt="bidernet">
    <div class="sub">הגדרת סיסמה חדשה</div>

    <?php if ($done): ?>
      <div class="flash" style="margin-bottom:18px">הסיסמה עודכנה בהצלחה.</div>
      <a class="btn" href="login.php" style="width:100%;justify-content:center">כניסה למערכת</a>

    <?php elseif (!$validToken): ?>
      <div class="alert-box alert-error">הקישור אינו תקף או שפג תוקפו. בקש קישור חדש.</div>
      <a class="btn" href="forgot.php" style="width:100%;justify-content:center">בקשת קישור חדש</a>

    <?php else: ?>
      <?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field"><label>סיסמה חדשה</label><input type="password" name="password" required minlength="6" autofocus autocomplete="new-password"></div>
        <div class="field"><label>אימות סיסמה</label><input type="password" name="password2" required minlength="6" autocomplete="new-password"></div>
        <button class="btn" type="submit" style="width:100%;justify-content:center;padding:12px">שמירת סיסמה</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
