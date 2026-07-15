<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quote_schema($pdo);

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM quotes WHERE id=?"); $st->execute([$id]);
$q = $st->fetch();
if (!$q) { http_response_code(404); die('ההצעה לא נמצאה.'); }
$its = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order"); $its->execute([$id]);
$items = $its->fetchAll();
$QASSET = 'assets/quote';
$signLink = $q['public_token'] ? rtrim(APP_URL,'/') . '/sign.php?t=' . $q['public_token'] : '';
?><!DOCTYPE html><html lang="he" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($q['mode']==='order'?'הזמנת עבודה':'הצעת מחיר') ?> · <?= e($q['client_name']) ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
body{background:#eef1f3;margin:0;padding:20px 0}
.qbar{max-width:600px;margin:0 auto 16px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.qdoc{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 30px rgba(0,0,0,.12)}
.qdoc img{width:100%;display:block}
.qpricing{background:linear-gradient(160deg,#eef4e4,#dfead0);padding:30px 26px}
.qhead{background:#464646;color:#fff;font-size:22px;font-weight:800;padding:11px 22px;border-radius:14px;display:inline-block;margin-bottom:18px}
.qitem{margin-bottom:16px}
.qitem-t{font-weight:800;font-size:16px;display:flex;justify-content:space-between;border-bottom:2px solid #c7d3b4;padding-bottom:5px}
.q-desc{font-size:13px;line-height:1.6;margin:6px 18px 0;padding:0}
.q-desc li{margin:2px 0}
.qtotals{background:#464646;color:#fff;border-radius:12px;padding:14px 20px;text-align:center;margin-top:20px}
.qrow{display:flex;justify-content:space-between;font-size:13px;opacity:.85;padding:1px 0}
.qsum{border-top:1px solid rgba(255,255,255,.3);margin-top:6px;padding-top:8px}
.qsum b{font-size:28px;color:#c6f02e}
@media print{.qbar{display:none}body{background:#fff;padding:0}.qdoc{box-shadow:none;max-width:100%}}
</style></head><body>
<div class="qbar">
  <a class="btn btn-ghost btn-sm" href="quotes.php">→ חזרה</a>
  <button class="btn btn-sm" onclick="window.print()">🖨 הדפס / שמור PDF</button>
  <?php if ($signLink): ?><a class="btn btn-ok btn-sm" href="<?= e($signLink) ?>" target="_blank">🔗 קישור חתימה ללקוח</a><?php endif; ?>
</div>
<?php include __DIR__ . '/includes/quote_document.php'; ?>
</body></html>
