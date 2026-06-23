<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT id FROM customers WHERE id=?");
$stmt->execute([$id]);
if (!$stmt->fetch()) { http_response_code(404); die('הלקוח לא נמצא.'); }

// החובות והתשלומים יימחקו אוטומטית (ON DELETE CASCADE)
$pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
flash('הלקוח נמחק.');
header('Location: customers.php');
exit;
