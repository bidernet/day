<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
retainers_bootstrap($pdo); // יצירה אוטומטית של חיובי ריטיינר חודשיים

$REMAIN = "(d.amount - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.debt_id = d.id),0))";
$filter = $_GET['filter'] ?? 'open';

$cond = "d.status='open' AND $REMAIN > 0";
if ($filter === 'overdue') {
    $cond .= " AND d.due_date IS NOT NULL AND d.due_date < CURDATE()";
} elseif ($filter === 'soon') {
    $cond .= " AND d.due_date IS NOT NULL AND d.due_date >= CURDATE() AND d.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter === 'paid') {
    $cond = "(d.status='paid' OR $REMAIN <= 0)";
}

$rows = $pdo->query("
    SELECT d.id, d.description, d.due_date, d.amount, d.status, $REMAIN AS remaining,
           c.id AS customer_id, c.full_name
    FROM debts d JOIN customers c ON c.id = d.customer_id
    WHERE $cond
    ORDER BY (d.due_date IS NULL), d.due_date ASC, d.id DESC
")->fetchAll();

$sum = 0;
foreach ($rows as $r) { if ($r['remaining'] > 0) $sum += $r['remaining']; }

$active = 'debts';
$page_title = 'חובות והתראות';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <form method="get" class="toolbar">
    <select name="filter" onchange="this.form.submit()">
      <option value="open"    <?= $filter==='open'?'selected':'' ?>>כל החובות הפתוחים</option>
      <option value="overdue" <?= $filter==='overdue'?'selected':'' ?>>באיחור</option>
      <option value="soon"    <?= $filter==='soon'?'selected':'' ?>>לפירעון תוך 7 ימים</option>
      <option value="paid"    <?= $filter==='paid'?'selected':'' ?>>שולמו</option>
    </select>
  </form>
  <div class="spacer"></div>
  <?php if ($filter !== 'paid'): ?>
    <div class="kpi" style="padding:10px 18px;box-shadow:none">
      <span class="muted" style="font-size:13px">סך מוצג:</span>
      <strong class="num" style="font-size:18px;margin-inline-start:6px;color:var(--danger)"><?= money_short($sum) ?></strong>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-body flush">
    <?php if (!$rows): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <p>אין רשומות להצגה בקטגוריה זו.</p>
      </div>
    <?php else: ?>
      <table class="data">
        <thead>
          <tr>
            <th>לקוח</th>
            <th>תיאור</th>
            <th>תאריך פירעון</th>
            <th>מצב</th>
            <th>יתרה</th>
            <th class="t-left">פעולה</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $isPaid = ($filter==='paid');
          $level = $isPaid ? 'paid' : debt_alert_level($r['due_date']);
        ?>
          <tr class="<?= (!$isPaid && $level==='overdue')?'row-overdue':'' ?>">
            <td><a class="cust-link" href="customer_view.php?id=<?= (int)$r['customer_id'] ?>"><?= e($r['full_name']) ?></a></td>
            <td class="muted"><?= e($r['description'] ?: '—') ?></td>
            <td class="num nowrap"><?= fmt_date($r['due_date']) ?></td>
            <td>
              <?php if ($isPaid): ?>
                <span class="badge badge-paid"><span class="pip"></span>שולם</span>
              <?php elseif ($level==='overdue'): ?>
                <span class="badge badge-overdue"><span class="pip"></span><?= e(debt_alert_text($r['due_date'])) ?></span>
              <?php elseif ($level==='soon'): ?>
                <span class="badge badge-soon"><span class="pip"></span><?= e(debt_alert_text($r['due_date'])) ?: 'לקראת פירעון' ?></span>
              <?php elseif ($level==='none'): ?>
                <span class="badge badge-neutral"><span class="pip"></span>ללא תאריך</span>
              <?php else: ?>
                <span class="badge badge-ok"><span class="pip"></span>בזמן</span>
              <?php endif; ?>
            </td>
            <td class="money"><?= money_short($r['remaining']) ?></td>
            <td class="t-left nowrap">
              <?php if (!$isPaid): ?>
                <a class="btn btn-ok btn-sm" href="debt_pay.php?id=<?= (int)$r['id'] ?>">תשלום</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
