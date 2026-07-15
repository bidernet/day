<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quotes_schema($pdo);

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$error = '';

// שמירה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_check();
    $id           = (int)($_POST['id'] ?? 0);
    $client_name  = trim($_POST['client_name'] ?? '');
    $customer_id  = (int)($_POST['customer_id'] ?? 0) ?: null;
    $phone        = trim($_POST['phone'] ?? '');
    $heading      = trim($_POST['heading'] ?? '');
    $doc_date     = $_POST['doc_date'] ?: null;
    $validity     = max(1, (int)($_POST['validity_days'] ?? 10));
    $mode         = ($_POST['mode'] ?? 'quote') === 'order' ? 'order' : 'quote';
    $doc_no       = (int)($_POST['doc_no'] ?? 0) ?: quote_next_docno($pdo);
    $items        = json_decode($_POST['items_json'] ?? '[]', true);
    if (!is_array($items)) $items = [];

    if ($client_name === '') {
        $error = 'יש לבחור לקוח או להזין שם.';
    } else {
        if ($id > 0) {
            $pdo->prepare("UPDATE quotes SET doc_no=?, customer_id=?, client_name=?, phone=?, heading=?, doc_date=?, validity_days=?, mode=? WHERE id=?")
                ->execute([$doc_no,$customer_id,$client_name,$phone,$heading,$doc_date,$validity,$mode,$id]);
        } else {
            $token = quote_token();
            $pdo->prepare("INSERT INTO quotes (doc_no, customer_id, client_name, phone, heading, doc_date, validity_days, mode, status, public_token) VALUES (?,?,?,?,?,?,?,?,'draft',?)")
                ->execute([$doc_no,$customer_id,$client_name,$phone,$heading,$doc_date,$validity,$mode,$token]);
            $id = (int)$pdo->lastInsertId();
        }
        // כתיבת שורות מחדש
        $pdo->prepare("DELETE FROM quote_items WHERE quote_id=?")->execute([$id]);
        $ins = $pdo->prepare("INSERT INTO quote_items (quote_id,name,description,fmt,price,qty,sort_order) VALUES (?,?,?,?,?,?,?)");
        $ord = 0;
        foreach ($items as $it) {
            $ins->execute([
                $id,
                mb_substr(trim($it['name'] ?? ''), 0, 190),
                (string)($it['desc'] ?? ''),
                (($it['fmt'] ?? 'bullets') === 'text' ? 'text' : 'bullets'),
                (float)($it['price'] ?? 0),
                max(1, (int)($it['qty'] ?? 1)),
                $ord++
            ]);
        }
        flash('ההצעה נשמרה.');
        $dest = ($_POST['after'] ?? '') === 'client' ? ('quote_sign.php?t=' . quote_get_token($pdo,$id)) : 'quotes.php';
        header('Location: ' . $dest); exit;
    }
}

function quote_get_token($pdo,$id){ $st=$pdo->prepare("SELECT public_token FROM quotes WHERE id=?"); $st->execute([$id]); return $st->fetchColumn(); }

// המרה להזמנת עבודה / בחזרה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['to_order','to_quote'])) {
    csrf_check();
    $mode = $_POST['action'] === 'to_order' ? 'order' : 'quote';
    $pdo->prepare("UPDATE quotes SET mode=? WHERE id=?")->execute([$mode, $id]);
    header('Location: quote_form.php?id=' . $id); exit;
}

// טעינת נתונים
$q = ['id'=>0,'doc_no'=>quote_next_docno($pdo),'customer_id'=>null,'client_name'=>'','phone'=>'','heading'=>'','doc_date'=>date('Y-m-d'),'validity_days'=>10,'mode'=>'quote','status'=>'draft','public_token'=>null];
$items = [];
if ($isEdit) {
    $st = $pdo->prepare("SELECT * FROM quotes WHERE id=?"); $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) { http_response_code(404); die('ההצעה לא נמצאה.'); }
    $q = $found;
    $ist = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order"); $ist->execute([$id]);
    foreach ($ist->fetchAll() as $r) $items[] = ['name'=>$r['name'],'desc'=>$r['description'],'fmt'=>$r['fmt'],'price'=>(float)$r['price'],'qty'=>(int)$r['qty']];
}

