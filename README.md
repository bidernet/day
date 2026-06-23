# bidernet – מערכת ניהול לקוחות, חובות ואחסון
גרסה 1.0 · מבוססת PHP + MySQL · להתקנה על שרת cPanel
כתובת היעד: **https://day.bidernet.co.il**

---

## הוראות לחברת האחסון

### דרישות שרת
- PHP 7.4 ומעלה (מומלץ 8.x)
- MySQL / MariaDB
- הרחבות PHP: PDO_MySQL, mbstring, cURL (סטנדרטי ב-cPanel)

### שלבי התקנה
1. **הצבת הקבצים** — העלו את כל תוכן ה-ZIP אל ה-Document Root של תת-הדומיין `day.bidernet.co.il`
   (לרוב `/home/<account>/day.bidernet.co.il/`), כך ש-`https://day.bidernet.co.il/index.php` עובד.

2. **מסד נתונים** — ב-cPanel ← *MySQL Databases*: צרו מסד נתונים ומשתמש עם **All Privileges**.

3. **עריכת `config.php`** — מלאו את פרטי מסד הנתונים בלבד:
   ```php
   define('DB_NAME', 'שם_מסד_הנתונים');
   define('DB_USER', 'שם_המשתמש');
   define('DB_PASS', 'הסיסמה');
   ```
   (שאר ההגדרות בקובץ — WhatsApp ו-cron — אפשר להשאיר ריקות בשלב זה.)

4. **הרצת ההתקנה** — גלשו אל `https://day.bidernet.co.il/install.php`,
   המערכת תיצור את כל הטבלאות, והגדירו משתמש מנהל (שם, אימייל, סיסמה).

5. **מחיקת `install.php`** מהשרת (חובה לאבטחה).

6. **SSL** — הפעילו תעודת SSL (AutoSSL) כדי שהמערכת תרוץ ב-https.

### הגדרת Cron (אופציונלי – לתזכורות חידוש אוטומטיות)
תחת *Cron Jobs* בהרצה יומית (למשל 09:00):
```
php /home/<account>/day.bidernet.co.il/cron.php
```
פועל רק לאחר שמוגדרת אינטגרציית WhatsApp (ראו למטה).

---

## יכולות המערכת
- **לקוחות** — כרטיס לקוח מלא (פרטי קשר, ת.ז/ח.פ, תאריך הצטרפות, סטטוס, הערות).
- **חובות** — חיובים עם תאריך פירעון, תשלומים (כולל חלקיים), והתראות לפי תאריך.
- **ריטיינר חודשי קבוע** — חיוב חודשי שנוצר אוטומטית לכל לקוח לפי יום חיוב שנבחר.
- **אחסון אתרים** — רישום אתרים (דומיין, לקוח, טלפון, עלות שנתית, הצטרפות, חידוש) עם התראות חידוש.
- **WhatsApp** — שליחת תזכורות חידוש ללקוחות דרך MegaSend API (ידני + אוטומטי ב-cron).
- **מנהלים** — ניהול משתמשי מערכת, הוספה, שינוי סיסמה.
- **שחזור סיסמה** — דרך קישור איפוס במייל.
- **לוח בקרה** — סיכומי חוב בש״ח, חייבים, חובות באיחור, וחידושי אחסון קרובים.

---

## הגדרות אופציונליות (ב-`config.php`, אפשר למלא אחרי ההעלאה)

### WhatsApp (MegaSend)
```php
define('MEGASEND_TOKEN', '');        // Access Token מלוח הבקרה של MegaSend
define('MEGASEND_INSTANCE_ID', '');  // מזהה ה-Instance של חשבון ה-WhatsApp
```

### תזכורות אוטומטיות
```php
define('REMINDER_DAYS', [14, 3]);    // כמה ימים לפני החידוש לשלוח תזכורת
define('CRON_KEY', '');              // מפתח להרצת cron דרך כתובת (אם אין CLI)
```

---

## מבנה הקבצים
```
config.php          הגדרות (מסד נתונים, WhatsApp, cron)
install.php         התקנה ראשונית (למחיקה לאחר ההתקנה)
cron.php            שליחת תזכורות חידוש אוטומטית
login.php / forgot.php / reset.php / logout.php   התחברות ושחזור סיסמה
index.php           לוח בקרה
customers.php / customer_form.php / customer_view.php   לקוחות
debts.php / debt_pay.php           חובות ותשלומים
hosting.php / hosting_form.php      אחסון אתרים
send_whatsapp.php   שליחת תזכורת בוואטסאפ
admins.php          ניהול מנהלים
includes/           קוד פנימי (מוגן מגישה ישירה)
assets/             עיצוב, גופן (PingHL), לוגו
```

## אבטחה
- כל העמודים מאחורי התחברות; סיסמאות מוצפנות (password_hash).
- Prepared Statements מול מסד הנתונים; הגנת CSRF בטפסים.
- תיקיית includes וקובץ config חסומים מגישה ישירה (.htaccess).
- טוקן ה-WhatsApp נשמר בצד השרת בלבד.
