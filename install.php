<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();

// יצירת הטבלאות (בטוח להריץ שוב — לא ימחק נתונים קיימים)
$schema = "
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(160) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  id_number VARCHAR(40) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  join_date DATE DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS debts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_date DATE DEFAULT NULL,
  status ENUM('open','paid') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer (customer_id),
  INDEX idx_status_due (status, due_date),
  CONSTRAINT fk_debt_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  debt_id INT DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_date DATE NOT NULL,
  method VARCHAR(60) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customer (customer_id),
  INDEX idx_debt (debt_id),
  CONSTRAINT fk_pay_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_debt FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token_hash),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

$error = '';
$created = false;

try {
    $pdo->exec($schema);
} catch (PDOException $e) {
    $error = 'יצירת הטבלאות נכשלה: ' . $e->getMessage();
}

$has_admin = false;
if (!$error) {
    $has_admin = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && !$has_admin) {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $pass      = $_POST['password'] ?? '';
    if ($username === '' || $full_name === '' || $email === '' || strlen($pass) < 6) {
        $error = 'מלא את כל השדות. הסיסמה חייבת להכיל לפחות 6 תווים.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'כתובת אימייל לא תקינה.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?,?,?,?)");
        $stmt->execute([$username, $email, password_hash($pass, PASSWORD_DEFAULT), $full_name]);
        $created = true;
        $has_admin = true;
    }
}
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>התקנת המערכת · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <img class="logo-img" src="assets/logo.png" alt="bidernet">
    <div class="sub">התקנת המערכת</div>

    <?php if ($error): ?>
      <div class="alert-box alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$error && $created): ?>
      <div class="flash">חשבון המנהל נוצר בהצלחה!</div>
      <p class="muted" style="font-size:14px">
        מטעמי אבטחה <strong>מחק כעת את הקובץ install.php</strong> מהשרת.
      </p>
      <a class="btn btn-accent" href="login.php" style="width:100%;justify-content:center">מעבר להתחברות</a>

    <?php elseif (!$error && $has_admin): ?>
      <div class="alert-box alert-error">המערכת כבר הותקנה. מחק את install.php מהשרת.</div>
      <a class="btn" href="login.php" style="width:100%;justify-content:center">מעבר להתחברות</a>

    <?php elseif (!$error): ?>
      <p class="muted" style="font-size:14px;margin-bottom:20px">הטבלאות נוצרו. כעת צור את חשבון המנהל הראשי.</p>
      <form method="post">
        <div class="field">
          <label>שם מלא <span class="req">*</span></label>
          <input type="text" name="full_name" required>
        </div>
        <div class="field">
          <label>אימייל <span class="req">*</span></label>
          <input type="email" name="email" required>
          <div class="hint">ישמש לשחזור סיסמה</div>
        </div>
        <div class="field">
          <label>שם משתמש <span class="req">*</span></label>
          <input type="text" name="username" required autocomplete="username">
        </div>
        <div class="field">
          <label>סיסמה <span class="req">*</span></label>
          <input type="password" name="password" required minlength="6" autocomplete="new-password">
          <div class="hint">לפחות 6 תווים</div>
        </div>
        <button class="btn btn-accent" type="submit">צור חשבון והתקן</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
