<?php
/**
 * שליחת תזכורות חידוש אוטומטית בוואטסאפ.
 * הרצה:
 *   - דרך CLI (מומלץ):  php /path/to/cron.php
 *   - דרך כתובת:        https://day.bidernet.co.il/cron.php?key=CRON_KEY
 *
 * הגדר ב-cPanel ← Cron Jobs הרצה יומית, למשל 09:00:
 *   php /home/<account>/day.bidernet.co.il/cron.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    // הרצה דרך הדפדפן מחייבת מפתח סודי תקין
    if (!defined('CRON_KEY') || CRON_KEY === '' || (($_GET['key'] ?? '') !== CRON_KEY)) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}

$pdo = db();
ensure_hosting_schema($pdo);
ensure_reminder_log_schema($pdo);

if (!greenapi_enabled()) {
    echo "GREEN API לא מוגדר (GREENAPI_ID / GREENAPI_TOKEN). יציאה.\n";
    exit;
}

$days = (defined('REMINDER_DAYS') && is_array(REMINDER_DAYS)) ? REMINDER_DAYS : [14, 3];

$sites = $pdo->query("
    SELECT * FROM hosting
    WHERE status='active' AND renewal_date IS NOT NULL AND phone IS NOT NULL AND phone <> ''
")->fetchAll();

$sent = 0; $skipped = 0; $failed = 0;
echo "התחלת ריצה: " . date('Y-m-d H:i') . "\n";

foreach ($sites as $s) {
    $d = days_until($s['renewal_date']);
    if (!in_array($d, $days, true)) continue;

    // "תפיסת" השליחה — אם כבר נשלחה תזכורת לאותו אתר/חידוש/מרחק, נדלג
    $claim = $pdo->prepare("INSERT IGNORE INTO reminder_log (hosting_id, renewal_date, days_before, status) VALUES (?,?,?, 'sending')");
    $claim->execute([$s['id'], $s['renewal_date'], $d]);
    if ($claim->rowCount() === 0) { $skipped++; continue; }

    $res = greenapi_send_text($s['phone'], renewal_reminder_text($s));

    if (!empty($res['ok'])) {
        $pdo->prepare("UPDATE reminder_log SET status='sent', detail=? WHERE hosting_id=? AND renewal_date=? AND days_before=?")
            ->execute(['נשלח', $s['id'], $s['renewal_date'], $d]);
        $pdo->prepare("UPDATE hosting SET last_reminder_at=NOW() WHERE id=?")->execute([$s['id']]);
        $sent++;
        echo "נשלח: {$s['domain']} ({$d} ימים לפני חידוש)\n";
    } else {
        $err = mb_substr($res['error'] ?? 'שגיאה', 0, 200, 'UTF-8');
        $pdo->prepare("UPDATE reminder_log SET status='failed', detail=? WHERE hosting_id=? AND renewal_date=? AND days_before=?")
            ->execute([$err, $s['id'], $s['renewal_date'], $d]);
        $failed++;
        echo "נכשל: {$s['domain']} – {$err}\n";
    }
}

echo "סיכום: נשלחו {$sent}, דולגו {$skipped}, נכשלו {$failed}.\n";