$customers = $pdo->query("SELECT id,name,phone FROM customers ORDER BY name")->fetchAll();
$catalog   = $pdo->query("SELECT id,name,description,fmt,price FROM service_catalog ORDER BY sort_order,name")->fetchAll();

$active = 'quotes';
$page_title = 'הצעות מחיר';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="quotes.php">→ חזרה לרשימה</a>
  <div class="spacer"></div>
  <?php if ($isEdit): ?>
    <form method="post" style="display:inline">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $q['mode']==='order' ? 'to_quote' : 'to_order' ?>">
      <button class="btn <?= $q['mode']==='order' ? 'btn-ghost' : 'btn-dark' ?> btn-sm" type="submit"><?= $q['mode']==='order' ? '← חזור להצעה' : '↔ הפוך להזמנת עבודה' ?></button>
    </form>
  <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="qform">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
  <input type="hidden" name="mode" value="<?= e($q['mode']) ?>">
  <input type="hidden" name="customer_id" id="customer_id" value="<?= (int)($q['customer_id'] ?? 0) ?>">
  <input type="hidden" name="items_json" id="items_json" value="">
  <input type="hidden" name="after" id="after" value="">

  <div class="card">
    <div class="card-body">
      <h2 style="margin:0"><?= $q['mode']==='order' ? 'הזמנת עבודה' : 'הצעת מחיר' ?>
        <span class="badge <?= $q['mode']==='order' ? 'badge-soon' : 'badge-neutral' ?>"><?= $q['mode']==='order' ? 'הזמנה' : 'הצעה' ?></span>
        <?php if ($q['status']==='signed'): ?><span class="badge badge-paid"><span class="pip"></span>חתום</span><?php endif; ?>
      </h2>
      <div class="form-grid" style="margin-top:14px">
        <div class="field"><label>לקוח <span class="req">*</span></label>
          <select id="client_select" onchange="pickClient()">
            <option value="">— בחר לקוח —</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= (int)$c['id'] ?>" data-phone="<?= e($c['phone']) ?>" data-name="<?= e($c['name']) ?>" <?= ($q['customer_id']==$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="client_name" id="client_name" value="<?= e($q['client_name']) ?>">
        </div>
        <div class="field"><label>טלפון לקוח (בלי 972)</label><input type="text" id="phone" name="phone" value="<?= e($q['phone']) ?>" placeholder="0501234567"><div class="hint">לשליחת המסמך בוואטסאפ</div></div>
        <div class="field"><label>כותרת עמוד המחירים</label><input type="text" name="heading" value="<?= e($q['heading']) ?>" placeholder="למשל: פרסום סושיאל מדיה"></div>
        <div class="field"><label>תאריך</label><input type="date" name="doc_date" value="<?= e($q['doc_date']) ?>"></div>
        <div class="field"><label>מספר מסמך</label><input type="number" name="doc_no" value="<?= (int)$q['doc_no'] ?>"></div>
        <div class="field"><label>תוקף (ימי עסקים)</label><input type="number" name="validity_days" value="<?= (int)$q['validity_days'] ?>"></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>סמן שירותים מהקטלוג ✓</h2></div>
    <div class="card-body">
      <?php if (!$catalog): ?>
        <div class="muted">הקטלוג ריק. <a href="quote_catalog.php">הוסף שירותים לקטלוג</a> כדי לבחור מהם.</div>
      <?php endif; ?>
      <div id="catalogList"></div>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
        <input id="free_name" placeholder="פריט חופשי (שם)" style="flex:1;min-width:150px">
        <input id="free_price" type="number" placeholder="מחיר" style="max-width:120px">
        <button type="button" class="btn btn-ghost btn-sm" onclick="addFree()">+ שורה חופשית</button>
      </div>
      <table class="tot" style="width:100%;margin-top:16px">
        <tr><td>סכום ביניים</td><td class="t-left num" id="t_sub" style="font-weight:700">₪0</td></tr>
        <tr><td class="muted">מע״מ 17%</td><td class="t-left num muted" id="t_vat">₪0</td></tr>
        <tr><td style="font-size:19px;border-top:2px solid #14180b;padding-top:8px">סה״כ</td><td class="t-left num" id="t_total" style="font-size:19px;font-weight:800;border-top:2px solid #14180b;padding-top:8px">₪0</td></tr>
      </table>
    </div>
  </div>

  <div class="card" style="text-align:center">
    <button class="btn" type="submit" onclick="document.getElementById('after').value=''">💾 שמור</button>
    <button class="btn btn-dark" type="submit" onclick="document.getElementById('after').value='client'">📱 שמור וצפה כלקוח →</button>
  </div>
