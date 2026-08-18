<?php
require_once __DIR__ . "/license_guard.php";
$message="";
if($_SERVER["REQUEST_METHOD"]==="POST"){
 $device=$_POST["device"]??""; $action=$_POST["action"]??"";
 $message=($device&&$action)?"Command accepted: $action for $device":"Select device and action.";
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Controller</title>
<style>body{font-family:Arial;text-align:center;background:#f2f2f2;padding:30px}.box{max-width:600px;margin:auto;background:white;padding:25px;border-radius:12px;box-shadow:0 0 10px #aaa}select,button{padding:10px;margin:8px}a{display:block;margin:20px}</style></head>
<body><div class="box"><h1>CONTROLLER</h1><p>Real Application Demonstration</p>
<form method="post"><select name="device"><option value="">Select Device</option><option>DEVICE-01</option><option>DEVICE-02</option><option>DEVICE-03</option></select><br>
<button name="action" value="ON">ON</button><button name="action" value="OFF">OFF</button></form>
<?php if($message): ?><p><strong><?=htmlspecialchars($message)?></strong></p><?php endif; ?>
<a href="index.php">Back to Main Application</a></div></body></html>
