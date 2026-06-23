<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
ensure_admin_schema($pdo);

$error = '';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full = trim($_POST['full_name'] ?? '');
        $user = trim($_POST['username'] ?? '');
        $mail = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        if ($full === '' || $user === '' || $mail === '' || strlen($pass) < 6) {
            $error = 'יש למלא שם, שם משתמש, אימייל וסיסמה (לפחות 6 תווים).';
        } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $error = 'כתובת אימייל לא תקינה.';
        } else {
            $exists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
            $exists->execute([$user]);
            if ($exists->fetchColumn() > 0) {
                $error = 'שם המשתמש כבר קיים במערכת.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?,?,?,?)");
                $stmt->execute([$user, $mail, password_hash($pass, PASSWORD_DEFAULT), $full]);
                flash('המנהל נוסף בהצלחה.');
                header('Location: admins.php'); exit;
            }
        }
    }

    if ($action === 'setpass') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) {
            $error = 'הסיסמה חייבת להכיל לפחות 6 תווים.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
            flash('הסיסמה עודכנה.');
            header('Location: admins.php'); exit;
        }
    }

    if ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $total = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($uid === (int)$me['id']) {
            $error = 'לא ניתן למחוק את המשתמש שאיתו אתה מחובר.';
        } elseif ($total <= 1) {
            $error = 'לא ניתן למחוק את המנהל האחרון במערכת.';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            flash('המנהל נמחק.');
            header('Location: admins.php'); exit;
        }
    }
}

$admins = $pdo->query("SELECT id, username, email, full_name, created_at FROM users ORDER BY created_at ASC")->fetchAll();

$active = 'admins';
$page_title = 'מנהלים';
include __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?><div class="alert-box alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <div class="card-head"><h2>מנהלי המערכת</h2></div>
  <div class="card-body flush">
    <table class="data">
      <thead><tr><th>שם</th><th>שם משתמש</th><th>אימייל</th><th>נוצר</th><th class="t-left">פעולה</th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
        <tr>
          <td><strong><?= e($a['full_name']) ?></strong><?= $a['id']==$me['id'] ? ' <span class="badge badge-ok" style="vertical-align:middle"><span class="pip"></span>אתה</span>' : '' ?></td>
          <td class="num"><?= e($a['username']) ?></td>
          <td class="muted"><?= e($a['email'] ?: '—') ?></td>
          <td class="num nowrap muted"><?= fmt_date($a['created_at']) ?></td>
          <td class="t-left">
            <?php if ($a['id'] != $me['id']): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('למחוק את המנהל <?= e($a['full_name']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-danger-ghost btn-sm" type="submit">מחק</button>
              </form>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>הוספת מנהל חדש</h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="field"><label>שם מלא <span class="req">*</span></label><input type="text" name="full_name" required></div>
        <div class="field"><label>אימייל <span class="req">*</span></label><input type="email" name="email" required><div class="hint">ישמש לשחזור סיסמה</div></div>
        <div class="field"><label>שם משתמש <span class="req">*</span></label><input type="text" name="username" required autocomplete="off"></div>
        <div class="field"><label>סיסמה <span class="req">*</span></label><input type="password" name="password" required minlength="6" autocomplete="new-password"><div class="hint">לפחות 6 תווים</div></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit">הוסף מנהל</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>שינוי סיסמה למנהל</h2></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="setpass">
      <div class="form-grid">
        <div class="field"><label>בחר מנהל <span class="req">*</span></label>
          <select name="user_id" required>
            <?php foreach ($admins as $a): ?>
              <option value="<?= (int)$a['id'] ?>"><?= e($a['full_name']) ?> (<?= e($a['username']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>סיסמה חדשה <span class="req">*</span></label><input type="password" name="password" required minlength="6" autocomplete="new-password"></div>
      </div>
      <div class="form-actions"><button class="btn btn-ghost" type="submit">עדכן סיסמה</button></div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
