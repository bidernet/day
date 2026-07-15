<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
ensure_quote_schema($pdo);

$token = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
if ($token === '') { http_response_code(404); die('קישור לא תקין.'); }
$st = $pdo->prepare("SELECT * FROM quotes WHERE public_token=?");
$st->execute([$token]);
$q = $st->fetch();
if (!$q) { http_response_code(404); die('המסמך לא נמצא.'); }
$its = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order"); $its->execute([$q['id']]);
$items = $its->fetchAll();

$justSigned = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $q['mode'] === 'order' && $q['status'] !== 'signed') {
    $sig  = $_POST['signature'] ?? '';
    $name = trim($_POST['signer_name'] ?? '');
    if (strpos($sig, 'data:image/') === 0 && strlen($sig) < 2000000) {
        $pdo->prepare("UPDATE quotes SET status='signed', signature_data=?, signer_name=?, signed_at=NOW() WHERE id=?")
            ->execute([$sig, $name, $q['id']]);
        $st->execute([$token]); $q = $st->fetch();
        $justSigned = true;
    }
}
$isOrder = ($q['mode'] === 'order');
$signed  = ($q['status'] === 'signed');
$QASSET  = 'assets/quote';
?><!DOCTYPE html><html lang="he" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($isOrder?'הזמנת עבודה':'הצעת מחיר') ?> · bidernet</title>
<link rel="icon" href="assets/favicon.ico">
<style>
body{font-family:'Heebo',system-ui,Arial,sans-serif;background:#eef1f3;margin:0;padding:16px 0;color:#181b1d}
.qdoc{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 6px 30px rgba(0,0,0,.12)}
.qdoc img{width:100%;display:block}
.qpricing{background:linear-gradient(160deg,#eef4e4,#dfead0);padding:28px 22px}
.qhead{background:#464646;color:#fff;font-size:20px;font-weight:800;padding:10px 20px;border-radius:14px;display:inline-block;margin-bottom:16px}
.qitem{margin-bottom:14px}
.qitem-t{font-weight:800;font-size:15px;display:flex;justify-content:space-between;border-bottom:2px solid #c7d3b4;padding-bottom:5px}
.q-desc{font-size:13px;line-height:1.6;margin:6px 18px 0;padding:0}
.qtotals{background:#464646;color:#fff;border-radius:12px;padding:14px 18px;text-align:center;margin-top:18px}
.qrow{display:flex;justify-content:space-between;font-size:13px;opacity:.85}
.qsum{border-top:1px solid rgba(255,255,255,.3);margin-top:6px;padding-top:8px}.qsum b{font-size:26px;color:#c6f02e}
.card{max-width:560px;margin:16px auto 0;background:#fff;border-radius:12px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.sigpad{border:2px dashed #bcc6c2;border-radius:12px;background:#fff;touch-action:none;width:100%;height:180px;display:block}
.btn{background:#c6f02e;color:#14180b;border:none;border-radius:10px;padding:12px 20px;font-weight:700;font-size:16px;cursor:pointer;font-family:inherit}
.btn-ghost{background:#fff;border:1px solid #dfe4e2}
input[type=text]{width:100%;padding:10px;border:1px solid #dfe4e2;border-radius:9px;font-family:inherit;font-size:15px;box-sizing:border-box}
.ok{background:#e9f9ee;border:1px solid #1aa256;color:#127a41;border-radius:10px;padding:14px;text-align:center;font-weight:700}
.muted{color:#6b7472;font-size:13px}
</style></head><body>

<?php include __DIR__ . '/includes/quote_document.php'; ?>

<?php if ($isOrder): ?>
<div class="card">
  <h2 style="margin-top:0">חתימת הלקוח</h2>
  <?php if ($signed): ?>
    <div class="ok">✓ ההזמנה נחתמה ואושרה<?= $q['signed_at'] ? ' · ' . date('d/m/Y H:i', strtotime($q['signed_at'])) : '' ?></div>
    <?php if ($q['signature_data']): ?><img src="<?= e($q['signature_data']) ?>" style="max-width:260px;border:1px solid #e2e8e6;border-radius:8px;margin-top:12px;background:#fff"><?php endif; ?>
    <?php if ($q['signer_name']): ?><div class="muted" style="margin-top:6px">חתם/ה: <?= e($q['signer_name']) ?></div><?php endif; ?>
  <?php else: ?>
    <form method="post" onsubmit="return doSign()">
      <div style="margin-bottom:10px"><label class="muted">שם החותם/ת</label><input type="text" name="signer_name" id="signer_name" placeholder="שם מלא"></div>
      <div class="muted" style="margin-bottom:8px">חתום כאן באצבע:</div>
      <canvas id="pad" class="sigpad"></canvas>
      <input type="hidden" name="signature" id="signature">
      <div style="display:flex;gap:8px;justify-content:center;margin-top:12px">
        <button type="button" class="btn btn-ghost" onclick="clearPad()">נקה</button>
        <button type="submit" class="btn">✍️ אני מאשר/ת וחותם/ת</button>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="card"><div class="muted" style="text-align:center">זוהי הצעת מחיר לצפייה. לאחר אישורכם היא תהפוך להזמנת עבודה לחתימה.</div></div>
<?php endif; ?>

<div style="text-align:center;margin:18px 0;color:#9aa3a0;font-size:12px">bidernet · הצעות מחיר</div>

<script>
let pad,ctx,drawing=false,dirty=false;
function initPad(){pad=document.getElementById('pad');if(!pad)return;const r=pad.getBoundingClientRect();pad.width=r.width;pad.height=r.height;ctx=pad.getContext('2d');ctx.lineWidth=2.5;ctx.lineCap='round';ctx.strokeStyle='#14180b';
 const pos=e=>{const b=pad.getBoundingClientRect();const t=e.touches?e.touches[0]:e;return{x:t.clientX-b.left,y:t.clientY-b.top};};
 const st=e=>{drawing=true;dirty=true;const p=pos(e);ctx.beginPath();ctx.moveTo(p.x,p.y);e.preventDefault();};
 const mv=e=>{if(!drawing)return;const p=pos(e);ctx.lineTo(p.x,p.y);ctx.stroke();e.preventDefault();};
 const en=()=>{drawing=false;};
 pad.addEventListener('mousedown',st);pad.addEventListener('mousemove',mv);window.addEventListener('mouseup',en);
 pad.addEventListener('touchstart',st,{passive:false});pad.addEventListener('touchmove',mv,{passive:false});pad.addEventListener('touchend',en);}
function clearPad(){if(ctx){ctx.clearRect(0,0,pad.width,pad.height);dirty=false;}}
function doSign(){if(!dirty){alert('נא לחתום קודם ✍️');return false;}document.getElementById('signature').value=pad.toDataURL('image/png');return true;}
initPad();
</script>
</body></html>
