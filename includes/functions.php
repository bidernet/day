<?php

/** מנקה פלט מפני XSS */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** מפרמט סכום בשקלים: 1234.5 => ₪1,234.50 */
function money($amount) {
    return '₪' . number_format((float)$amount, 2, '.', ',');
}

/** מפרמט סכום קצר ללא אגורות אם עגול: ₪1,234 */
function money_short($amount) {
    $amount = (float)$amount;
    $decimals = (floor($amount) == $amount) ? 0 : 2;
    return '₪' . number_format($amount, $decimals, '.', ',');
}

/** מפרמט תאריך לתצוגה: 2025-06-23 => 23/06/2025 */
function fmt_date($date) {
    if (empty($date) || $date === '0000-00-00') return '—';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : e($date);
}

/** מחזיר את מספר הימים עד תאריך פירעון (שלילי = באיחור) */
function days_until($date) {
    if (empty($date)) return null;
    $due = strtotime(date('Y-m-d', strtotime($date)));
    $today = strtotime(date('Y-m-d'));
    return (int)round(($due - $today) / 86400);
}

/** מחזיר מצב התראה לחוב פתוח לפי תאריך הפירעון */
function debt_alert_level($due_date) {
    $d = days_until($due_date);
    if ($d === null) return 'none';
    if ($d < 0)  return 'overdue';   // באיחור
    if ($d <= 7) return 'soon';      // לקראת פירעון
    return 'ok';
}

/** טקסט תיאור למצב ההתראה */
function debt_alert_text($due_date) {
    $d = days_until($due_date);
    if ($d === null) return '';
    if ($d < 0)  return 'באיחור ' . abs($d) . ' ימים';
    if ($d === 0) return 'פירעון היום';
    if ($d === 1) return 'מחר';
    if ($d <= 7) return 'בעוד ' . $d . ' ימים';
    return '';
}

/* ---------- CSRF ---------- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
            http_response_code(403);
            die('בקשה לא תקינה (CSRF). חזור אחורה ונסה שוב.');
        }
    }
}

/** הודעת פלאש פשוטה */
function flash($msg = null) {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return; }
    if (!empty($_SESSION['flash'])) {
        $m = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return null;
}

/* ---------- מנהלים / איפוס סיסמה ---------- */

/** מוודא שקיימים הטבלאות/עמודות הדרושים לניהול מנהלים ולאיפוס סיסמה */
function ensure_admin_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token_hash),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $hasEmail = (int)$pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email'
    ")->fetchColumn();
    if (!$hasEmail) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(160) DEFAULT NULL AFTER username");
    }
}

/** שולח מייל HTML בעברית מהמערכת. מחזיר true/false */
function send_system_mail($to, $subject, $html) {
    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: bidernet <' . MAIL_FROM . '>';
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encodedSubject, $html, implode("\r\n", $headers));
}

/** עוטף תוכן מייל בתבנית HTML בסיסית בעברית */
function mail_template($title, $bodyHtml) {
    return '<!DOCTYPE html><html lang="he" dir="rtl"><body style="font-family:Arial,sans-serif;background:#f5f7f9;margin:0;padding:24px">'
         . '<div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #ebeef1;border-radius:14px;overflow:hidden">'
         . '<div style="background:#181b1d;color:#fff;padding:18px 24px;font-size:20px;font-weight:bold">bidernet</div>'
         . '<div style="padding:24px;color:#181b1d;font-size:15px;line-height:1.7">'
         . '<h2 style="margin:0 0 14px;font-size:18px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>'
         . $bodyHtml
         . '</div></div></body></html>';
}

/* ---------- ריטיינר חודשי קבוע ---------- */

/** מוודא שקיימים הטבלה והעמודות לריטיינרים */
function ensure_retainer_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS retainers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        billing_day TINYINT NOT NULL DEFAULT 1,
        start_date DATE NOT NULL,
        end_date DATE DEFAULT NULL,
        status ENUM('active','paused') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer (customer_id),
        CONSTRAINT fk_ret_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach (['retainer_id' => 'INT DEFAULT NULL', 'period' => 'CHAR(7) DEFAULT NULL'] as $col => $def) {
        $has = (int)$pdo->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='debts' AND COLUMN_NAME='$col'
        ")->fetchColumn();
        if (!$has) $pdo->exec("ALTER TABLE debts ADD COLUMN $col $def");
    }
    $idx = (int)$pdo->query("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='debts' AND INDEX_NAME='uniq_retainer_period'
    ")->fetchColumn();
    if (!$idx) $pdo->exec("ALTER TABLE debts ADD UNIQUE KEY uniq_retainer_period (retainer_id, period)");
}

