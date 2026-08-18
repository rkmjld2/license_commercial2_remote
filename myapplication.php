<?php
require_once __DIR__ . "/license_guard.php";
$message="";
if($_SERVER["REQUEST_METHOD"]==="POST"){
 $option=$_POST["option"]??"";
 $message=$option?"Selected: $option":"Please select an operation.";
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>My Application</title>
<style>body{font-family:Arial;text-align:center;background:#f2f2f2;padding:30px}.box{max-width:600px;margin:auto;background:white;padding:25px;border-radius:12px;box-shadow:0 0 10px #aaa}select,button{padding:10px;margin:8px}a{display:block;margin:20px}</style></head>
<body><div class="box"><h1>MY APPLICATION</h1><p>Real Application Demonstration</p>
<form method="post"><select name="option"><option value="">Select Operation</option><option>Start Process</option><option>Stop Process</option><option>View Status</option></select><button>EXECUTE</button></form>
<?php if($message): ?><p><strong><?=htmlspecialchars($message)?></strong></p><?php endif; ?>
<a href="index.php">Back to Main Application</a></div></body></html>
