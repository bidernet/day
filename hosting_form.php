<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_hosting_schema($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';

$row = [
    'id'=>0, 'domain'=>'', 'customer_name'=>'', 'phone'=>'', 'annual_price'=>'',
    'join_date'=>'', 'renewal_date'=>'', 'status'=>'active', 'notes'=>''
];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM hosting WHERE id=?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { http_response_code(404); die('האתר לא נמצא.'); }
    $row = $found;
}

// מחיקה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $pdo->prepare("DELETE FROM hosting WHERE id=?")->execute([$id]);
    flash('רשומת האחסון נמחקה.');
    header('Location: hosting.php'); exit;
}

// שמירה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_check();
    $row['domain']        = trim($_POST['domain'] ?? '');
    $row['customer_name'] = trim($_POST['customer_name'] ?? '');
    $row['phone']         = trim($_POST['phone'] ?? '');
    $row['annual_price']  = (float)str_replace(',', '', $_POST['annual_price'] ?? '0');
    $row['join_date']     = $_POST['join_date'] ?: null;
    $row['renewal_date']  = $_POST['renewal_date'] ?: null;
    $row['status']        = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $row['notes']         = trim($_POST['notes'] ?? '');

    if ($row['domain'] === '') {
        $error = 'יש להזין כתובת דומיין.';
    } else {
        if ($isEdit) {
            $pdo->prepare("UPDATE hosting SET domain=?, customer_name=?, phone=?, annual_price=?, join_date=?, renewal_date=?, status=?, notes=? WHERE id=?")
                ->execute([$row['domain'],$row['customer_name'],$row['phone'],$row['annual_price'],$row['join_date'],$row['renewal_date'],$row['status'],$row['notes'],$id]);
            flash('רשומת האחסון עודכנה.');
        } else {
            $pdo->prepare("INSERT INTO hosting (domain, customer_name, phone, annual_price, join_date, renewal_date, status, notes) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$row['domain'],$row['customer_name'],$row['phone'],$row['annual_price'],$row['join_date'],$row['renewal_date'],$row['status'],$row['notes']]);
            flash('האתר נוסף לאחסון.');
        }
        header('Location: hosting.php'); exit;
    }
}

$active = 'hosting';
$page_title = $isEdit ? 'עריכת אתר' : 'אתר חדש';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="hosting.php">→ חזרה לרשימת האתרים</a>
</div>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($isEdit):
  $lvl = $row['status']==='inactive' ? 'inactive' : hosting_alert_level($row['renewal_date']);
?>
<div class="cust-header">
  <div class="avatar" style="border-radius:14px"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M20 13H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-6c0-.55-.45-1-1-1zM7 19c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM20 3H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1zM7 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg></div>
  <div class="info">
    <h1><?= e($row['domain']) ?></h1>
    <div class="meta">
      <?php if ($row['customer_name']): ?><span>👤 <?= e($row['customer_name']) ?></span><?php endif; ?>
      <?php if ($row['phone']): ?><span>☎ <?= e($row['phone']) ?></span><?php endif; ?>
      <?php if ($row['renewal_date']): ?><span>חידוש <?= fmt_date($row['renewal_date']) ?></span><?php endif; ?>
      <?php if ($lvl==='overdue'): ?><span class="badge badge-overdue"><span class="pip"></span><?= e(hosting_alert_text($row['renewal_date'])) ?></span>
      <?php elseif ($lvl==='soon'): ?><span class="badge badge-soon"><span class="pip"></span><?= e(hosting_alert_text($row['renewal_date'])) ?></span><?php endif; ?>
      <?php if (!empty($row['last_reminder_at'])): ?><span class="muted">תזכורת אחרונה נשלחה <?= fmt_date($row['last_reminder_at']) ?></span><?php endif; ?>
    </div>
  </div>
  <?php if ($row['phone']): ?>
  <div class="actions">
    <?= reminder_button_html($row, 'hosting_form.php?id=' . $id) ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h2><?= $isEdit ? 'פרטי האתר' : 'הוספת אתר לאחסון' ?></h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="form-grid">
        <div class="field"><label>כתובת דומיין <span class="req">*</span></label><input type="text" name="domain" value="<?= e($row['domain']) ?>" placeholder="example.co.il" required autofocus></div>
        <div class="field"><label>שם לקוח</label><input type="text" name="customer_name" value="<?= e($row['customer_name']) ?>"></div>
        <div class="field"><label>טלפון</label><input type="text" name="phone" value="<?= e($row['phone']) ?>" inputmode="tel" placeholder="050-0000000"><div class="hint">לשליחת תזכורת חידוש בוואטסאפ</div></div>
        <div class="field"><label>עלות אחסון לשנה (₪)</label><input type="text" name="annual_price" value="<?= e($row['annual_price']) ?>" inputmode="decimal" placeholder="0.00"></div>
        <div class="field"><label>תאריך הצטרפות לשרת</label><input type="date" name="join_date" value="<?= e($row['join_date']) ?>"></div>
        <div class="field"><label>תאריך חידוש</label><input type="date" name="renewal_date" value="<?= e($row['renewal_date']) ?>"><div class="hint">לפי תאריך זה תופיע התראת החידוש</div></div>
        <div class="field"><label>סטטוס</label>
          <select name="status">
            <option value="active" <?= $row['status']==='active'?'selected':'' ?>>פעיל</option>
            <option value="inactive" <?= $row['status']==='inactive'?'selected':'' ?>>לא פעיל</option>
          </select>
        </div>
        <div class="field full"><label>הערות</label><textarea name="notes"><?= e($row['notes']) ?></textarea></div>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'שמירת שינויים' : 'הוסף אתר' ?></button>
        <a class="btn btn-ghost" href="hosting.php">ביטול</a>
      </div>
    </form>
  </div>
</div>

<?php if ($isEdit): ?>
<div class="card">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <div><strong>מחיקת האתר</strong><div class="muted" style="font-size:13px">הסרת הרשומה מהמערכת. לא ניתן לשחזר.</div></div>
    <form method="post" onsubmit="return confirm('למחוק את <?= e($row['domain']) ?>? פעולה זו אינה הפיכה.')">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <button class="btn btn-danger-ghost" type="submit">מחק אתר</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
