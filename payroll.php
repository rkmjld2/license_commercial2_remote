<?php
require_once __DIR__ . "/license_guard.php";

$basic = 0;
$da = 0;
$da_amount = 0;
$total = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $basic = (float)($_POST["basic"] ?? 0);
    $da = (float)($_POST["da"] ?? 0);

    $da_amount = $basic * $da / 100;
    $total = $basic + $da_amount;
}
?>
<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Payroll</title>

<style>

body{
    font-family:Arial;
    text-align:center;
    background:#f2f2f2;
    padding:30px;
}

.box{
    max-width:600px;
    margin:auto;
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 0 10px #aaa;
}

input,button{
    padding:9px;
    margin:7px;
}

button{
    cursor:pointer;
}

a{
    display:block;
    margin:20px;
}

</style>

</head>

<body>

<div class="box">

<h1>PAYROLL</h1>

<p>Real Application Demonstration</p>

<form method="post">

Basic Pay<br>

<input
    type="number"
    step="0.01"
    name="basic"
    required
>

<br>

DA %<br>

<input
    type="number"
    step="0.01"
    name="da"
    required
>

<br>

<button type="submit">
CALCULATE PAY
</button>

</form>

<?php if ($_SERVER["REQUEST_METHOD"] === "POST"): ?>

<hr>

<p>
<strong>Basic Pay:</strong>
<?= number_format($basic, 2) ?>
</p>

<p>
<strong>DA Amount:</strong>
<?= number_format($da_amount, 2) ?>
</p>

<h3>
Total Pay:
<?= number_format($total, 2) ?>
</h3>

<?php endif; ?>

<a href="index.php">
Back to Main Application
</a>

</div>

</body>
</html>

