<?php
/**
 * משתנים שאפשר להגדיר בעמוד לפני include:
 *   $active     - מזהה העמוד הפעיל בתפריט (dashboard|customers|debts)
 *   $page_title - כותרת העמוד
 */
$active = $active ?? '';
$page_title = $page_title ?? APP_NAME;
$user = current_user();

// ספירת חייבים להצגת תג בתפריט
$debtor_count = (int) db()->query("
    SELECT COUNT(DISTINCT d.customer_id)
    FROM debts d
    WHERE d.status = 'open'
      AND (d.amount - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.debt_id = d.id),0)) > 0
")->fetchColumn();

// ספירת חידושי אחסון שדורשים תשומת לב (פג / עד 30 יום)
$hosting_alert_count = 0;
try {
    $hosting_alert_count = (int) db()->query("
        SELECT COUNT(*) FROM hosting
        WHERE status='active' AND renewal_date IS NOT NULL
          AND renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ")->fetchColumn();
} catch (PDOException $e) { /* הטבלה תיווצר בכניסה ללוח הבקרה */ }

// ברכה לפי שעה
$h = (int) date('G');
$greet = $h < 12 ? 'בוקר טוב' : ($h < 17 ? 'צהריים טובים' : ($h < 21 ? 'ערב טוב' : 'לילה טוב'));

function nav_icon($name) {
    $icons = [
        'dashboard' => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
        'customers' => '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>',
        'debts'     => '<path d="M12 1l9 4v6c0 5.55-3.84 10.74-9 12-5.16-1.26-9-6.45-9-12V5l9-4zm-1 6v2h2V7h-2zm0 4v6h2v-6h-2z"/>',
        'admins'    => '<path d="M10.5 12c1.93 0 3.5-1.57 3.5-3.5S12.43 5 10.5 5 7 6.57 7 8.5 8.57 12 10.5 12zm0-5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM3 18v1h11v-4.5c0-2.33-4.17-3.5-6.5-3.5S3 12.17 3 14.5V18zm2-3.5c0-.58 2.53-1.5 4.5-1.5s4.5.92 4.5 1.5V17H5v-2.5zM20.5 12l1.5-1.06-.9-1.56-1.76.5a3 3 0 00-.6-.35l-.44-1.78h-1.8l-.44 1.78a3 3 0 00-.6.35l-1.76-.5-.9 1.56L13 12l-1.5 1.06.9 1.56 1.76-.5c.18.14.39.26.6.35l.44 1.78h1.8l.44-1.78c.21-.09.42-.21.6-.35l1.76.5.9-1.56L20.5 12zm-3 1.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>',
        'hosting'   => '<path d="M20 13H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-6c0-.55-.45-1-1-1zM7 19c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM20 3H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1zM7 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
}
$initial = mb_substr(trim($user['name']), 0, 1, 'UTF-8');
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand"><img class="logo-img" src="assets/logo.png" alt="bidernet"></div>
    <nav class="nav">
      <div class="nav-section">ניווט</div>
      <a href="index.php" class="<?= $active==='dashboard'?'active':'' ?>"><?= nav_icon('dashboard') ?><span class="t">ראשי</span></a>
      <a href="customers.php" class="<?= $active==='customers'?'active':'' ?>"><?= nav_icon('customers') ?><span class="t">לקוחות</span></a>
      <a href="debts.php" class="<?= $active==='debts'?'active':'' ?>"><?= nav_icon('debts') ?><span class="t">חובות והתראות</span><?php if ($debtor_count>0): ?><span class="badge-count num"><?= $debtor_count ?></span><?php endif; ?></a>
      <a href="hosting.php" class="<?= $active==='hosting'?'active':'' ?>"><?= nav_icon('hosting') ?><span class="t">אחסון אתרים</span><?php if ($hosting_alert_count>0): ?><span class="badge-count num"><?= $hosting_alert_count ?></span><?php endif; ?></a>
      <a href="admins.php" class="<?= $active==='admins'?'active':'' ?>"><?= nav_icon('admins') ?><span class="t">מנהלים</span></a>
    </nav>
    <div class="side-foot">
      <span class="who"><span class="ava"><?= e($initial) ?></span><?= e($user['name']) ?></span>
      <a href="logout.php">יציאה</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="greet">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7a5 5 0 100 10 5 5 0 000-10zm0-5a1 1 0 011 1v2a1 1 0 11-2 0V3a1 1 0 011-1zm0 17a1 1 0 011 1v2a1 1 0 11-2 0v-2a1 1 0 011-1zM4.22 4.22a1 1 0 011.42 0l1.4 1.4A1 1 0 115.64 7L4.22 5.64a1 1 0 010-1.42zM17 17a1 1 0 011.4 0l1.42 1.4a1 1 0 11-1.42 1.42L17 18.4a1 1 0 010-1.4zM2 12a1 1 0 011-1h2a1 1 0 110 2H3a1 1 0 01-1-1zm17 0a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1zM4.22 19.78a1 1 0 010-1.42L5.64 17A1 1 0 117 18.36l-1.4 1.42a1 1 0 01-1.42 0zM17 7a1 1 0 010-1.4l1.4-1.42A1 1 0 1119.82 5.6L18.4 7A1 1 0 0117 7z"/></svg>
        <span class="nm"><?= e($greet) ?>, <b><?= e($user['name']) ?></b></span>
      </div>
      <div class="spacer"></div>
      <a class="btn" href="customer_form.php"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>לקוח חדש</a>
    </header>
    <div class="content">
<?php if ($f = flash()): ?>
      <div class="flash">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <?= e($f) ?>
      </div>
<?php endif; ?>
