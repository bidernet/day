<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$error = '';

// טעינת לקוח קיים לעריכה
$c = [
    'full_name'=>'', 'phone'=>'', 'email'=>'', 'id_number'=>'',
    'address'=>'', 'join_date'=>date('Y-m-d'), 'status'=>'active', 'notes'=>''
];
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { http_response_code(404); die('הלקוח לא נמצא.'); }
    $c = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $c['full_name'] = trim($_POST['full_name'] ?? '');
    $c['phone']     = trim($_POST['phone'] ?? '');
    $c['email']     = trim($_POST['email'] ?? '');
    $c['id_number'] = trim($_POST['id_number'] ?? '');
    $c['address']   = trim($_POST['address'] ?? '');
    $c['join_date'] = $_POST['join_date'] ?: null;
    $c['status']    = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $c['notes']     = trim($_POST['notes'] ?? '');

    if ($c['full_name'] === '') {
        $error = 'יש להזין שם לקוח.';
    } else {
        if ($isEdit) {
            $stmt = $pdo->prepare("UPDATE customers SET full_name=?, phone=?, email=?, id_number=?, address=?, join_date=?, status=?, notes=? WHERE id=?");
            $stmt->execute([$c['full_name'],$c['phone'],$c['email'],$c['id_number'],$c['address'],$c['join_date'],$c['status'],$c['notes'],$id]);
            flash('פרטי הלקוח עודכנו.');
            header('Location: customer_view.php?id=' . $id);
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO customers (full_name,phone,email,id_number,address,join_date,status,notes) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$c['full_name'],$c['phone'],$c['email'],$c['id_number'],$c['address'],$c['join_date'],$c['status'],$c['notes']]);
            $newId = $pdo->lastInsertId();
            flash('הלקוח נוסף בהצלחה.');
            header('Location: customer_view.php?id=' . $newId);
            exit;
        }
    }
}

$active = 'customers';
$page_title = $isEdit ? 'עריכת לקוח' : 'לקוח חדש';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="<?= $isEdit ? 'customer_view.php?id='.$id : 'customers.php' ?>">→ חזרה</a>
</div>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <div class="card-head"><h2><?= $isEdit ? 'עריכת כרטיס לקוח' : 'פתיחת כרטיס לקוח' ?></h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field full">
          <label>שם מלא <span class="req">*</span></label>
          <input type="text" name="full_name" value="<?= e($c['full_name']) ?>" required autofocus>
        </div>
        <div class="field">
          <label>טלפון</label>
          <input type="text" name="phone" value="<?= e($c['phone']) ?>" inputmode="tel">
        </div>
        <div class="field">
          <label>דוא״ל</label>
          <input type="email" name="email" value="<?= e($c['email']) ?>">
        </div>
        <div class="field">
          <label>תעודת זהות / ח.פ</label>
          <input type="text" name="id_number" value="<?= e($c['id_number']) ?>" inputmode="numeric">
        </div>
        <div class="field">
          <label>תאריך הצטרפות</label>
          <input type="date" name="join_date" value="<?= e($c['join_date']) ?>">
        </div>
        <div class="field full">
          <label>כתובת</label>
          <input type="text" name="address" value="<?= e($c['address']) ?>">
        </div>
        <div class="field">
          <label>סטטוס</label>
          <select name="status">
            <option value="active"   <?= $c['status']==='active'?'selected':'' ?>>פעיל</option>
            <option value="inactive" <?= $c['status']==='inactive'?'selected':'' ?>>לא פעיל</option>
          </select>
        </div>
        <div class="field full">
          <label>הערות</label>
          <textarea name="notes"><?= e($c['notes']) ?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-accent" type="submit"><?= $isEdit ? 'שמירת שינויים' : 'פתח כרטיס לקוח' ?></button>
        <a class="btn btn-ghost" href="<?= $isEdit ? 'customer_view.php?id='.$id : 'customers.php' ?>">ביטול</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
