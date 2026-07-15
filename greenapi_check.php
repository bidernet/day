<?php
/**
 * כלי בדיקת חיבור WhatsApp (GREEN API) — קובץ זמני לאבחון.
 * העלה לצד config.php, גלוש אל: https://day.bidernet.co.il/greenapi_check.php
 * לאחר שהכול עובד — מחק את הקובץ.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');

function ga_call($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20]);
    $r = curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $e=curl_error($ch); curl_close($ch);
    return ['code'=>$code,'body'=>$r,'json'=>json_decode($r,true),'err'=>$e];
}
function ga_post($url,$payload){
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>25]);
    $r=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$e=curl_error($ch);curl_close($ch);
    return ['code'=>$code,'body'=>$r,'json'=>json_decode($r,true),'err'=>$e];
}
$id = defined('GREENAPI_ID') ? GREENAPI_ID : '';
$token = defined('GREENAPI_TOKEN') ? GREENAPI_TOKEN : '';
$api = defined('GREENAPI_API_URL') ? rtrim(GREENAPI_API_URL,'/') : 'https://api.green-api.com';

function row($l,$v,$ok=null){$c=$ok===true?'#1aa256':($ok===false?'#e23b32':'#555');$m=$ok===true?'✓ ':($ok===false?'✗ ':'');
  echo '<tr><td style="padding:8px 14px;border-bottom:1px solid #eee;font-weight:600">'.htmlspecialchars($l).'</td><td style="padding:8px 14px;border-bottom:1px solid #eee;color:'.$c.'">'.$m.nl2br(htmlspecialchars($v)).'</td></tr>';}
?><!DOCTYPE html><html lang="he" dir="rtl"><head><meta charset="utf-8"><title>בדיקת GREEN API</title>
<style>body{font-family:system-ui,Arial,sans-serif;background:#f5f7f9;padding:24px;color:#181b1d}.box{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e2e8e6;border-radius:14px;overflow:hidden}h1{font-size:20px;margin:0;padding:18px 20px;background:#181b1d;color:#fff}h2{font-size:16px;margin:0;padding:14px 20px;background:#fafbfc;border-bottom:1px solid #eee}table{width:100%;border-collapse:collapse;font-size:14px}code{background:#f0f2f4;padding:2px 6px;border-radius:5px;direction:ltr;display:inline-block}.lime{background:#c6f02e;color:#14180b;padding:10px 14px;border-radius:8px;font-weight:700;border:none;cursor:pointer}.note{padding:16px 20px;font-size:14px;line-height:1.7}</style></head><body><div class="box">
<h1>בדיקת חיבור WhatsApp · GREEN API</h1>

<h2>1. הערכים ב-config.php</h2>
<table>
<?php
row('idInstance מוגדר?', $id!=='' ? $id : 'לא — ריק!', $id!=='');
row('apiTokenInstance מוגדר?', $token!=='' ? (substr($token,0,6).'… ('.strlen($token).' תווים)') : 'לא — ריק!', $token!=='');
?>
</table>

<?php if($id!=='' && $token!==''):
$state = ga_call("$api/waInstance$id/getStateInstance/$token");
?>
<h2>2. מצב החיבור (getStateInstance)</h2>
<table>
<?php
if($state['code']===200 && is_array($state['json']) && isset($state['json']['stateInstance'])){
    $st=$state['json']['stateInstance'];
    $authorized = ($st==='authorized');
    row('סטטוס Instance', $st, $authorized);
    if(!$authorized) row('משמעות', 'ה-Instance לא מחובר לוואטסאפ. היכנס ל-green-api.com, סרוק QR וחבר את המספר. סטטוס תקין = authorized.', false);
} else {
    row('שגיאה', 'קוד '.$state['code'], false);
    row('תשובת השרת', $state['body'] ?: $state['err']);
    row('משמעות', 'ה-idInstance או ה-Token כנראה שגויים. ודא שהעתקת אותם נכון מלוח הבקרה.', false);
}
?>
</table>

<h2>3. שליחת הודעת בדיקה</h2>
<div class="note">
  <form method="post">שלח בדיקה למספר (למשל 0501234567):<br><br>
  <input type="text" name="test_phone" placeholder="0501234567" style="padding:8px;border:1px solid #ccc;border-radius:6px;direction:ltr;width:200px">
  <button class="lime" type="submit">שלח בדיקה</button></form>
</div>
<?php if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['test_phone'])):
  $d=preg_replace('/\D+/','',$_POST['test_phone']); if(strpos($d,'0')===0)$d='972'.substr($d,1);
  $send=ga_post("$api/waInstance$id/sendMessage/$token", ['chatId'=>$d.'@c.us','message'=>'בדיקת חיבור bidernet ✅']);
?>
<table>
<?php
if($send['code']>=200 && $send['code']<300 && is_array($send['json']) && !empty($send['json']['idMessage'])){
    row('תוצאה','נשלח בהצלחה! בדוק את הוואטסאפ. (idMessage: '.$send['json']['idMessage'].')', true);
}else{
    row('תוצאה','נכשל — קוד '.$send['code'], false);
    row('תשובת השרת', $send['body'] ?: $send['err']);
}
?>
</table>
<?php endif; endif; ?>

<div class="note" style="background:#fbf2e0"><strong>חשוב:</strong> קובץ אבחון זמני. לאחר שהשליחה עובדת — מחק את <code>greenapi_check.php</code> מהשרת.</div>
</div></body></html>
