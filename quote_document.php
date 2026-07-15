<?php
/**
 * מציג את מסמך ההצעה/הזמנה: עמודים מעוצבים + עמוד מחירים דינמי.
 * מצפה למשתנים: $q (רשומת quotes), $items (מערך פריטים).
 * אופציונלי: $QASSET (נתיב בסיס לתמונות, ברירת מחדל assets/quote).
 */
if (!isset($QASSET)) $QASSET = 'assets/quote';
$t = quote_totals($items);
$isOrder = ($q['mode'] === 'order');
$kind = $isOrder ? 'הזמנת עבודה' : 'הצעת מחיר';
?>
<div class="qdoc">
  <div class="qpage" style="position:relative">
    <img src="<?= $QASSET ?>/p1.jpg" alt="שער">
    <div style="position:absolute;top:9%;right:7%;text-align:right">
      <div style="background:#464646;color:#deedce;font-weight:700;font-size:14px;padding:6px 16px;border-radius:8px;white-space:nowrap;display:inline-block">תאריך מסמך: <?= fmt_date($q['quote_date']) ?></div>
      <div style="background:#464646;color:#deedce;font-weight:800;font-size:26px;padding:10px 24px;border-radius:12px;white-space:nowrap;margin-top:10px;display:inline-block"><?= e($kind) ?></div>
      <div style="color:#2f342c;font-size:20px;font-weight:800;margin-top:10px"><?= e($q['client_name']) ?></div>
    </div>
  </div>
  <div class="qpage"><img src="<?= $QASSET ?>/p2.jpg"></div>
  <div class="qpage"><img src="<?= $QASSET ?>/p3.jpg"></div>
  <div class="qpage"><img src="<?= $QASSET ?>/p4.jpg"></div>

  <div class="qpricing">
    <div class="qhead"><?= e($q['heading']) ?></div>
    <?php foreach ($items as $it): ?>
      <div class="qitem">
        <div class="qitem-t"><span><?= e($it['name']) ?></span><span><?= money_short((float)$it['price'] * max(1,(int)$it['qty'])) ?></span></div>
        <?= quote_desc_html($it['descr'], $it['fmt']) ?>
      </div>
    <?php endforeach; ?>
    <div class="qtotals">
      <div class="qrow"><span>סכום ביניים</span><span><?= money_short($t['sub']) ?></span></div>
      <div class="qrow"><span>מע״מ 17%</span><span><?= money_short($t['vat']) ?></span></div>
      <div class="qsum"><b><?= money_short($t['total']) ?></b><div>סה״כ כולל מע״מ · תוקף <?= (int)$q['validity'] ?> ימי עסקים</div></div>
    </div>
  </div>

  <?php if ($isOrder): ?>
  <div class="qpage"><img src="<?= $QASSET ?>/p6.jpg"></div>
  <?php endif; ?>
</div>
