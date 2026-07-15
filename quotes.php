<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quotes_schema($pdo);

$tab = ($_GET['tab'] ?? 'open') === 'signed' ? 'signed' : 'open';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM quote_items WHERE quote_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM quotes WHERE id=?")->execute([$id]);
    flash('ההצעה נמחקה.');
    header('Location: quotes.php?tab=' . $tab); exit;
}

$open_count   = (int)$pdo->query("SELECT COUNT(*) FROM quotes WHERE status<>'signed'")->fetchColumn();
$signed_count = (int)$pdo->query("SELECT COUNT(*) FROM quotes WHERE status='signed'")->fetchColumn();

$where = $tab === 'signed' ? "status='signed'" : "status<>'signed'";
$rows = $pdo->query("SELECT * FROM quotes WHERE $where ORDER BY created_at DESC")->fetchAll();

// חישוב סכום לכל הצעה
function quote_total_for($pdo, $qid) {
    $items = $pdo->prepare("SELECT price, qty FROM quote_items WHERE quote_id=?");
    $items->execute([$qid]);
    return quote_totals($items->fetchAll());
}

$active = 'quotes';
$page_title = 'הצעות מחיר';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-ghost btn-sm" href="quote_catalog.php" style="margin-inline-end:8px">📚 קטלוג שירותים</a>
  <a class="btn btn-accent" href="quote_form.php">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>
    הצעה חדשה
  </a>
</div>

<div class="subtabs" style="display:flex;gap:8px;margin-bottom:14px">
  <a class="btn <?= $tab==='open'?'':'btn-ghost' ?> btn-sm" href="quotes.php?tab=open">📝 הצעות מחיר (<?= $open_count ?>)</a>
  <a class="btn <?= $tab==='signed'?'':'btn-ghost' ?> btn-sm" href="quotes.php?tab=signed">✅ הזמנות חתומות (<?= $signed_count ?>)</a>
</div>

<div class="card">
  <div class="card-head"><h2><?= $tab==='signed' ? 'הזמנות חתומות' : 'הצעות מחיר' ?></h2></div>
  <div class="card-body flush">
    <?php if (!$rows): ?>
      <div class="empty"><p><?= $tab==='signed' ? 'אין עדיין הזמנות חתומות.' : 'אין עדיין הצעות מחיר.' ?></p>
        <?php if ($tab==='open'): ?><a class="btn btn-accent" href="quote_form.php">צור הצעה ראשונה</a><?php endif; ?>
      </div>
    <?php else: ?>
      <table class="data">
        <thead>
          <tr>
            <th>מסמך</th><th>לקוח</th><th>סטטוס</th><th>תאריך</th>
            <?php if ($tab==='signed'): ?><th>תאריך חתימה</th><?php endif; ?>
            <th>סה״כ כולל מע״מ</th><th class="t-left">פעולה</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $q):
          $t = quote_total_for($pdo, $q['id']);
          $badge = $q['status']==='signed'
            ? '<span class="badge badge-paid"><span class="pip"></span>חתום</span>'
            : ($q['mode']==='order'
                ? '<span class="badge badge-soon"><span class="pip"></span>הזמנת עבודה</span>'
                : '<span class="badge badge-neutral"><span class="pip"></span>הצעה</span>');
        ?>
          <tr>
            <td><a class="cust-link" href="quote_form.php?id=<?= (int)$q['id'] ?>">#<?= (int)$q['doc_no'] ?></a></td>
            <td><?= e($q['client_name'] ?: '—') ?><?php if ($q['phone']): ?><div class="muted num" style="font-size:12.5px"><?= e($q['phone']) ?></div><?php endif; ?></td>
            <td><?= $badge ?></td>
            <td class="num nowrap"><?= fmt_date($q['doc_date']) ?></td>
            <?php if ($tab==='signed'): ?><td class="num nowrap muted"><?= $q['signed_at'] ? fmt_date($q['signed_at']) : '—' ?></td><?php endif; ?>
            <td class="money"><?= money_short($t['total']) ?></td>
            <td class="t-left nowrap">
              <?php if ($q['public_token']): ?>
                <a class="btn btn-ok btn-sm" href="quote_sign.php?t=<?= e($q['public_token']) ?>" target="_blank" rel="noopener">מסך לקוח</a>
              <?php endif; ?>
              <a class="btn btn-ghost btn-sm" href="quote_form.php?id=<?= (int)$q['id'] ?>">עריכה</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
