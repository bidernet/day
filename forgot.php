<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();
ensure_admin_schema($pdo);

$sent = false; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'יש להזין כתובת אימייל תקינה.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        // לא חושפים אם האימייל קיים — תמיד מציגים הצלחה
        if ($u) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600); // שעה
            $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)")
                ->execute([$u['id'], $hash, $expires]);

            $link = rtrim(APP_URL, '/') . '/reset.php?token=' . $token;
            $body = mail_template('איפוס סיסמה',
                '<p>שלום ' . e($u['full_name']) . ',</p>'
              . '<p>התקבלה בקשה לאיפוס הסיסמה שלך במערכת bidernet.</p>'
              . '<p>ללחיצה על הכפתור להגדרת סיסמה חדשה (הקישור תקף לשעה אחת):</p>'
              . '<p style="margin:22px 0"><a href="' . e($link) . '" style="background:#c6f02e;color:#14180b;font-weight:bold;text-decoration:none;padding:12px 22px;border-radius:10px;display:inline-block">איפוס סיסמה</a></p>'
              . '<p style="font-size:13px;color:#6b7480">אם הכפתור לא עובד, העתק את הקישור לדפדפן:<br>' . e($link) . '</p>'
              . '<p style="font-size:13px;color:#6b7480">אם לא ביקשת לאפס סיסמה, אפשר להתעלם מהודעה זו.</p>');
            send_system_mail($email, 'איפוס סיסמה – bidernet', $body);
        }
        $sent = true;
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
<title>שחזור סיסמה · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <img class="logo-img" src="assets/logo.png" alt="bidernet">
    <div class="sub">שחזור סיסמה</div>

    <?php if ($sent): ?>
      <div class="flash" style="margin-bottom:18px">אם קיים חשבון עם האימייל הזה, נשלח אליו קישור לאיפוס.</div>
      <p class="hint" style="color:var(--muted);font-size:13.5px">בדוק את תיבת הדואר (וגם את תיקיית הספאם). הקישור תקף לשעה.</p>
      <a class="btn" href="login.php" style="width:100%;justify-content:center;margin-top:8px">חזרה להתחברות</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>
      <p style="color:var(--muted);font-size:14px;margin:0 0 18px">הזן את האימייל שאיתו נרשמת, ונשלח לך קישור לאיפוס הסיסמה.</p>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label>אימייל</label><input type="email" name="email" required autofocus></div>
        <button class="btn" type="submit" style="width:100%;justify-content:center;padding:12px">שליחת קישור איפוס</button>
      </form>
      <p style="text-align:center;margin-top:16px"><a href="login.php" style="font-size:13.5px;color:var(--muted)">חזרה להתחברות</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
