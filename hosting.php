<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_hosting_schema($pdo);

// סימון "שולם" — קידום תאריך החידוש בשנה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    csrf_check();
    $sid = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare("SELECT renewal_date FROM hosting WHERE id=?");
    $st->execute([$sid]);
    $cur = $st->fetchColumn();
    if ($sid) {
        $base = $cur ?: date('Y-m-d');
        $next = date('Y-m-d', strtotime($base . ' +1 year'));
        $pdo->prepare("UPDATE hosting SET renewal_date=? WHERE id=?")->execute([$next, $sid]);
        flash('סומן כשולם — תאריך החידוש עודכן ל-' . fmt_date($next) . '.');
    }
    header('Location: hosting.php'); exit;
}

// רשימה
$sites = $pdo->query("
    SELECT * FROM hosting
    ORDER BY (renewal_date IS NULL), renewal_date ASC, domain ASC
")->fetchAll();

// סיכומים
$total_sites = 0; $annual_total = 0; $renew_due = 0;
foreach ($sites as $s) {
    if ($s['status'] === 'active') { $total_sites++; $annual_total += $s['annual_price']; }
    if ($s['status'] === 'active' && $s['renewal_date']) {
        $lvl = hosting_alert_level($s['renewal_date']);
        if ($lvl === 'overdue' || $lvl === 'soon') $renew_due++;
    }
}

$active = 'hosting';
$page_title = 'אחסון אתרים';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <div class="toolbar"></div>
  <div class="spacer"></div>
  <a class="btn btn-accent" href="hosting_form.php">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>
    הוספת אתר
  </a>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="kpi">
    <div class="label">אתרים באחסון</div>
    <div class="value num"><?= $total_sites ?></div>
    <div class="sub">אתרים פעילים</div>
  </div>
  <div class="kpi">
    <div class="label">הכנסה שנתית מאחסון</div>
    <div class="value num"><?= money_short($annual_total) ?></div>
    <div class="sub">סך החיוב השנתי</div>
  </div>
  <div class="kpi">
    <div class="label">חידושים קרובים</div>
    <div class="value num <?= $renew_due>0?'danger':'' ?>"><?= $renew_due ?></div>
    <div class="sub">פג / עד 30 יום</div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>אתרים באחסון</h2></div>
  <div class="card-body flush">
    <?php if (!$sites): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 13H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-6c0-.55-.45-1-1-1zM7 19c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM20 3H4c-.55 0-1 .45-1 1v6c0 .55.45 1 1 1h16c.55 0 1-.45 1-1V4c0-.55-.45-1-1-1zM7 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
        <p>עדיין לא נרשמו אתרים.</p>
        <a class="btn btn-accent" href="hosting_form.php">הוסף אתר ראשון</a>
      </div>
    <?php else: ?>
      <table class="data">
        <thead>
          <tr>
            <th>דומיין</th>
            <th>לקוח</th>
            <th>הצטרפות לשרת</th>
            <th>תאריך חידוש</th>
            <th>מצב חידוש</th>
            <th>עלות שנתית</th>
            <th class="t-left">פעולה</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sites as $s):
          $lvl = $s['status']==='inactive' ? 'inactive' : hosting_alert_level($s['renewal_date']);
          $rowClass = ($lvl==='overdue') ? 'row-overdue' : '';
        ?>
          <tr class="<?= $rowClass ?>">
            <td><a class="cust-link" href="hosting_form.php?id=<?= (int)$s['id'] ?>"><?= e($s['domain']) ?></a><?= $s['status']==='inactive' ? ' <span class="badge badge-neutral"><span class="pip"></span>לא פעיל</span>' : '' ?></td>
            <td><?= e($s['customer_name'] ?: '—') ?><?php if (!empty($s['phone'])): ?><div class="muted num" style="font-size:12.5px"><?= e($s['phone']) ?></div><?php endif; ?></td>
            <td class="num nowrap muted"><?= fmt_date($s['join_date']) ?></td>
            <td class="num nowrap"><?= fmt_date($s['renewal_date']) ?></td>
            <td>
              <?php if ($s['status']==='inactive'): ?>
                <span class="muted">—</span>
              <?php elseif ($lvl==='overdue'): ?>
                <span class="badge badge-overdue"><span class="pip"></span><?= e(hosting_alert_text($s['renewal_date'])) ?></span>
              <?php elseif ($lvl==='soon'): ?>
                <span class="badge badge-soon"><span class="pip"></span><?= e(hosting_alert_text($s['renewal_date'])) ?></span>
              <?php elseif ($lvl==='none'): ?>
                <span class="badge badge-neutral"><span class="pip"></span>ללא תאריך</span>
              <?php else: ?>
                <span class="badge badge-ok"><span class="pip"></span>בתוקף</span>
              <?php endif; ?>
            </td>
            <td class="money"><?= money_short($s['annual_price']) ?></td>
            <td class="t-left nowrap">
              <?php if ($s['status']==='active'): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('לסמן שהלקוח שילם? תאריך החידוש יקודם בשנה.')">
                <?= csrf_field() ?><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-ok btn-sm" type="submit">שולם</button>
              </form>
              <?php endif; ?>
              <?= reminder_button_html($s, 'hosting.php') ?>
              <a class="btn btn-ghost btn-sm" href="hosting_form.php?id=<?= (int)$s['id'] ?>">עריכה</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
