<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
retainers_bootstrap($pdo); // יצירה אוטומטית של חיובי ריטיינר חודשיים

$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$balanceExpr = "IFNULL((
    SELECT SUM(GREATEST(d.amount - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.debt_id=d.id),0),0))
    FROM debts d WHERE d.customer_id=c.id AND d.status='open'
),0)";

$where  = [];
$params = [];
if ($q !== '') {
    $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR c.id_number LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
if ($filter === 'active')   $where[] = "c.status='active'";
if ($filter === 'inactive') $where[] = "c.status='inactive'";

$whereSql  = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$havingSql = $filter === 'debtors' ? 'HAVING balance > 0' : '';

$sql = "SELECT c.*, $balanceExpr AS balance
        FROM customers c
        $whereSql
        $havingSql
        ORDER BY balance DESC, c.full_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$active = 'customers';
$page_title = 'לקוחות';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <form method="get" class="toolbar">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="חיפוש לפי שם, טלפון או ת.ז…">
    <select name="filter" onchange="this.form.submit()">
      <option value="all"      <?= $filter==='all'?'selected':'' ?>>כל הלקוחות</option>
      <option value="debtors"  <?= $filter==='debtors'?'selected':'' ?>>חייבים בלבד</option>
      <option value="active"   <?= $filter==='active'?'selected':'' ?>>פעילים</option>
      <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>לא פעילים</option>
    </select>
    <button class="btn btn-ghost btn-sm" type="submit">סינון</button>
  </form>
  <div class="spacer"></div>
  <a class="btn btn-accent" href="customer_form.php">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>
    לקוח חדש
  </a>
</div>

<div class="card">
  <div class="card-body flush">
    <?php if (!$customers): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        <p><?= $q!=='' || $filter!=='all' ? 'לא נמצאו לקוחות שמתאימים לחיפוש.' : 'עדיין אין לקוחות במערכת.' ?></p>
        <a class="btn btn-accent" href="customer_form.php">הוסף לקוח ראשון</a>
      </div>
    <?php else: ?>
      <table class="data">
        <thead>
          <tr>
            <th>שם</th>
            <th>טלפון</th>
            <th>הצטרף</th>
            <th>סטטוס</th>
            <th class="t-left">יתרת חוב</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><a class="cust-link" href="customer_view.php?id=<?= (int)$c['id'] ?>"><?= e($c['full_name']) ?></a></td>
            <td class="num muted"><?= e($c['phone'] ?: '—') ?></td>
            <td class="num nowrap muted"><?= fmt_date($c['join_date']) ?></td>
            <td>
              <?php if ($c['status']==='active'): ?>
                <span class="badge badge-ok"><span class="pip"></span>פעיל</span>
              <?php else: ?>
                <span class="badge badge-neutral"><span class="pip"></span>לא פעיל</span>
              <?php endif; ?>
            </td>
            <td class="money t-left">
              <?php if ($c['balance'] > 0): ?>
                <span style="color:var(--danger)"><?= money_short($c['balance']) ?></span>
              <?php else: ?>
                <span class="muted">—</span>
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
