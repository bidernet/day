<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
retainers_bootstrap($pdo); // יצירה אוטומטית של חיובי ריטיינר חודשיים

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$stmt->execute([$id]);
$cust = $stmt->fetch();
if (!$cust) { http_response_code(404); die('הלקוח לא נמצא.'); }

$error = '';

// הוספת חוב חדש
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_debt') {
    csrf_check();
    $desc   = trim($_POST['description'] ?? '');
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? '0');
    $due    = $_POST['due_date'] ?: null;
    if ($amount <= 0) {
        $error = 'יש להזין סכום חוב גדול מאפס.';
    } else {
        $ins = $pdo->prepare("INSERT INTO debts (customer_id, description, amount, due_date) VALUES (?,?,?,?)");
        $ins->execute([$id, $desc, $amount, $due]);
        flash('החוב נוסף לכרטיס.');
        header('Location: customer_view.php?id=' . $id);
        exit;
    }
}

// ---- ריטיינר חודשי: הוספה ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_retainer') {
    csrf_check();
    $rdesc   = trim($_POST['r_description'] ?? '');
    $ramount = (float)str_replace(',', '', $_POST['r_amount'] ?? '0');
    $rday    = max(1, min(28, (int)($_POST['r_billing_day'] ?? 1)));
    $rstart  = $_POST['r_start_date'] ?: date('Y-m-d');
    if ($ramount <= 0) {
        $error = 'יש להזין סכום ריטיינר גדול מאפס.';
    } else {
        $pdo->prepare("INSERT INTO retainers (customer_id, description, amount, billing_day, start_date) VALUES (?,?,?,?,?)")
            ->execute([$id, ($rdesc ?: 'ריטיינר חודשי'), $ramount, $rday, $rstart]);
        flash('הריטיינר נוסף. החיובים החודשיים ייווצרו אוטומטית.');
        header('Location: customer_view.php?id=' . $id);
        exit;
    }
}

// ---- ריטיינר: השהיה / הפעלה ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_retainer') {
    csrf_check();
    $rid = (int)($_POST['retainer_id'] ?? 0);
    $pdo->prepare("UPDATE retainers SET status = IF(status='active','paused','active') WHERE id=? AND customer_id=?")
        ->execute([$rid, $id]);
    flash('סטטוס הריטיינר עודכן.');
    header('Location: customer_view.php?id=' . $id);
    exit;
}

// ---- ריטיינר: מחיקה (החיובים הקיימים נשמרים) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_retainer') {
    csrf_check();
    $rid = (int)($_POST['retainer_id'] ?? 0);
    $pdo->prepare("DELETE FROM retainers WHERE id=? AND customer_id=?")->execute([$rid, $id]);
    flash('הריטיינר נמחק. החיובים שכבר נוצרו נשמרו בכרטיס.');
    header('Location: customer_view.php?id=' . $id);
    exit;
}

// שליפת חובות עם יתרה
$debts = $pdo->prepare("
    SELECT d.*, IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.debt_id=d.id),0) AS paid
    FROM debts d WHERE d.customer_id=?
    ORDER BY (d.status='paid'), (d.due_date IS NULL), d.due_date ASC, d.id DESC
");
$debts->execute([$id]);
$debts = $debts->fetchAll();

