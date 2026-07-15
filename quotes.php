<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_quote_schema($pdo);
ensure_settings_schema($pdo);

$tab = (($_GET['tab'] ?? '') === 'signed') ? 'signed' : 'open';

$where = $tab === 'signed' ? "status='signed'" : "status<>'signed'";
$order = $tab === 'signed' ? "signed_at DESC" : "created_at DESC";
$rows = $pdo->query("
    SELECT q.*, (SELECT COALESCE(SUM(price*qty),0) FROM quote_items qi WHERE qi.quote_id=q.id) AS sub
    FROM quotes q WHERE $where ORDER BY $order
")->fetchAll();

$open_cnt   = (int)$pdo->query("SELECT COUNT(*) FROM quotes WHERE status<>'signed'")->fetchColumn();
$signed_cnt = (int)$pdo->query("SELECT COUNT(*) FROM quotes WHERE status='signed'")->fetchColumn();

$active = 'quotes';
$page_title = 'הצעות מחיר';
include __DIR__ . '/includes/header.php';
?>

<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-ghost btn-sm" href="quote_services.php" style="margin-inline-end:8px">📚 קטלוג שירותים</a>
  <a class="btn btn-accent" href="quote_form.php">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>
    הצעה חדשה
  </a>
</div>

<div class="subtabs">
  <a class="btn btn-sm <?= $tab==='open'?'':'btn-ghost' ?>" href="quotes.php?tab=open">📝 הצעות מחיר (<?= $open_cnt ?>)</a>
  <a class="btn btn-sm <?= $tab==='signed'?'':'btn-ghost' ?>" href="quotes.php?tab=signed">✅ הזמנות חתומות (<?= $signed_cnt ?>)</a>
</div>

<div class="card">
  <div class="card-head"><h2><?= $tab==='signed'?'הזמנות חתומות':'הצעות מחיר' ?></h2></div>
  <div class="card-body flush">
    <?php if (!$rows): ?>
      <div class="empty"><p><?= $tab==='signed'?'אין עדיין הזמנות חתומות.':'אין עדיין הצעות מחיר.' ?></p>
      <?php if ($tab!=='signed'): ?><a class="btn btn-accent" href="quote_form.php">צור הצעה ראשונה</a><?php endif; ?></div>
    <?php else: ?>
      <table class="data">
        <thead><tr>
          <th>מסמך</th><th>סטטוס</th><th>תאריך</th>
          <?php if ($tab==='signed'): ?><th>תאריך חתימה</th><?php endif; ?>
          <th>סה״כ כולל מע״מ</th><th class="t-left">פעולה</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $q):
          $total = (float)$q['sub'] * (1 + VAT_RATE);
          $badge = $q['status']==='signed'
            ? '<span class="badge badge-paid"><span class="pip"></span>חתום</span>'
            : ($q['mode']==='order'
                ? '<span class="badge badge-soon"><span class="pip"></span>הזמנת עבודה</span>'
                : '<span class="badge badge-neutral"><span class="pip"></span>הצעה</span>');
        ?>
          <tr>
            <td><a class="cust-link" href="quote_form.php?id=<?= (int)$q['id'] ?>">#<?= (int)$q['doc_no'] ?> · <?= e($q['client_name'] ?: '—') ?></a></td>
            <td><?= $badge ?></td>
            <td class="num nowrap"><?= fmt_date($q['quote_date']) ?></td>
            <?php if ($tab==='signed'): ?><td class="num nowrap muted"><?= $q['signed_at']?fmt_date($q['signed_at']):'—' ?></td><?php endif; ?>
            <td class="money"><?= money_short($total) ?></td>
            <td class="t-left nowrap">
              <a class="btn btn-ghost btn-sm" href="quote_doc.php?id=<?= (int)$q['id'] ?>" target="_blank">צפייה</a>
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
