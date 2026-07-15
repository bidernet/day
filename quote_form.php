<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quote_schema($pdo);
ensure_settings_schema($pdo);

$id = (int)($_GET['id'] ?? 0);

// ---------- שמירה ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id          = (int)($_POST['id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $heading     = trim($_POST['heading'] ?? '');
    $quote_date  = $_POST['quote_date'] ?: date('Y-m-d');
    $validity    = max(0, (int)($_POST['validity'] ?? 10));
    $doc_no      = (int)($_POST['doc_no'] ?? 0);
    $mode        = ($_POST['mode'] ?? 'quote') === 'order' ? 'order' : 'quote';
    $items       = json_decode($_POST['items_json'] ?? '[]', true);
    if (!is_array($items)) $items = [];

    if ($id > 0) {
        $pdo->prepare("UPDATE quotes SET doc_no=?,client_name=?,phone=?,heading=?,quote_date=?,validity=?,mode=? WHERE id=?")
            ->execute([$doc_no,$client_name,$phone,$heading,$quote_date,$validity,$mode,$id]);
    } else {
        if (!$doc_no) {
            $doc_no = (int)$pdo->query("SELECT COALESCE(MAX(doc_no),0)+1 FROM quotes")->fetchColumn();
        }
        $token = quote_make_token();
        $pdo->prepare("INSERT INTO quotes (doc_no,client_name,phone,heading,quote_date,validity,mode,status,public_token) VALUES (?,?,?,?,?,?,?, 'draft', ?)")
            ->execute([$doc_no,$client_name,$phone,$heading,$quote_date,$validity,$mode,$token]);
        $id = (int)$pdo->lastInsertId();
    }
    // rewrite items
    $pdo->prepare("DELETE FROM quote_items WHERE quote_id=?")->execute([$id]);
    $ins = $pdo->prepare("INSERT INTO quote_items (quote_id,name,descr,fmt,price,qty,sort_order) VALUES (?,?,?,?,?,?,?)");
    $i = 0;
    foreach ($items as $it) {
        $ins->execute([
            $id,
            trim($it['name'] ?? ''),
            (string)($it['descr'] ?? ''),
            ($it['fmt'] ?? 'bullets') === 'text' ? 'text' : 'bullets',
            (float)($it['price'] ?? 0),
            max(1, (int)($it['qty'] ?? 1)),
            $i++
        ]);
    }
    flash('ההצעה נשמרה.');
    header('Location: quote_form.php?id=' . $id); exit;
}

// ---------- טעינה ----------
$q = ['id'=>0,'doc_no'=>'','client_name'=>'','phone'=>'','heading'=>'פרסום סושיאל מדיה',
      'quote_date'=>date('Y-m-d'),'validity'=>10,'mode'=>'quote','status'=>'draft','public_token'=>''];
$items = [];
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM quotes WHERE id=?"); $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) { http_response_code(404); die('ההצעה לא נמצאה.'); }
    $q = $found;
    $its = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id=? ORDER BY sort_order"); $its->execute([$id]);
    foreach ($its->fetchAll() as $r) {
        $items[] = ['catId'=>null,'name'=>$r['name'],'descr'=>$r['descr'],'fmt'=>$r['fmt'],'price'=>(float)$r['price'],'qty'=>(int)$r['qty']];
    }
}
if (!$q['doc_no']) $q['doc_no'] = (int)$pdo->query("SELECT COALESCE(MAX(doc_no),0)+1 FROM quotes")->fetchColumn();

$catalog   = $pdo->query("SELECT id,name,descr,fmt,price FROM quote_services ORDER BY created_at DESC")->fetchAll();
$customers = $pdo->query("SELECT name,phone FROM customers ORDER BY name")->fetchAll();
$signLink  = $q['public_token'] ? rtrim(APP_URL,'/') . '/sign.php?t=' . $q['public_token'] : '';