// תשלומים אחרונים
$payments = $pdo->prepare("
    SELECT p.*, d.description AS debt_desc
    FROM payments p LEFT JOIN debts d ON d.id=p.debt_id
    WHERE p.customer_id=? ORDER BY p.payment_date DESC, p.id DESC
");
$payments->execute([$id]);
$payments = $payments->fetchAll();

// ריטיינרים של הלקוח
$retainers = $pdo->prepare("SELECT * FROM retainers WHERE customer_id=? ORDER BY created_at DESC");
$retainers->execute([$id]);
$retainers = $retainers->fetchAll();
$hasActiveRetainer = false;
foreach ($retainers as $rr) { if ($rr['status'] === 'active') { $hasActiveRetainer = true; break; } }

// סיכומים
$balance = 0; $total_amount = 0; $total_paid = 0;
foreach ($debts as $d) {
    $rem = $d['amount'] - $d['paid'];
    if ($d['status'] === 'open' && $rem > 0) $balance += $rem;
    $total_amount += $d['amount'];
    $total_paid   += $d['paid'];
}

$initial = mb_substr(trim($cust['full_name']), 0, 1, 'UTF-8');

$active = 'customers';
$page_title = $cust['full_name'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <a class="btn btn-ghost btn-sm" href="customers.php">→ חזרה לרשימה</a>
</div>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="cust-header">
  <div class="avatar"><?= e($initial) ?></div>
  <div class="info">
    <h1><?= e($cust['full_name']) ?>
      <?php if ($cust['status']==='active'): ?>
        <span class="badge badge-ok" style="vertical-align:middle"><span class="pip"></span>פעיל</span>
      <?php else: ?>
        <span class="badge badge-neutral" style="vertical-align:middle"><span class="pip"></span>לא פעיל</span>
      <?php endif; ?>
    </h1>
    <div class="meta">
      <?php if ($cust['phone']): ?><span>☎ <?= e($cust['phone']) ?></span><?php endif; ?>
      <?php if ($cust['email']): ?><span>✉ <?= e($cust['email']) ?></span><?php endif; ?>
      <?php if ($cust['id_number']): ?><span>ת.ז <?= e($cust['id_number']) ?></span><?php endif; ?>
      <?php if ($cust['join_date']): ?><span>הצטרף <?= fmt_date($cust['join_date']) ?></span><?php endif; ?>
      <?php if ($hasActiveRetainer): ?><span class="badge badge-retainer"><span class="pip"></span>ריטיינר חודשי</span><?php endif; ?>
    </div>
    <?php if ($cust['address']): ?><div class="meta"><span>⌖ <?= e($cust['address']) ?></span></div><?php endif; ?>
    <?php if ($cust['notes']): ?><div class="meta"><span class="muted"><?= e($cust['notes']) ?></span></div><?php endif; ?>
  </div>
  <div class="actions">
    <a class="btn btn-ghost btn-sm" href="customer_form.php?id=<?= $id ?>">עריכה</a>
    <a class="btn btn-danger-ghost btn-sm" href="customer_delete.php?id=<?= $id ?>" onclick="return confirm('למחוק את הלקוח וכל החובות שלו? פעולה זו אינה הפיכה.')">מחיקה</a>
  </div>
</div>

<div class="balance-strip">
  <div class="cell">
    <div class="l">יתרת חוב פתוחה</div>
    <div class="v num <?= $balance>0?'danger':'ok' ?>"><?= money_short($balance) ?></div>
  </div>
  <div class="cell">
    <div class="l">סך כל החיובים</div>
    <div class="v num"><?= money_short($total_amount) ?></div>
  </div>
  <div class="cell">
    <div class="l">סך ששולם</div>
    <div class="v num ok"><?= money_short($total_paid) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>חובות וחיובים</h2>
    <div class="spacer"></div>
  </div>
  <div class="card-body flush">
    <?php if (!$debts): ?>
      <div class="empty"><p>אין חובות רשומים ללקוח זה.</p></div>
    <?php else: ?>
      <table class="data">
        <thead>
          <tr>
            <th>תיאור</th>
            <th>תאריך פירעון</th>
            <th>סכום</th>
            <th>שולם</th>
            <th>יתרה</th>
            <th>מצב</th>
            <th class="t-left">פעולה</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($debts as $d):
          $rem = $d['amount'] - $d['paid'];
          $isPaid = ($d['status']==='paid' || $rem <= 0);
          $level = $isPaid ? 'paid' : debt_alert_level($d['due_date']);
        ?>
          <tr class="<?= (!$isPaid && $level==='overdue') ? 'row-overdue':'' ?>">
            <td><?= e($d['description'] ?: '—') ?><?php if (!empty($d['retainer_id'])): ?> <span class="badge badge-retainer"><span class="pip"></span>ריטיינר</span><?php endif; ?></td>
            <td class="num nowrap"><?= fmt_date($d['due_date']) ?></td>
            <td class="money"><?= money_short($d['amount']) ?></td>
            <td class="money muted"><?= money_short($d['paid']) ?></td>
            <td class="money"><?= $rem>0 ? money_short($rem) : '—' ?></td>
            <td>
              <?php if ($isPaid): ?>
                <span class="badge badge-paid"><span class="pip"></span>שולם</span>
              <?php elseif ($level==='overdue'): ?>
                <span class="badge badge-overdue"><span class="pip"></span><?= e(debt_alert_text($d['due_date'])) ?></span>
              <?php elseif ($level==='soon'): ?>
                <span class="badge badge-soon"><span class="pip"></span><?= e(debt_alert_text($d['due_date'])) ?: 'לקראת פירעון' ?></span>
              <?php elseif ($level==='none'): ?>
                <span class="badge badge-neutral"><span class="pip"></span>פתוח</span>
              <?php else: ?>
                <span class="badge badge-ok"><span class="pip"></span>פתוח</span>
              <?php endif; ?>
            </td>
            <td class="t-left nowrap">
              <?php if (!$isPaid): ?>
                <a class="btn btn-ok btn-sm" href="debt_pay.php?id=<?= (int)$d['id'] ?>">תשלום</a>
              <?php endif; ?>
              <a class="btn btn-danger-ghost btn-sm" href="debt_delete.php?id=<?= (int)$d['id'] ?>" onclick="return confirm('למחוק את החוב והתשלומים שלו?')">מחק</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>ריטיינר חודשי קבוע</h2></div>
  <div class="card-body">
    <?php foreach ($retainers as $r): ?>
      <div class="retainer-row">
        <div>
          <strong><?= e($r['description']) ?></strong> · <?= money_short($r['amount']) ?> לחודש
          <div class="muted" style="font-size:13px;margin-top:2px">
            חיוב ביום <?= (int)$r['billing_day'] ?> בכל חודש · מתאריך <?= fmt_date($r['start_date']) ?><?= $r['end_date'] ? ' · עד ' . fmt_date($r['end_date']) : '' ?>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <?php if ($r['status']==='active'): ?>
            <span class="badge badge-ok"><span class="pip"></span>פעיל</span>
          <?php else: ?>
            <span class="badge badge-neutral"><span class="pip"></span>מושהה</span>
          <?php endif; ?>
          <form method="post" style="display:inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_retainer">
            <input type="hidden" name="retainer_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><?= $r['status']==='active' ? 'השהה' : 'הפעל' ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('למחוק את הריטיינר? החיובים שכבר נוצרו יישמרו.')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_retainer">
            <input type="hidden" name="retainer_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-danger-ghost btn-sm" type="submit">מחק</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>

    <form method="post" style="margin-top:<?= $retainers ? '18px' : '0' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_retainer">
      <div class="form-grid">
        <div class="field"><label>תיאור</label><input type="text" name="r_description" placeholder="ריטיינר חודשי"></div>
        <div class="field"><label>סכום חודשי (₪) <span class="req">*</span></label><input type="text" name="r_amount" inputmode="decimal" placeholder="0.00" required></div>
        <div class="field"><label>יום חיוב בחודש <span class="req">*</span></label><input type="number" name="r_billing_day" min="1" max="28" value="1" required><div class="hint">יום קבוע בין 1 ל-28</div></div>
        <div class="field"><label>תאריך התחלה</label><input type="date" name="r_start_date" value="<?= date('Y-m-d') ?>"></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">הוסף ריטיינר</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>הוספת חוב / חיוב</h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_debt">
      <div class="form-grid">
        <div class="field">
          <label>תיאור</label>
          <input type="text" name="description" placeholder="לדוגמה: חשבונית 1042">
        </div>
        <div class="field">
          <label>סכום (₪) <span class="req">*</span></label>
          <input type="text" name="amount" inputmode="decimal" placeholder="0.00" required>
        </div>
        <div class="field">
          <label>תאריך פירעון</label>
          <input type="date" name="due_date">
          <div class="hint">לפי תאריך זה תופיע ההתראה בלוח הבקרה</div>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-accent" type="submit">הוסף חוב</button>
      </div>
    </form>
  </div>
</div>

<?php if ($payments): ?>
<div class="card">
  <div class="card-head"><h2>היסטוריית תשלומים</h2></div>
  <div class="card-body flush">
    <table class="data">
      <thead>
        <tr><th>תאריך</th><th>עבור</th><th>אמצעי</th><th>הערה</th><th class="t-left">סכום</th></tr>
      </thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td class="num nowrap"><?= fmt_date($p['payment_date']) ?></td>
          <td class="muted"><?= e($p['debt_desc'] ?: '—') ?></td>
          <td><?= e($p['method'] ?: '—') ?></td>
          <td class="muted"><?= e($p['notes'] ?: '') ?></td>
          <td class="money t-left" style="color:var(--ok)"><?= money_short($p['amount']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