/** שמות חודשים בעברית (1-12) */
function hebrew_month($m) {
    $names = ['','ינואר','פברואר','מרץ','אפריל','מאי','יוני','יולי','אוגוסט','ספטמבר','אוקטובר','נובמבר','דצמבר'];
    return $names[(int)$m] ?? '';
}

/** יוצר אוטומטית חיובי ריטיינר חודשיים שטרם נוצרו, עד החודש הנוכחי */
function generate_retainer_charges($pdo) {
    $retainers = $pdo->query("SELECT * FROM retainers WHERE status='active'")->fetchAll();
    if (!$retainers) return;

    $today = new DateTime('today');
    $ins = $pdo->prepare("INSERT IGNORE INTO debts
        (customer_id, description, amount, due_date, status, retainer_id, period)
        VALUES (?,?,?,?, 'open', ?, ?)");

    foreach ($retainers as $r) {
        if (empty($r['start_date'])) continue;
        $start  = new DateTime($r['start_date']);
        $end    = !empty($r['end_date']) ? new DateTime($r['end_date']) : null;
        $cursor = new DateTime(date('Y-m-01', strtotime($r['start_date'])));
        $guard  = 0;

        while ($cursor <= $today && $guard < 240) {
            $guard++;
            $y   = (int)$cursor->format('Y');
            $m   = (int)$cursor->format('n');
            $dim = (int)$cursor->format('t');                 // ימים בחודש
            $day = min((int)$r['billing_day'], $dim);          // התאמה לחודשים קצרים
            $billing = new DateTime(sprintf('%04d-%02d-%02d', $y, $m, $day));

            $cursorNext = (clone $cursor)->modify('first day of next month');

            // לדלג על חודש ההתחלה אם יום החיוב כבר עבר בעת ההתחלה
            if ($billing < $start) { $cursor = $cursorNext; continue; }
            if ($end && $billing > $end) break;

            if ($billing <= $today) {
                $period = sprintf('%04d-%02d', $y, $m);
                $desc = ($r['description'] ?: 'ריטיינר חודשי') . ' – ' . hebrew_month($m) . ' ' . $y;
                $ins->execute([$r['customer_id'], $desc, $r['amount'], $billing->format('Y-m-d'), $r['id'], $period]);
            }
            $cursor = $cursorNext;
        }
    }
}

/** מאתחל סכמת ריטיינרים ומייצר חיובים שטרם נוצרו */
function retainers_bootstrap($pdo) {
    ensure_retainer_schema($pdo);
    generate_retainer_charges($pdo);
}

/* ---------- אחסון אתרים ---------- */

/** מוודא שקיימת טבלת האחסון */
function ensure_hosting_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hosting (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain VARCHAR(190) NOT NULL,
        customer_name VARCHAR(160) DEFAULT NULL,
        phone VARCHAR(40) DEFAULT NULL,
        annual_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        join_date DATE DEFAULT NULL,
        renewal_date DATE DEFAULT NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        notes TEXT DEFAULT NULL,
        last_reminder_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_renewal (renewal_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // הוספת עמודת טלפון אם הטבלה כבר קיימת בלעדיה
    $hasPhone = (int)$pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hosting' AND COLUMN_NAME='phone'
    ")->fetchColumn();
    if (!$hasPhone) $pdo->exec("ALTER TABLE hosting ADD COLUMN phone VARCHAR(40) DEFAULT NULL AFTER customer_name");

    // הוספת עמודת תזכורת אחרונה אם חסרה
    $hasRem = (int)$pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hosting' AND COLUMN_NAME='last_reminder_at'
    ")->fetchColumn();
    if (!$hasRem) $pdo->exec("ALTER TABLE hosting ADD COLUMN last_reminder_at DATETIME DEFAULT NULL");
}

/** בונה קישור וואטסאפ עם הודעה מוכנה. ממיר מספר ישראלי לפורמט בינלאומי. */
function wa_link($phone, $text = '') {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (strpos($digits, '0') === 0) $digits = '972' . substr($digits, 1);
    $url = 'https://wa.me/' . $digits;
    if ($text !== '') $url .= '?text=' . rawurlencode($text);
    return $url;
}

/** רמת התראה לחידוש אחסון (חלון של 30 יום) */
function hosting_alert_level($date) {
    $d = days_until($date);
    if ($d === null) return 'none';
    if ($d < 0)  return 'overdue';
    if ($d <= 30) return 'soon';
    return 'ok';
}

/** טקסט התראה לחידוש אחסון */
function hosting_alert_text($date) {
    $d = days_until($date);
    if ($d === null) return '';
    if ($d < 0)  return 'פג לפני ' . abs($d) . ' ימים';
    if ($d === 0) return 'לחידוש היום';
    if ($d === 1) return 'מחר';
    if ($d <= 30) return 'בעוד ' . $d . ' ימים';
    return 'בתוקף';
}

/* ---------- WhatsApp דרך MegaSend ---------- */

/** האם אינטגרציית WhatsApp מוגדרת */
function megasend_enabled() {
    return defined('MEGASEND_TOKEN') && MEGASEND_TOKEN !== ''
        && defined('MEGASEND_INSTANCE_ID') && MEGASEND_INSTANCE_ID !== '';
}

/** ממיר מספר ישראלי לפורמט בינלאומי לדוגמה +9725XXXXXXXX */
function megasend_format_phone($phone) {
    $d = preg_replace('/\D+/', '', (string)$phone);
    if ($d === '') return '';
    if (strpos($d, '0') === 0) $d = '972' . substr($d, 1);
    return '+' . $d;
}

/** שולח הודעת טקסט בוואטסאפ דרך MegaSend. מחזיר ['ok'=>bool, 'error'=>?, 'data'=>?] */
function megasend_send_text($phone, $text) {
    if (!megasend_enabled()) {
        return ['ok'=>false, 'error'=>'אינטגרציית WhatsApp לא הוגדרה (חסר טוקן או Instance ב-config.php).'];
    }
    if (!function_exists('curl_init')) {
        return ['ok'=>false, 'error'=>'הרחבת cURL אינה זמינה בשרת.'];
    }
    $recipient = megasend_format_phone($phone);
    if ($recipient === '') return ['ok'=>false, 'error'=>'מספר טלפון לא תקין.'];

    $payload = json_encode([
        'instance_id'  => MEGASEND_INSTANCE_ID,
        'recipient'    => $recipient,
        'message_type' => 'text',
        'text'         => $text,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(rtrim(MEGASEND_API_URL, '/') . '/messages/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['X-MEGASEND-AUTH: ' . MEGASEND_TOKEN, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) return ['ok'=>false, 'error'=>'שגיאת רשת: ' . $cerr];
    $data = json_decode($resp, true);
    if ($code >= 200 && $code < 300) return ['ok'=>true, 'data'=>$data];
    $msg = (is_array($data) && isset($data['detail'])) ? $data['detail'] : ('שגיאת API (קוד ' . $code . ')');
    return ['ok'=>false, 'error'=>$msg, 'http'=>$code, 'data'=>$data];
}

/** טקסט תזכורת חידוש אחסון מתוך רשומת אחסון */
function renewal_reminder_text($s) {
    return 'שלום' . (!empty($s['customer_name']) ? ' ' . $s['customer_name'] : '')
         . ', תזכורת לחידוש האחסון של הדומיין ' . $s['domain']
         . (!empty($s['renewal_date']) ? ' בתאריך ' . fmt_date($s['renewal_date']) : '')
         . '. נשמח להסדיר את החידוש בהקדם.';
}

/** מחזיר HTML לכפתור תזכורת: שליחה אוטומטית דרך API אם מוגדר, אחרת קישור wa.me ידני */
function reminder_button_html($s, $return = 'hosting.php') {
    if (empty($s['phone'])) return '';
    if (megasend_enabled()) {
        return '<form method="post" action="send_whatsapp.php" style="display:inline">'
             . csrf_field()
             . '<input type="hidden" name="hosting_id" value="' . (int)$s['id'] . '">'
             . '<input type="hidden" name="return" value="' . e($return) . '">'
             . '<button class="btn btn-ok btn-sm" type="submit">שלח תזכורת</button>'
             . '</form>';
    }
    return '<a class="btn btn-ok btn-sm" href="' . e(wa_link($s['phone'], renewal_reminder_text($s)))
         . '" target="_blank" rel="noopener">וואטסאפ</a>';
}

/** מוודא טבלת יומן תזכורות (מונע שליחות כפולות) */
function ensure_reminder_log_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reminder_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hosting_id INT NOT NULL,
        renewal_date DATE DEFAULT NULL,
        days_before INT DEFAULT NULL,
        status VARCHAR(20) DEFAULT NULL,
        detail VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reminder (hosting_id, renewal_date, days_before),
        INDEX idx_hosting (hosting_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/* ---------- בנק חבילות (גרפיקות/פוסטים) ---------- */

/** מוודא טבלת בנקים */
function ensure_bank_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS service_banks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        title VARCHAR(160) NOT NULL,
        total_qty INT NOT NULL DEFAULT 0,
        used_qty INT NOT NULL DEFAULT 0,
        price DECIMAL(12,2) NOT NULL DEFAULT 0,
        status ENUM('active','closed') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer (customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