$active = 'quotes';
$page_title = 'הצעת מחיר';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="quotes.php">→ חזרה לרשימה</a>
  <div class="spacer"></div>
  <?php if ($id): ?>
    <a class="btn btn-ghost btn-sm" href="quote_doc.php?id=<?= $id ?>" target="_blank">👁 צפה כלקוח</a>
    <?php if (greenapi_enabled() && $q['phone']): ?>
      <form method="post" action="quote_send.php" style="display:inline" onsubmit="return confirm('לשלוח בוואטסאפ אל <?= e($q['phone']) ?>?')">
        <?= csrf_field() ?><input type="hidden" name="quote_id" value="<?= $id ?>">
        <button class="btn btn-ok btn-sm" type="submit">💬 שלח בוואטסאפ</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($id && !greenapi_enabled()): ?>
  <div class="alert-box" style="background:#fbf2e0;border:1px solid #e7d3a0;color:#7a5a12;padding:12px 16px;border-radius:10px;margin-bottom:14px">כדי לשלוח בוואטסאפ יש לחבר את GREEN API ב<a href="settings.php">הגדרות</a>.</div>
<?php endif; ?>

<form method="post" id="qform">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
  <input type="hidden" name="mode" id="f_mode" value="<?= e($q['mode']) ?>">
  <input type="hidden" name="items_json" id="items_json">

  <div class="card"><div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
      <h2 style="margin:0" id="modeTitle"></h2>
      <button type="button" class="btn btn-dark btn-sm" id="modeBtn" onclick="toggleMode()"></button>
    </div>
    <div class="form-grid" style="margin-top:12px">
      <div class="field"><label>בחר לקוח מהמערכת</label>
        <select id="custSelect" onchange="pickCust(this.value)">
          <option value="">— בחר / הקלד ידנית —</option>
          <?php foreach ($customers as $c): ?><option value="<?= e($c['phone']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>שם הלקוח</label><input type="text" name="client_name" id="f_client" value="<?= e($q['client_name']) ?>"></div>
      <div class="field"><label>טלפון לקוח (בלי 972)</label><input type="text" name="phone" id="f_phone" value="<?= e($q['phone']) ?>" placeholder="0501234567"><div class="hint">לשליחת ההצעה/הזמנה בוואטסאפ</div></div>
      <div class="field"><label>כותרת עמוד המחירים</label><input type="text" name="heading" value="<?= e($q['heading']) ?>" placeholder="למשל: פרסום סושיאל מדיה"></div>
      <div class="field"><label>תאריך</label><input type="date" name="quote_date" value="<?= e($q['quote_date']) ?>"></div>
      <div class="field"><label>מספר מסמך</label><input type="number" name="doc_no" value="<?= (int)$q['doc_no'] ?>"></div>
      <div class="field"><label>תוקף (ימי עסקים)</label><input type="number" name="validity" value="<?= (int)$q['validity'] ?>"></div>
    </div>
  </div></div>

  <div class="card"><div class="card-head"><h2>סמן שירותים מהקטלוג ✓</h2></div><div class="card-body">
    <div id="svcList"></div>
    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      <input id="fn" placeholder="פריט חופשי (תיאור קצר)" style="flex:1;min-width:160px">
      <input id="fp" type="number" placeholder="מחיר" style="max-width:120px">
      <button type="button" class="btn btn-ghost btn-sm" onclick="addFree()">+ שורה חופשית</button>
    </div>
    <table class="tot" style="width:100%;margin-top:14px">
      <tr><td>סכום ביניים</td><td class="t-left num" id="t_sub" style="font-weight:700"></td></tr>
      <tr><td class="muted">מע״מ 17%</td><td class="t-left num muted" id="t_vat"></td></tr>
      <tr><td style="font-size:19px;border-top:2px solid #14180b;padding-top:8px">סה״כ</td><td class="t-left num" id="t_total" style="font-size:19px;font-weight:800;border-top:2px solid #14180b;padding-top:8px"></td></tr>
    </table>
  </div></div>

  <div class="card" style="text-align:center"><button class="btn" type="submit" onclick="return prepSubmit()">💾 שמור הצעה</button></div>
</form>

<script>
const CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>.map(c=>({id:+c.id,name:c.name,descr:c.descr||'',fmt:c.fmt||'bullets',price:+c.price}));
const CUSTMAP = {};
<?php foreach ($customers as $c): ?>CUSTMAP[<?= json_encode($c['name'], JSON_UNESCAPED_UNICODE) ?>]=<?= json_encode($c['phone'], JSON_UNESCAPED_UNICODE) ?>;<?php endforeach; ?>
let items = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;
let mode  = <?= json_encode($q['mode']) ?>;
const VAT = 0.17;

function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(n){return '₪'+Number(n||0).toLocaleString('he-IL',{maximumFractionDigits:0});}
function isSel(id){return items.some(i=>i.catId===id);}
function sub(){return items.reduce((s,i)=>s+(+i.price)*(i.qty||1),0);}

