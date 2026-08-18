<?php

/*
===========================================================
 LICENSE ADMIN V2
===========================================================
*/

date_default_timezone_set("Asia/Kolkata");

require_once 'db.php';

/*
 * Set database session timezone to Indian Standard Time.
 */
$conn->query("SET time_zone = '+05:30'");


$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? 'USER001';
$message = '';

/* =========================================================
   HANDLE BUTTON ACTIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
     * Mode is only needed for START / RESET.
     * For ON and REMOTE OFF we keep the existing database mode.
     */
    $license_mode = $_POST['license_mode'] ?? 'CALENDAR';

    $duration = max(1, (int)($_POST['duration'] ?? 1));
    $unit = $_POST['unit'] ?? 'HOURS';


    /* ---------------------------------------------------------
       Convert duration into seconds
       --------------------------------------------------------- */

    $multiplier = 3600;       // HOURS

    if ($unit === 'MINUTES') {
        $multiplier = 60;
    }
    elseif ($unit === 'DAYS') {
        $multiplier = 86400;
    }

    $duration_seconds = $duration * $multiplier;


    /* =========================================================
       START / RESET LICENSE
       ========================================================= */

    if ($action === 'start') {

        /*
         * CALENDAR MODE
         *
         * The expiry date/time is calculated now.
         */

        if ($license_mode === 'CALENDAR') {

            $stmt = $conn->prepare(
                "UPDATE licenses
                 SET
                    status = 'ON',
                    license_mode = ?,
                    duration_seconds = ?,
                    used_seconds = 0,
                    started_at = NOW(),
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    last_seen_at = NULL
                 WHERE user_id = ?"
            );

            $stmt->bind_param(
                "siis",
                $license_mode,
                $duration_seconds,
                $duration_seconds,
                $user_id
            );
        }


        /*
         * ACTUAL APPLICATION-USE MODE
         *
         * There is NO calendar expiry.
         * expires_at is NULL.
         */

        else {

            $stmt = $conn->prepare(
                "UPDATE licenses
                 SET
                    status = 'ON',
                    license_mode = ?,
                    duration_seconds = ?,
                    used_seconds = 0,
                    started_at = NOW(),
                    expires_at = NULL,
                    last_seen_at = NULL
                 WHERE user_id = ?"
            );

            $stmt->bind_param(
                "sis",
                $license_mode,
                $duration_seconds,
                $user_id
            );
        }


        $stmt->execute();
        $stmt->close();


        if ($license_mode === 'USAGE') {
            $label = 'Actual application-use';
        }
        else {
            $label = 'Calendar';
        }


        $message =
            "$user_id started: $label for " .
            $duration . " " .
            strtolower($unit) . ".";
    }


    /* =========================================================
       ON BUTTON
       ========================================================= */

    elseif ($action === 'on') {

        /*
         * IMPORTANT:
         *
         * ON changes ONLY the status.
         *
         * It does NOT change:
         *   license_mode
         *   used_seconds
         *   started_at
         *   expires_at
         *
         * Therefore a USAGE license remains USAGE.
         */

        $stmt = $conn->prepare(
            "UPDATE licenses
             SET status = 'ON'
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "s",
            $user_id
        );

        $stmt->execute();
        $stmt->close();

        $message = "$user_id switched ON.";
    }


    /* =========================================================
       REMOTE OFF BUTTON
       ========================================================= */

    elseif ($action === 'off') {

        /*
         * IMPORTANT:
         *
         * OFF changes ONLY the status.
         *
         * It does NOT reset usage time.
         */

        $stmt = $conn->prepare(
            "UPDATE licenses
             SET status = 'OFF'
             WHERE user_id = ?"
        );

        $stmt->bind_param(
            "s",
            $user_id
        );

        $stmt->execute();
        $stmt->close();

        $message = "$user_id switched OFF.";
    }
}


/* =========================================================
   READ CURRENT LICENSE FROM DATABASE
   ========================================================= */

$stmt = $conn->prepare(
    "SELECT *
     FROM licenses
     WHERE user_id = ?"
);

$stmt->bind_param(
    "s",
    $user_id
);

$stmt->execute();

$license = $stmt->get_result()->fetch_assoc();

$stmt->close();


/* User does not exist */

if (!$license) {

    die(
        "User ID not found: " .
        htmlspecialchars($user_id)
    );
}


