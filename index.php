<?php

/*
===========================================================
 MAIN APPLICATION
 index.php
===========================================================
*/


/* ---------------------------------------------------------
   CUSTOMER USER ID

   CHANGE THIS FOR EACH CUSTOMER.
--------------------------------------------------------- */

$user_id = "USER001";


/* ---------------------------------------------------------
   REMOTE LICENSE SERVER

   DO NOT CHANGE FOR THIS PROJECT.
--------------------------------------------------------- */

$license_url =
    "https://license-commercial-remote.onrender.com/license_check.php";


/* ---------------------------------------------------------
   LICENSE CHECK FUNCTION
--------------------------------------------------------- */

function check_license(
    $user_id,
    $license_url
) {

    $post_data =
        http_build_query([
            "user_id" => $user_id
        ]);


    $ch =
        curl_init($license_url);


    curl_setopt(
        $ch,
        CURLOPT_POST,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        $post_data
    );


    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_CONNECTTIMEOUT,
        10
    );


    curl_setopt(
        $ch,
        CURLOPT_TIMEOUT,
        20
    );


    curl_setopt(
        $ch,
        CURLOPT_FOLLOWLOCATION,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json"
        ]
    );


    $response =
        curl_exec($ch);


    if ($response === false) {

        $error =
            curl_error($ch);

        curl_close($ch);


        return [
            "success" => false,
            "status" => "OFF",
            "message" =>
                "License server could not be contacted: "
                . $error
        ];
    }


    $http_code =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    if (
        $http_code < 200 ||
        $http_code >= 300
    ) {

        return [
            "success" => false,
            "status" => "OFF",
            "message" =>
                "License checker returned HTTP "
                . $http_code
        ];
    }


    /* -----------------------------------------------------
       CLEAN RESPONSE
    ----------------------------------------------------- */

    $response =
        trim($response);


    /*
     * Remove UTF-8 BOM.
     */

    $response =
        preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $response
        );


    /*
     * Remove accidental Markdown fences.
     */

    $response =
        preg_replace(
            '/^```(?:json|php)?\s*/i',
            '',
            $response
        );


    $response =
        preg_replace(
            '/\s*```\s*$/',
            '',
            $response
        );


    $response =
        trim($response);


    /* -----------------------------------------------------
       DECODE JSON
    ----------------------------------------------------- */

    $data =
        json_decode(
            $response,
            true
        );


    if (!is_array($data)) {

        return [
            "success" => false,
            "status" => "OFF",
            "message" =>
                "Invalid response from license checker. "
                . "JSON error: "
                . json_last_error_msg()
                . " | HTTP: "
                . $http_code
                . " | RESPONSE: "
                . substr($response, 0, 500)
        ];
    }


    return $data;
}


/* ---------------------------------------------------------
   CHECK LICENSE
--------------------------------------------------------- */

$license =
    check_license(
        $user_id,
        $license_url
    );


/* ---------------------------------------------------------
   AUTHORIZATION
--------------------------------------------------------- */

$authorized =
    isset($license["status"]) &&
    $license["status"] === "ON";


/* ---------------------------------------------------------
   LICENSE INFORMATION
--------------------------------------------------------- */

$license_mode =
    $license["license_mode"] ?? "";


$used_seconds =
    (int)(
        $license["used_seconds"] ?? 0
    );


$remaining_seconds =
    $license["remaining_seconds"]
    ?? null;


/* ---------------------------------------------------------
   FORMAT TIME
--------------------------------------------------------- */

function format_time($seconds)
{

    if ($seconds === null) {
        return "-";
    }


    $seconds =
        max(
            0,
            (int)$seconds
        );


    $hours =
        floor(
            $seconds / 3600
        );


    $minutes =
        floor(
            ($seconds % 3600) / 60
        );


    $seconds =
        $seconds % 60;


    return sprintf(
        "%02d:%02d:%02d",
        $hours,
        $minutes,
        $seconds
    );
}


/* ---------------------------------------------------------
   HTML ESCAPE
--------------------------------------------------------- */

function h($value)
{

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>
<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<title>
My Demonstration Application
</title>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f2f2f2;
    text-align:center;
    margin:0;
    padding:30px;
}

.container{
    width:700px;
    max-width:95%;
    margin:auto;
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 0 10px #aaa;
}

.info{
    background:#f7f7f7;
    padding:15px;
    margin:20px 0;
    border-radius:8px;
    text-align:left;
}

.info p{
    margin:8px;
}

.running{
    color:green;
    font-size:26px;
    font-weight:bold;
}

.disabled{
    color:red;
    font-size:26px;
    font-weight:bold;
}

.disabled-box{
    border:2px solid red;
    padding:25px;
    border-radius:10px;
}

.menu{
    margin-top:25px;
}

.menu a{
    display:inline-block;
    padding:12px 20px;
    margin:8px;
    background:#eee;
    border:1px solid #ccc;
    border-radius:6px;
    text-decoration:none;
    color:black;
}

.menu a:hover{
    background:#ddd;
}

.small{
    color:#666;
    font-size:14px;
}

</style>

</head>

<body>

<div class="container">

<h1>
MY DEMONSTRATION APPLICATION
</h1>


<?php if ($authorized): ?>

<div class="running">
APPLICATION RUNNING
</div>


<div class="info">

<p>
<strong>User ID:</strong>
<?= h($user_id) ?>
</p>


<p>

<strong>License Mode:</strong>

<?php

if ($license_mode === "CALENDAR") {

    echo "CALENDAR TIME";

}
elseif ($license_mode === "USAGE") {

    echo "ACTUAL APPLICATION-USE TIME";

}
else {

    echo h($license_mode);

}

?>

</p>


<p>
<strong>Time Used:</strong>
<?= format_time($used_seconds) ?>
</p>


<p>
<strong>Time Remaining:</strong>
<?= format_time($remaining_seconds) ?>
</p>


<?php if ($license_mode === "CALENDAR"): ?>

<p>
<strong>Expiry:</strong>
<?= h($license["expires_at"] ?? "-") ?>
</p>

<?php endif; ?>

</div>


<hr>


<h2>
APPLICATION MENU
</h2>


<div class="menu">

<a href="payroll.php">
PAYROLL
</a>

<a href="controller.php">
CONTROLLER
</a>

<a href="myapplication.php">
MY APPLICATION
</a>

</div>


<p class="small">
License is checked automatically every 30 seconds.
</p>


<?php else: ?>


<div class="disabled-box">

<div class="disabled">
APPLICATION DISABLED
</div>


<p>
<strong>User ID:</strong>
<?= h($user_id) ?>
</p>


<p>
<?= h(
    $license["message"]
    ?? "Application is not authorized."
) ?>
</p>


<p>
The application cannot be started until the license becomes valid.
</p>

</div>


<?php endif; ?>

</div>


<script>

setTimeout(
    function () {

        location.reload();

    },
    30000
);

</script>


</body>

</html>
