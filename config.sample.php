<?php
/**
 * הגדרות חיבור למסד הנתונים
 * ----------------------------------
 * ערוך את הפרטים הבאים לפי מה שהגדרת ב-cPanel תחת "MySQL Databases".
 * בדרך כלל שם המשתמש ושם מסד הנתונים יתחילו בקידומת של החשבון שלך,
 * לדוגמה: myuser_bidant
 */

define('DB_HOST', 'localhost');          // כמעט תמיד localhost ב-cPanel
define('DB_NAME', 'CHANGE_ME_dbname');   // שם מסד הנתונים
define('DB_USER', 'CHANGE_ME_dbuser');   // שם המשתמש של מסד הנתונים
define('DB_PASS', 'CHANGE_ME_password'); // הסיסמה של מסד הנתונים

// שם המערכת (מוצג בכותרת)
define('APP_NAME', 'bidernet – ניהול לקוחות');

// כתובת הבסיס של המערכת — משמשת לבניית קישור איפוס סיסמה במייל
define('APP_URL', 'https://day.bidernet.co.il');

// כתובת השולח של מיילים אוטומטיים מהמערכת
define('MAIL_FROM', 'noreply@bidernet.co.il');

// ===== אינטגרציית WhatsApp דרך MegaSend (Weblix) =====
// השג את הערכים מלוח הבקרה של MegaSend ב-app.megasend.io והדבק כאן:
define('MEGASEND_API_URL', 'https://api.megasend.co.il');
define('MEGASEND_TOKEN', '');        // ה-Access Token (mega_token_...)
define('MEGASEND_INSTANCE_ID', '');  // מזהה ה-Instance של חשבון ה-WhatsApp Business

// ===== שליחת תזכורות חידוש אוטומטית (cron) =====
define('REMINDER_DAYS', [14, 3]);    // כמה ימים לפני החידוש לשלוח תזכורת (אפשר להוסיף/לשנות)
define('CRON_KEY', '');              // מפתח סודי להרצת ה-cron דרך כתובת אינטרנט (אם אין הרצת CLI)

// אזור זמן
date_default_timezone_set('Asia/Jerusalem');