function pickCust(phone){const sel=document.getElementById('custSelect');const name=sel.options[sel.selectedIndex].text;if(sel.value===''){return;}document.getElementById('f_client').value=name;document.getElementById('f_phone').value=phone||'';}

function render(){
  document.getElementById('modeTitle').textContent = mode==='order'?'הזמנת עבודה':'הצעת מחיר';
  document.getElementById('modeBtn').textContent = mode==='order'?'← חזור להצעה':'↔ הפוך להזמנת עבודה';
  document.getElementById('f_mode').value = mode;
  // services
  const rows = CATALOG.map(c=>{
    const sel=isSel(c.id); const line=items.find(i=>i.catId===c.id);
    return `<div class="bank-row" style="align-items:flex-start;${sel?'background:#fbfee9;border-radius:10px;padding:12px':''}">
      <div class="bank-info"><div style="display:flex;gap:10px;align-items:flex-start">
        <input type="checkbox" style="width:18px;height:18px;margin-top:3px" ${sel?'checked':''} onchange="toggle(${c.id},this.checked)">
        <div style="flex:1"><strong>${esc(c.name)}</strong> <span class="badge badge-neutral">${c.fmt==='bullets'?'בולטים':'טקסט'}</span>
          ${sel?`<textarea style="width:100%;min-height:70px;margin-top:8px" onchange="setDesc(${c.id},this.value)">${esc(line.descr)}</textarea>`:`<div class="muted" style="margin-top:6px;white-space:pre-wrap">${esc(c.descr)}</div>`}
        </div></div>
      </div>
      <div class="bank-actions">${sel?`<input type="number" min="1" value="${line.qty}" style="width:60px" onchange="setQty(${c.id},this.value)"><input type="number" value="${line.price}" style="width:100px" onchange="setPrice(${c.id},this.value)">`:`<span class="muted">${money(c.price)}</span>`}</div>
    </div>`;}).join('');
  const free = items.filter(i=>i.catId==null).map((f,idx)=>{const realIdx=items.indexOf(f);return `<div class="bank-row" style="background:#fbfee9;border-radius:10px;padding:12px">
      <div class="bank-info"><strong>✏️ ${esc(f.name)}</strong>
        <textarea style="width:100%;min-height:50px;margin-top:6px" placeholder="תיאור (אופציונלי)" onchange="setFieldDesc(${realIdx},this.value)">${esc(f.descr||'')}</textarea></div>
      <div class="bank-actions"><input type="number" min="1" value="${f.qty}" style="width:60px" onchange="setField(${realIdx},'qty',this.value)"><input type="number" value="${f.price}" style="width:100px" onchange="setField(${realIdx},'price',this.value)"><button type="button" class="btn btn-danger-ghost btn-sm" onclick="delItem(${realIdx})">✕</button></div>
    </div>`;}).join('');
  document.getElementById('svcList').innerHTML = rows + free;
  const s=sub(); document.getElementById('t_sub').textContent=money(s);
  document.getElementById('t_vat').textContent=money(s*VAT);
  document.getElementById('t_total').textContent=money(s*(1+VAT));
}
function toggle(id,on){if(on){const c=CATALOG.find(x=>x.id===id);items.push({catId:id,name:c.name,descr:c.descr,fmt:c.fmt,price:c.price,qty:1});}else{items=items.filter(i=>i.catId!==id);}render();}
function setPrice(id,v){items.find(i=>i.catId===id).price=+v||0;render();}
function setQty(id,v){items.find(i=>i.catId===id).qty=Math.max(1,+v||1);render();}
function setDesc(id,v){items.find(i=>i.catId===id).descr=v;}
function setField(idx,k,v){items[idx][k]= k==='qty'?Math.max(1,+v||1):(k==='price'?(+v||0):v);render();}
function setFieldDesc(idx,v){items[idx].descr=v;}
function delItem(idx){items.splice(idx,1);render();}
function addFree(){const n=document.getElementById('fn').value.trim();const p=+document.getElementById('fp').value||0;if(!n){alert('הזן תיאור לפריט');return;}items.push({catId:null,name:n,descr:'',fmt:'text',price:p,qty:1});document.getElementById('fn').value='';document.getElementById('fp').value='';render();}
function toggleMode(){mode=mode==='order'?'quote':'order';render();}
function prepSubmit(){document.getElementById('items_json').value=JSON.stringify(items);return true;}
render();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
