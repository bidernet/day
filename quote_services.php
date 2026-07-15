<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quote_schema($pdo);

$error = '';
$edit = ['id'=>0, 'name'=>'', 'descr'=>'', 'fmt'=>'bullets', 'price'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') {
        $pdo->prepare("DELETE FROM quote_services WHERE id=?")->execute([(int)$_POST['id']]);
        flash('השירות נמחק מהקטלוג.');
        header('Location: quote_services.php'); exit;
    }
    if ($act === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $descr = trim($_POST['descr'] ?? '');
        $fmt   = ($_POST['fmt'] ?? 'bullets') === 'text' ? 'text' : 'bullets';
        $price = (float)str_replace(',', '', $_POST['price'] ?? '0');
        if ($name === '') {
            $error = 'יש להזין שם שירות.';
            $edit = compact('id','name','descr','fmt','price');
        } else {
            if ($id > 0) {
                $pdo->prepare("UPDATE quote_services SET name=?,descr=?,fmt=?,price=? WHERE id=?")
                    ->execute([$name,$descr,$fmt,$price,$id]);
                flash('השירות עודכן.');
            } else {
                $pdo->prepare("INSERT INTO quote_services (name,descr,fmt,price) VALUES (?,?,?,?)")
                    ->execute([$name,$descr,$fmt,$price]);
                flash('השירות נוסף לקטלוג.');
            }
            header('Location: quote_services.php'); exit;
        }
    }
}

if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM quote_services WHERE id=?");
    $st->execute([(int)$_GET['edit']]);
    $found = $st->fetch();
    if ($found) $edit = $found;
}
$showForm = isset($_GET['edit']) || isset($_GET['new']) || $error;

$services = $pdo->query("SELECT * FROM quote_services ORDER BY created_at DESC")->fetchAll();

$active = 'quote_services';
$page_title = 'קטלוג שירותים';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="quotes.php">→ חזרה להצעות</a>
  <div class="spacer"></div>
  <?php if (!$showForm): ?><a class="btn btn-accent" href="quote_services.php?new=1">+ שירות חדש</a><?php endif; ?>
</div>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($showForm): ?>
<div class="card" style="border-color:var(--lime-d)">
  <div class="card-head"><h2><?= $edit['id']?'עריכת שירות':'שירות חדש' ?></h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <div class="form-grid">
        <div class="field"><label>שם השירות <span class="req">*</span></label><input type="text" name="name" value="<?= e($edit['name']) ?>" required autofocus></div>
        <div class="field"><label>מחיר (₪)</label><input type="text" name="price" inputmode="decimal" value="<?= e($edit['price']) ?>" placeholder="0.00"></div>
      </div>
      <div class="field full"><label>תיאור</label>
        <textarea name="descr" placeholder="בולטים: כל שורה = נקודה. אפשר גם טקסט חופשי."><?= e($edit['descr']) ?></textarea>
      </div>
      <div class="field"><label>תצוגת תיאור</label>
        <select name="fmt">
          <option value="bullets" <?= $edit['fmt']==='bullets'?'selected':'' ?>>בולטים (כל שורה = נקודה)</option>
          <option value="text" <?= $edit['fmt']==='text'?'selected':'' ?>>טקסט חופשי</option>
        </select>
      </div>
      <div class="form-actions"><button class="btn" type="submit">שמור</button><a class="btn btn-ghost" href="quote_services.php">ביטול</a></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2>השירותים שלי (<?= count($services) ?>)</h2></div>
  <div class="card-body">
    <?php if (!$services): ?>
      <div class="muted">אין עדיין שירותים. לחץ "שירות חדש" כדי להתחיל.</div>
    <?php else: foreach ($services as $s): ?>
      <div class="bank-row">
        <div class="bank-info">
          <strong><?= e($s['name']) ?></strong> <span class="badge badge-neutral"><?= $s['fmt']==='bullets'?'בולטים':'טקסט' ?></span>
          <div class="muted" style="margin-top:6px"><?= quote_desc_html($s['descr'], $s['fmt']) ?></div>
        </div>
        <div class="bank-actions">
          <span class="muted"><?= money_short($s['price']) ?></span>
          <a class="btn btn-ghost btn-sm" href="quote_services.php?edit=<?= (int)$s['id'] ?>">עריכה</a>
          <form method="post" style="display:inline" onsubmit="return confirm('למחוק את השירות?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-danger-ghost btn-sm" type="submit">מחק</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
