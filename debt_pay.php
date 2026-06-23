<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("
    SELECT d.*, c.full_name,
           IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.debt_id=d.id),0) AS paid
    FROM debts d JOIN customers c ON c.id=d.customer_id
    WHERE d.id=?
");
$stmt->execute([$id]);
$debt = $stmt->fetch();
if (!$debt) { http_response_code(404); die('החוב לא נמצא.'); }

$remaining = (float)$debt['amount'] - (float)$debt['paid'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? '0');
    $date   = $_POST['payment_date'] ?: date('Y-m-d');
    $method = trim($_POST['method'] ?? '');
    $notes  = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        $error = 'יש להזין סכום תשלום גדול מאפס.';
    } else {
        $pdo->beginTransaction();
        $ins = $pdo->prepare("INSERT INTO payments (customer_id, debt_id, amount, payment_date, method, notes) VALUES (?,?,?,?,?,?)");
        $ins->execute([$debt['customer_id'], $id, $amount, $date, $method, $notes]);

        // אם החוב כוסה במלואו — סמן כשולם
        $newPaid = (float)$debt['paid'] + $amount;
        if ($newPaid >= (float)$debt['amount']) {
            $pdo->prepare("UPDATE debts SET status='paid' WHERE id=?")->execute([$id]);
        }
        $pdo->commit();

        flash('התשלום נרשם.');
        header('Location: customer_view.php?id=' . $debt['customer_id']);
        exit;
    }
}

$active = 'customers';
$page_title = 'רישום תשלום';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="customer_view.php?id=<?= (int)$debt['customer_id'] ?>">→ חזרה לכרטיס</a>
</div>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width:560px">
  <div class="card-head"><h2>רישום תשלום</h2></div>
  <div class="card-body">
    <div class="balance-strip" style="grid-template-columns:1fr 1fr;margin-bottom:20px">
      <div class="cell">
        <div class="l">לקוח</div>
        <div class="v" style="font-size:18px"><?= e($debt['full_name']) ?></div>
      </div>
      <div class="cell">
        <div class="l">יתרה לתשלום</div>
        <div class="v num danger"><?= money_short($remaining) ?></div>
      </div>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="field">
          <label>סכום התשלום (₪) <span class="req">*</span></label>
          <input type="text" name="amount" inputmode="decimal" value="<?= e(number_format(max($remaining,0), 2, '.', '')) ?>" required autofocus>
          <div class="hint">ניתן לרשום גם תשלום חלקי</div>
        </div>
        <div class="field">
          <label>תאריך התשלום</label>
          <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="field">
          <label>אמצעי תשלום</label>
          <select name="method">
            <option value="">— בחר —</option>
            <option>מזומן</option>
            <option>העברה בנקאית</option>
            <option>אשראי</option>
            <option>צ׳ק</option>
            <option>ביט</option>
            <option>אחר</option>
          </select>
        </div>
        <div class="field">
          <label>הערה</label>
          <input type="text" name="notes">
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-ok" type="submit">רשום תשלום</button>
        <a class="btn btn-ghost" href="customer_view.php?id=<?= (int)$debt['customer_id'] ?>">ביטול</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
