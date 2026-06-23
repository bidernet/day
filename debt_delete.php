<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT customer_id FROM debts WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); die('החוב לא נמצא.'); }

$pdo->prepare("DELETE FROM debts WHERE id=?")->execute([$id]);
flash('החוב נמחק.');
header('Location: customer_view.php?id=' . (int)$row['customer_id']);
exit;
