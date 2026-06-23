<?php
require_once __DIR__ . '/includes/auth.php';

// כבר מחובר? לדשבורד
if (!empty($_SESSION['user_id'])) { header('Location: ./'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $stmt = db()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $u['id'];
        $_SESSION['user_name'] = $u['full_name'];
        header('Location: ./');
        exit;
    }
    $error = 'שם משתמש או סיסמה שגויים.';
}
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<title>התחברות · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <img class="logo-img" src="assets/logo.png" alt="bidernet">
    <div class="sub">ניהול לקוחות וחובות</div>

    <?php if ($error): ?>
      <div class="alert-box alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label>שם משתמש</label>
        <input type="text" name="username" required autofocus autocomplete="username">
      </div>
      <div class="field">
        <label>סיסמה</label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-accent" type="submit">כניסה למערכת</button>
    </form>
    <p style="text-align:center;margin-top:16px"><a href="forgot.php" style="font-size:13.5px;color:var(--muted)">שכחת סיסמה?</a></p>
  </div>
</div>
</body>
</html>