</form>

<?php if ($isEdit && $q['public_token']): ?>
<div class="card">
  <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div>
      <strong>שליחה ללקוח</strong>
      <div class="muted" style="font-size:13px">שלח קישור לצפייה<?= $q['mode']==='order' ? ' וחתימה' : '' ?> בוואטסאפ. שמור שינויים לפני השליחה.</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-ghost btn-sm" href="quote_sign.php?t=<?= e($q['public_token']) ?>" target="_blank" rel="noopener">פתח מסך לקוח</a>
      <form method="post" action="quote_send.php" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
        <button class="btn btn-ok btn-sm" type="submit">💬 שלח בוואטסאפ</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
.q-item{border:1px solid var(--line);border-radius:12px;padding:14px;margin-bottom:12px}
.q-item.sel{border-color:var(--lime-d);background:#fbfee9}
.q-head{display:flex;align-items:flex-start;gap:10px}
.q-head input[type=checkbox]{width:20px;height:20px;margin-top:2px}
.q-head .nm{flex:1;font-weight:700}
.q-bul{margin:6px 0 0;padding-inline-start:18px;font-size:13px;color:#4a524f;line-height:1.6}
.q-txt{font-size:13px;color:#4a524f;line-height:1.6;margin-top:6px}
.q-edit{margin-top:10px}
.q-edit textarea{width:100%;min-height:70px;font-family:inherit;font-size:13px;padding:8px;border:1px solid var(--line);border-radius:8px}
.q-edit .row{display:flex;gap:8px;margin-top:6px;align-items:center}
</style>

<script>
const CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>;
let items = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;   // {name,desc,fmt,price,qty, catId?}
const VAT = 0.17;
function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(n){return '₪'+Math.round(+n||0).toLocaleString('he-IL');}

// זיהוי אילו שירותי קטלוג נבחרו (לפי שם — לפריטים שמקורם בקטלוג)
function findItemByCat(cat){ return items.find(i=>i.catId===cat.id); }
// שיוך פריטים קיימים (מעריכה) לקטלוג לפי שם
items.forEach(i=>{ if(i.catId===undefined){ const c=CATALOG.find(c=>c.name===i.name); if(c) i.catId=c.id; } });

function bulletsHTML(desc){return '<ul class="q-bul">'+String(desc||'').split('\n').filter(x=>x.trim()).map(l=>'<li>'+esc(l)+'</li>').join('')+'</ul>';}
function descHTML(desc,fmt){return fmt==='bullets'?bulletsHTML(desc):'<div class="q-txt">'+esc(desc).replace(/\n/g,'<br>')+'</div>';}

function renderCatalog(){
  const box=document.getElementById('catalogList');
  box.innerHTML = CATALOG.map(c=>{
    const it=findItemByCat(c);
    const sel=!!it;
    return `<div class="q-item ${sel?'sel':''}">
      <div class="q-head">
        <input type="checkbox" ${sel?'checked':''} onchange="toggleCat(${c.id},this.checked)">
        <div class="nm">${esc(c.name)}</div>
        ${sel?`<input type="number" value="${it.price}" style="width:110px" onchange="setCat(${c.id},'price',this.value)">`:`<div class="muted" style="width:110px;text-align:left">${money(c.price)}</div>`}
      </div>
      ${sel?`<div class="q-edit">
        <textarea onchange="setCat(${c.id},'desc',this.value)">${esc(it.desc)}</textarea>
        <div class="row">
          <span class="muted" style="font-size:12px">תצוגה:</span>
          <select onchange="setCat(${c.id},'fmt',this.value)"><option value="bullets" ${it.fmt==='bullets'?'selected':''}>בולטים</option><option value="text" ${it.fmt==='text'?'selected':''}>טקסט</option></select>
          <span class="muted" style="font-size:12px">כמות:</span>
          <input type="number" min="1" value="${it.qty}" style="width:70px" onchange="setCat(${c.id},'qty',this.value)">
        </div>
      </div>`:descHTML(c.description,c.fmt)}
    </div>`;
  }).join('');
  renderFree();
  recompute();
}

function renderFree(){
  // הצגת פריטים חופשיים (בלי catId) מתחת לרשימה
  const frees=items.filter(i=>i.catId===undefined);
  let html=frees.map((f)=>{
    const idx=items.indexOf(f);
    return `<div class="q-item sel">
      <div class="q-head"><div class="nm">✏️ ${esc(f.name)}</div><input type="number" value="${f.price}" style="width:110px" onchange="setIdx(${idx},'price',this.value)"></div>
      <div class="q-edit"><textarea placeholder="תיאור (אופציונלי)" onchange="setIdx(${idx},'desc',this.value)">${esc(f.desc||'')}</textarea>
      <div class="row"><span class="muted" style="font-size:12px">כמות:</span><input type="number" min="1" value="${f.qty}" style="width:70px" onchange="setIdx(${idx},'qty',this.value)"><button type="button" class="btn btn-danger-ghost btn-sm" onclick="delIdx(${idx})">הסר</button></div></div>
    </div>`;
  }).join('');
  const box=document.getElementById('catalogList');
  box.insertAdjacentHTML('beforeend', html);
}

function toggleCat(catId,on){
  const c=CATALOG.find(x=>x.id===catId);
  if(on){ items.push({catId:catId,name:c.name,desc:c.description||'',fmt:c.fmt,price:+c.price,qty:1}); }
  else { items=items.filter(i=>i.catId!==catId); }
  renderCatalog();
}
function setCat(catId,f,v){ const it=items.find(i=>i.catId===catId); if(!it)return; it[f]= (f==='price')?(+v||0):(f==='qty'?Math.max(1,+v||1):v); recompute(); }
function setIdx(idx,f,v){ items[idx][f]=(f==='price')?(+v||0):(f==='qty'?Math.max(1,+v||1):v); recompute(); }
function delIdx(idx){ items.splice(idx,1); renderCatalog(); }
function addFree(){ const n=document.getElementById('free_name').value.trim(); const p=+document.getElementById('free_price').value||0; if(!n){alert('הזן שם לפריט');return;} items.push({name:n,desc:'',fmt:'text',price:p,qty:1}); document.getElementById('free_name').value='';document.getElementById('free_price').value=''; renderCatalog(); }

function recompute(){
  let sub=0; items.forEach(i=>sub+=(+i.price)*(Math.max(1,+i.qty||1)));
  const vat=sub*VAT;
  document.getElementById('t_sub').textContent=money(sub);
  document.getElementById('t_vat').textContent=money(vat);
  document.getElementById('t_total').textContent=money(sub+vat);
}

function pickClient(){
  const sel=document.getElementById('client_select');
  const opt=sel.options[sel.selectedIndex];
  document.getElementById('customer_id').value = sel.value||0;
  document.getElementById('client_name').value = opt ? (opt.dataset.name||'') : '';
  if(opt && opt.dataset.phone){ document.getElementById('phone').value = opt.dataset.phone; }
}

document.getElementById('qform').addEventListener('submit', function(){
  // ניקוי catId לפני שליחה (השרת לא צריך אותו) — אך נשאיר כדי לזהות בעריכה חוזרת
  document.getElementById('items_json').value = JSON.stringify(items.map(i=>({name:i.name,desc:i.desc,fmt:i.fmt,price:i.price,qty:i.qty})));
  if(!document.getElementById('client_name').value.trim()){ alert('יש לבחור לקוח'); return false; }
});

renderCatalog();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
