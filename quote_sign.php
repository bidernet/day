<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
ensure_quotes_schema($pdo);

$token = $_GET['t'] ?? '';
$st = $pdo->prepare("SELECT * FROM quotes WHERE public_token=?");
$st->execute([$token]);
$q = $st->fetch();
if (!$q) { http_response_code(404); die('המסמך לא נמצא.'); }

$ist = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order");
$ist->execute([$q['id']]);
$items = $ist->fetchAll();
$tot = quote_totals($items);

// חתימה
$justSigned = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sign' && $q['mode'] === 'order' && $q['status'] !== 'signed') {
    $sig = $_POST['signature'] ?? '';
    $name = trim($_POST['signer_name'] ?? '');
    if (strpos($sig, 'data:image') === 0 && strlen($sig) < 500000) {
        $pdo->prepare("UPDATE quotes SET signature=?, signer_name=?, signed_at=NOW(), status='signed' WHERE id=?")
            ->execute([$sig, $name, $q['id']]);
        $st->execute([$token]); $q = $st->fetch();
        $justSigned = true;
    }
}

$typeLabel = $q['mode'] === 'order' ? 'הזמנת עבודה' : 'הצעת מחיר';
function money_il($n){ return '₪' . number_format((float)$n, 0); }
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon.ico" sizes="any">
<title><?= e($typeLabel) ?> · <?= e($q['client_name']) ?></title>
<style>
:root{--ink2:#14180b;--lime:#c6f02e;--line:#e2e8e6;--muted:#6b7472;--green:#1aa256}
*{box-sizing:border-box}
body{font-family:'Heebo',system-ui,Arial,sans-serif;margin:0;background:#eef1f0;color:#181b1d}
.doc{max-width:620px;margin:0 auto;background:#fff}
.doc img{width:100%;display:block}
.cover{position:relative}
.cover .ov{position:absolute;top:6%;right:7%;text-align:right}
.cover .ov .pill{background:#464646;color:#deedce;font-weight:700;font-size:14px;padding:6px 16px;border-radius:8px;white-space:nowrap;display:inline-block}
.cover .ov .title{background:#464646;color:#deedce;font-weight:800;font-size:26px;padding:10px 24px;border-radius:12px;white-space:nowrap;margin-top:10px}
.cover .ov .client{color:#2f342c;font-size:20px;font-weight:800;margin-top:10px}
.pp{background:linear-gradient(160deg,#eef4e4,#dfead0);padding:30px 26px}
.pp .bar{background:#464646;color:#fff;font-size:22px;font-weight:800;padding:11px 20px;border-radius:14px;display:inline-block;margin-bottom:18px}
.pp .b{margin-bottom:16px}
.pp .bt{font-weight:800;font-size:16px;display:flex;justify-content:space-between;border-bottom:2px solid #c7d3b4;padding-bottom:5px}
.pp .bd{font-size:13px;line-height:1.6;margin-top:6px}
.pp .bd ul{margin:6px 0 0;padding-inline-start:18px}
.sumbox{background:#464646;color:#fff;border-radius:12px;padding:14px 20px;text-align:center;margin-top:20px}
.sumbox .r{display:flex;justify-content:space-between;font-size:13px;opacity:.85;padding:2px 0}
.sumbox .big{border-top:1px solid rgba(255,255,255,.3);margin-top:6px;padding-top:8px}
.sumbox .big b{font-size:28px;color:var(--lime)}
.signwrap{max-width:620px;margin:16px auto;padding:0 12px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px}
h2{font-size:17px;margin:0 0 12px}
canvas{border:2px dashed #bcc6c2;border-radius:12px;background:#fff;touch-action:none;width:100%;height:190px;display:block}
input[type=text]{width:100%;padding:10px;border:1px solid var(--line);border-radius:9px;font-size:15px;margin-bottom:10px}
.btn{background:var(--lime);color:var(--ink2);border:none;border-radius:10px;padding:12px 18px;font-weight:700;font-size:15px;cursor:pointer;font-family:inherit}
.btn-ghost{background:#fff;border:1px solid var(--line)}
.actions{display:flex;gap:8px;justify-content:center;margin-top:12px}
.signed{background:#e9f9ee;border:1px solid var(--green);color:#127a41;border-radius:10px;padding:14px;text-align:center;font-weight:600}
.muted{color:var(--muted)}
</style>
</head>
<body>
<div class="doc">
  <div class="cover">
    <img src="assets/quote/p1.jpg" alt="">
    <div class="ov">
      <div class="pill">תאריך מסמך: <?= e(fmt_date($q['doc_date'])) ?></div>
      <div class="title"><?= e($typeLabel) ?></div>
      <div class="client"><?= e($q['client_name']) ?></div>
    </div>
  </div>
  <img src="assets/quote/p2.jpg" alt="">
  <img src="assets/quote/p3.jpg" alt="">
  <img src="assets/quote/p4.jpg" alt="">

  <div class="pp">
    <div class="bar"><?= e($q['heading'] ?: 'הצעת מחיר') ?></div>
    <?php foreach ($items as $it): ?>
      <div class="b">
        <div class="bt"><span><?= e($it['name']) ?></span><span><?= money_il(((float)$it['price'])*max(1,(int)$it['qty'])) ?></span></div>
        <?php if ($it['description']): ?><div class="bd"><?= quote_desc_html($it['description'], $it['fmt']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="sumbox">
      <div class="r"><span>סכום ביניים</span><span><?= money_il($tot['sub']) ?></span></div>
      <div class="r"><span>מע״מ 17%</span><span><?= money_il($tot['vat']) ?></span></div>
      <div class="big"><b><?= money_il($tot['total']) ?></b><div style="font-size:12px">סה״כ כולל מע״מ · תוקף <?= (int)$q['validity_days'] ?> ימי עסקים</div></div>
    </div>
  </div>

  <?php if ($q['mode'] === 'order'): ?>
    <img src="assets/quote/p6.jpg" alt="">
  <?php endif; ?>
</div>

<?php if ($q['mode'] === 'order'): ?>
<div class="signwrap">
  <div class="card">
    <h2>חתימת הלקוח</h2>
    <?php if ($q['status'] === 'signed'): ?>
      <div class="signed">✓ ההזמנה נחתמה ואושרה<?= $q['signer_name'] ? ' על ידי ' . e($q['signer_name']) : '' ?> · <?= e(fmt_date($q['signed_at'])) ?></div>
      <?php if ($q['signature']): ?><img src="<?= e($q['signature']) ?>" style="max-width:260px;border:1px solid var(--line);border-radius:8px;margin-top:12px;background:#fff"><?php endif; ?>
    <?php else: ?>
      <form method="post" id="sform">
        <input type="hidden" name="action" value="sign">
        <input type="hidden" name="signature" id="sig">
        <input type="text" name="signer_name" placeholder="שם החותם" required>
        <div class="muted" style="font-size:13px;margin-bottom:8px">חתום כאן באצבע:</div>
        <canvas id="pad"></canvas>
        <div class="actions">
          <button type="button" class="btn btn-ghost" onclick="clearPad()">נקה</button>
          <button type="submit" class="btn" onclick="return prepSign()">✍️ אני מאשר וחותם</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="signwrap"><div class="card muted" style="text-align:center">זוהי הצעת מחיר לצפייה. לאחר אישור, היא תהפוך להזמנת עבודה לחתימה.</div></div>
<?php endif; ?>

<script>
var pad=document.getElementById('pad'), ctx, drawing=false, dirty=false;
if(pad){
  function fit(){var r=pad.getBoundingClientRect();pad.width=r.width;pad.height=r.height;ctx=pad.getContext('2d');ctx.lineWidth=2.5;ctx.lineCap='round';ctx.strokeStyle='#14180b';}
  fit();
  function pos(e){var b=pad.getBoundingClientRect();var t=e.touches?e.touches[0]:e;return{x:t.clientX-b.left,y:t.clientY-b.top};}
  function st(e){drawing=true;dirty=true;var p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);e.preventDefault();}
  function mv(e){if(!drawing)return;var p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();e.preventDefault();}
  function en(){drawing=false;}
  pad.addEventListener('mousedown',st);pad.addEventListener('mousemove',mv);window.addEventListener('mouseup',en);
  pad.addEventListener('touchstart',st,{passive:false});pad.addEventListener('touchmove',mv,{passive:false});pad.addEventListener('touchend',en);
}
function clearPad(){if(ctx){ctx.clearRect(0,0,pad.width,pad.height);dirty=false;}}
function prepSign(){if(!dirty){alert('נא לחתום קודם ✍️');return false;}document.getElementById('sig').value=pad.toDataURL('image/png');return true;}
</script>
</body>
</html>