/*
 * Database column:
 *
 *     license_mode
 */

$current_mode = $license['license_mode'];


/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* Display duration */

function show_duration($seconds)
{
    $seconds = (int)$seconds;


    if (
        $seconds >= 86400 &&
        $seconds % 86400 === 0
    ) {
        return ($seconds / 86400) . " d";
    }


    if (
        $seconds >= 3600 &&
        $seconds % 3600 === 0
    ) {
        return ($seconds / 3600) . " h";
    }


    return round(
        $seconds / 60,
        1
    ) . " min";
}


/* Display used time */

function show_used($seconds)
{
    return round(
        ((int)$seconds) / 60,
        1
    ) . " min";
}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>License Admin V2</title>


<style>

body {
    font-family: Arial, sans-serif;
    text-align: center;
    margin: 30px;
}

.box {
    max-width: 1100px;
    margin: auto;

    border: 1px solid #ccc;

    border-radius: 10px;

    padding: 20px;
}

input,
select,
button {

    padding: 9px;

    margin: 5px;
}

button {

    cursor: pointer;
}

table {

    border-collapse: collapse;

    width: 100%;

    margin-top: 20px;
}

th,
td {

    border: 1px solid #ccc;

    padding: 10px;
}

th {

    background: #f2f2f2;
}

.message {

    margin: 15px;

    padding: 10px;

    background: #eef7ee;

    border: 1px solid #b7d7b7;

    border-radius: 5px;
}

</style>

</head>


<body>


<h1>License Control Panel — V2</h1>


<div class="box">


<form method="post">


<!-- =====================================================
     USER ID
     ===================================================== -->

<label>
USER_ID
</label>


<input
    type="text"
    name="user_id"
    value="<?= h($user_id) ?>"
>


<!-- =====================================================
     LICENSE MODE
     ===================================================== -->

<label>
Mode
</label>


<select name="license_mode">


<option
    value="CALENDAR"
    <?= $current_mode === 'CALENDAR' ? 'selected' : '' ?>
>
    Calendar time
</option>


<option
    value="USAGE"
    <?= $current_mode === 'USAGE' ? 'selected' : '' ?>
>
    Actual application-use time
</option>


</select>


<!-- =====================================================
     DURATION
     ===================================================== -->

<label>
Duration
</label>


<input
    type="number"
    name="duration"
    value="1"
    min="1"
>


<select name="unit">


<option value="HOURS">
    Hours
</option>


<option value="MINUTES">
    Minutes
</option>


<option value="DAYS">
    Days
</option>


</select>


<br>


<!-- =====================================================
     START / RESET
     ===================================================== -->

<button
    type="submit"
    name="action"
    value="start"
>
    START / RESET LICENSE
</button>


<!-- =====================================================
     ON
     ===================================================== -->

<button
    type="submit"
    name="action"
    value="on"
>
    ON
</button>


<!-- =====================================================
     REMOTE OFF
     ===================================================== -->

<button
    type="submit"
    name="action"
    value="off"
>
    REMOTE OFF
</button>


</form>


<!-- =====================================================
     MESSAGE
     ===================================================== -->

<?php if ($message): ?>

<div class="message">

<strong>
<?= h($message) ?>
</strong>

</div>

<?php endif; ?>


<!-- =====================================================
     LICENSE INFORMATION
     ===================================================== -->

<table>


<tr>

<th>User</th>

<th>Status</th>

<th>Mode</th>

<th>Duration</th>

<th>Used</th>

<th>Started</th>

<th>Expires</th>

<th>Last Check</th>

</tr>


<tr>


<td>
<?= h($license['user_id']) ?>
</td>


<td>
<?= h($license['status']) ?>
</td>


<td>
<?= h($license['license_mode']) ?>
</td>


<td>
<?= h(
    show_duration(
        $license['duration_seconds']
    )
) ?>
</td>


<td>
<?= h(
    show_used(
        $license['used_seconds']
    )
) ?>
</td>


<td>
<?= h(
    $license['started_at']
    ?: '-'
) ?>
</td>


<td>
<?= h(
    $license['expires_at']
    ?: '-'
) ?>
</td>


<td>
<?= h(
    $license['last_seen_at']
    ?: '-'
) ?>
</td>


</tr>


</table>


</div>


</body>

</html>
