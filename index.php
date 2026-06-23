<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
retainers_bootstrap($pdo); // יצירה אוטומטית של חיובי ריטיינר חודשיים
ensure_hosting_schema($pdo);

/* ביטוי "יתרת חוב" לכל שורת חוב = סכום פחות סך התשלומים אליה */
$REMAIN = "(d.amount - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.debt_id = d.id),0))";

// סך החוב הכולל
$total_owed = (float) $pdo->query("
    SELECT IFNULL(SUM($REMAIN),0) FROM debts d
    WHERE d.status='open' AND $REMAIN > 0
")->fetchColumn();

// מספר לקוחות חייבים
$debtors = (int) $pdo->query("
    SELECT COUNT(DISTINCT d.customer_id) FROM debts d
    WHERE d.status='open' AND $REMAIN > 0
")->fetchColumn();

// סך כל הלקוחות
$total_customers = (int) $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();

// חובות באיחור
$overdue = $pdo->query("
    SELECT COUNT(*) cnt, IFNULL(SUM($REMAIN),0) sum FROM debts d
    WHERE d.status='open' AND $REMAIN > 0 AND d.due_date IS NOT NULL AND d.due_date < CURDATE()
")->fetch();

// לקוחות חדשים החודש
$new_month = (int) $pdo->query("
    SELECT COUNT(*) FROM customers
    WHERE join_date IS NOT NULL AND YEAR(join_date)=YEAR(CURDATE()) AND MONTH(join_date)=MONTH(CURDATE())
")->fetchColumn();

// התראות: חובות פתוחים לפי תאריך פירעון (הקרובים/באיחור קודם)
$alerts = $pdo->query("
    SELECT d.id, d.description, d.due_date, d.amount, $REMAIN AS remaining,
           c.id AS customer_id, c.full_name
    FROM debts d
    JOIN customers c ON c.id = d.customer_id
    WHERE d.status='open' AND $REMAIN > 0
    ORDER BY (d.due_date IS NULL), d.due_date ASC
    LIMIT 12
")->fetchAll();

// חידושי אחסון שדורשים תשומת לב (פג / עד 30 יום)
$hosting_renewals = $pdo->query("
    SELECT id, domain, customer_name, phone, renewal_date, annual_price
    FROM hosting
    WHERE status='active' AND renewal_date IS NOT NULL
      AND renewal_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY renewal_date ASC
    LIMIT 10
")->fetchAll();

$active = 'dashboard';
$page_title = 'לוח בקרה';
include __DIR__ . '/includes/header.php';
?>

<div class="kpi-grid">
  <div class="kpi hero">
    <div class="label">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1l9 4v6c0 5.55-3.84 10.74-9 12-5.16-1.26-9-6.45-9-12V5l9-4z" opacity=".25"/><path d="M11 7h2v2h-2zm0 4h2v6h-2z"/></svg>
      סך החוב הפתוח
    </div>
    <div class="value num"><span class="cur">₪</span><?= number_format($total_owed, 0, '.', ',') ?></div>
    <div class="sub"><?= $debtors ?> לקוחות עם יתרה פתוחה</div>
  </div>

  <div class="kpi">
    <div class="label">לקוחות חייבים</div>
    <div class="value num"><?= $debtors ?></div>
    <div class="sub">מתוך <?= $total_customers ?> לקוחות</div>
  </div>

  <div class="kpi">
    <div class="label">חובות באיחור</div>
    <div class="value num <?= $overdue['cnt']>0?'danger':'' ?>"><?= (int)$overdue['cnt'] ?></div>
    <div class="sub"><?= money_short($overdue['sum']) ?> סך באיחור</div>
  </div>

  <div class="kpi">
    <div class="label">הצטרפו החודש</div>
    <div class="value num"><?= $new_month ?></div>
    <div class="sub">לקוחות חדשים</div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h2>התראות חובות לפי תאריך</h2>
    <div class="spacer"></div>
    <a class="btn btn-ghost btn-sm" href="debts.php">לכל החובות</a>
  </div>
  <div class="card-body flush">
    <?php if (!$alerts): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <p>אין חובות פתוחים. הכל מסודר.</p>
      </div>
    <?php else: ?>
      <table class="data">
        <thead>
          <tr>
            <th>לקוח</th>
            <th>תיאור</th>
            <th>תאריך פירעון</th>
            <th>מצב</th>
            <th class="t-left">יתרה</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($alerts as $a):
          $level = debt_alert_level($a['due_date']);
          $rowClass = $level==='overdue' ? 'row-overdue' : '';
        ?>
          <tr class="<?= $rowClass ?>">
            <td><a class="cust-link" href="customer_view.php?id=<?= (int)$a['customer_id'] ?>"><?= e($a['full_name']) ?></a></td>
            <td class="muted"><?= e($a['description'] ?: '—') ?></td>
            <td class="nowrap num"><?= fmt_date($a['due_date']) ?></td>
            <td>
              <?php if ($level==='overdue'): ?>
                <span class="badge badge-overdue"><span class="pip"></span><?= e(debt_alert_text($a['due_date'])) ?></span>
              <?php elseif ($level==='soon'): ?>
                <span class="badge badge-soon"><span class="pip"></span><?= e(debt_alert_text($a['due_date'])) ?: 'לקראת פירעון' ?></span>
              <?php elseif ($level==='none'): ?>
                <span class="badge badge-neutral"><span class="pip"></span>ללא תאריך</span>
              <?php else: ?>
                <span class="badge badge-ok"><span class="pip"></span>בזמן</span>
              <?php endif; ?>
            </td>
            <td class="money t-left"><?= money_short($a['remaining']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php if ($hosting_renewals): ?>
<div class="card">
  <div class="card-head">
    <h2>חידושי אחסון קרובים</h2>
    <div class="spacer"></div>
    <a class="btn btn-ghost btn-sm" href="hosting.php">לכל האחסון</a>
  </div>
  <div class="card-body flush">
    <table class="data">
      <thead><tr><th>דומיין</th><th>לקוח</th><th>תאריך חידוש</th><th>מצב</th><th>עלות שנתית</th><th class="t-left">תזכורת</th></tr></thead>
      <tbody>
      <?php foreach ($hosting_renewals as $hr):
        $hl = hosting_alert_level($hr['renewal_date']);
      ?>
        <tr class="<?= $hl==='overdue'?'row-overdue':'' ?>">
          <td><a class="cust-link" href="hosting.php?edit=<?= (int)$hr['id'] ?>"><?= e($hr['domain']) ?></a></td>
          <td class="muted"><?= e($hr['customer_name'] ?: '—') ?></td>
          <td class="num nowrap"><?= fmt_date($hr['renewal_date']) ?></td>
          <td>
            <?php if ($hl==='overdue'): ?>
              <span class="badge badge-overdue"><span class="pip"></span><?= e(hosting_alert_text($hr['renewal_date'])) ?></span>
            <?php else: ?>
              <span class="badge badge-soon"><span class="pip"></span><?= e(hosting_alert_text($hr['renewal_date'])) ?></span>
            <?php endif; ?>
          </td>
          <td class="money"><?= money_short($hr['annual_price']) ?></td>
          <td class="t-left nowrap"><?= reminder_button_html($hr, 'index.php') ?: '<span class="muted">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
