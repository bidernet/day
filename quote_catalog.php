<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quotes_schema($pdo);

$error = '';
$edit = ['id'=>0, 'name'=>'', 'description'=>'', 'fmt'=>'bullets', 'price'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $fmt   = ($_POST['fmt'] ?? 'bullets') === 'text' ? 'text' : 'bullets';
        $price = (float)str_replace(',', '', $_POST['price'] ?? '0');
        if ($name === '') {
            $error = 'יש להזין שם שירות.';
            $edit = compact('id','name','description','fmt','price') + ['description'=>$desc];
        } else {
            if ($id > 0) {
                $pdo->prepare("UPDATE service_catalog SET name=?, description=?, fmt=?, price=? WHERE id=?")
                    ->execute([$name,$desc,$fmt,$price,$id]);
                flash('השירות עודכן.');
            } else {
                $pdo->prepare("INSERT INTO service_catalog (name,description,fmt,price) VALUES (?,?,?,?)")
                    ->execute([$name,$desc,$fmt,$price]);
                flash('השירות נוסף לקטלוג.');
            }
            header('Location: quote_catalog.php'); exit;
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM service_catalog WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
        flash('השירות נמחק.');
        header('Location: quote_catalog.php'); exit;
    }
}

if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM service_catalog WHERE id=?");
    $st->execute([(int)$_GET['edit']]);
    $found = $st->fetch();
    if ($found) $edit = $found;
}
$editing = ($edit['id'] > 0) || isset($_GET['new']) || $error;

$services = $pdo->query("SELECT * FROM service_catalog ORDER BY sort_order, name")->fetchAll();

$active = 'quotes';
$page_title = 'קטלוג שירותים';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="quotes.php">→ חזרה להצעות</a>
  <div class="spacer"></div>
  <?php if (!$editing): ?><a class="btn btn-accent" href="quote_catalog.php?new=1">+ שירות חדש</a><?php endif; ?>
</div>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($editing): ?>
<div class="card">
  <div class="card-head"><h2><?= $edit['id']>0 ? 'עריכת שירות' : 'שירות חדש' ?></h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <div class="form-grid">
        <div class="field"><label>שם השירות <span class="req">*</span></label><input type="text" name="name" value="<?= e($edit['name']) ?>" required autofocus></div>
        <div class="field"><label>מחיר (₪)</label><input type="text" name="price" value="<?= e($edit['price']) ?>" inputmode="decimal" placeholder="0.00"></div>
        <div class="field full"><label>תיאור</label><textarea name="description" rows="6" placeholder="בבולטים: כל שורה = נקודה. אפשר גם טקסט חופשי."><?= e($edit['description']) ?></textarea></div>
        <div class="field"><label>תצוגת התיאור</label>
          <select name="fmt">
            <option value="bullets" <?= $edit['fmt']==='bullets'?'selected':'' ?>>בולטים (כל שורה = נקודה)</option>
            <option value="text" <?= $edit['fmt']==='text'?'selected':'' ?>>טקסט חופשי</option>
          </select>
        </div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">שמור</button><a class="btn btn-ghost" href="quote_catalog.php">ביטול</a></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2>קטלוג שירותים (<?= count($services) ?>)</h2></div>
  <div class="card-body">
    <?php if (!$services): ?>
      <div class="muted">אין עדיין שירותים. הוסף שירות ראשון.</div>
    <?php else: foreach ($services as $s): ?>
      <div class="bank-row">
        <div class="bank-info">
          <strong><?= e($s['name']) ?></strong>
          <span class="badge badge-neutral"><?= $s['fmt']==='bullets'?'בולטים':'טקסט' ?></span>
          <?= quote_desc_html($s['description'], $s['fmt']) ?>
        </div>
        <div class="bank-actions">
          <span class="muted"><?= money_short($s['price']) ?></span>
          <a class="btn btn-ghost btn-sm" href="quote_catalog.php?edit=<?= (int)$s['id'] ?>">עריכה</a>
          <form method="post" style="display:inline" onsubmit="return confirm('למחוק את <?= e($s['name']) ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-danger-ghost btn-sm" type="submit">מחק</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<style>
.q-bul{margin:6px 0 0;padding-inline-start:18px;font-size:13px;color:#4a524f;line-height:1.6}
.q-txt{font-size:13px;color:#4a524f;line-height:1.6;margin-top:6px}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
