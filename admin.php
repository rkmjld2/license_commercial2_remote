<?php

/*
===========================================================
 LICENSE ADMIN V2
 Independent Start / End Date-Time
 CALENDAR + ACTUAL APPLICATION-USE
===========================================================
*/

date_default_timezone_set("Asia/Kolkata");

require_once 'db.php';

/*
 * Database session timezone = IST
 */
$conn->query("SET time_zone = '+05:30'");


$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? 'USER001';

$message = '';


/* =========================================================
   HANDLE BUTTON ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $license_mode =
        $_POST['license_mode'] ?? 'CALENDAR';


    /* =====================================================
       START / RESET LICENSE
    ===================================================== */

    if ($action === 'start') {

        /*
         * Start and End are selected independently.
         *
         * HTML datetime-local gives:
         *
         * YYYY-MM-DDTHH:MM
         */

        $start_input =
            trim($_POST['start_datetime'] ?? '');

        $end_input =
            trim($_POST['end_datetime'] ?? '');


        if ($start_input === '' || $end_input === '') {

            $message =
                "Please select both Start Date/Time and End Date/Time.";

        }
        else {

            /*
             * Convert T to space for MySQL DATETIME.
             */

            $started_at =
                str_replace('T', ' ', $start_input);

            $expires_at =
                str_replace('T', ' ', $end_input);


            /*
             * Convert selected dates to timestamps.
             *
             * PHP timezone is Asia/Kolkata.
             */

            $start_timestamp =
                strtotime($started_at);

            $end_timestamp =
                strtotime($expires_at);


            /*
             * Check date/time validity.
             */

            if (
                $start_timestamp === false ||
                $end_timestamp === false
            ) {

                $message =
                    "Invalid Start or End Date/Time.";

            }
            elseif ($end_timestamp <= $start_timestamp) {

                $message =
                    "End Date/Time must be later than Start Date/Time.";

            }
            else {

                /*
                 * Calculate total window duration.
                 *
                 * This is used for CALENDAR mode and
                 * also stored for information in USAGE mode.
                 */

                $duration_seconds =
                    $end_timestamp - $start_timestamp;


                /*
                 * START / RESET
                 *
                 * Both CALENDAR and USAGE now use
                 * independently selected Start/End.
                 */

                $stmt = $conn->prepare(
                    "UPDATE licenses
                     SET
                        status = 'ON',
                        license_mode = ?,
                        duration_seconds = ?,
                        used_seconds = 0,
                        started_at = ?,
                        expires_at = ?,
                        last_seen_at = NULL
                     WHERE user_id = ?"
                );


                if ($stmt) {

                    $stmt->bind_param(
                        "sisss",
                        $license_mode,
                        $duration_seconds,
                        $started_at,
                        $expires_at,
                        $user_id
                    );


                    if ($stmt->execute()) {

                        if ($license_mode === 'USAGE') {

                            $label =
                                'Actual application-use time';

                        }
                        else {

                            $label =
                                'Calendar time';
                        }


                        $message =
                            "$user_id started: $label from " .
                            $started_at .
                            " to " .
                            $expires_at .
                            ".";

                    }
                    else {

                        $message =
                            "Database update failed.";
                    }


                    $stmt->close();

                }
                else {

                    $message =
                        "Database statement error.";
                }
            }
        }
    }


    /* =====================================================
       ON BUTTON
    ===================================================== */

    elseif ($action === 'on') {

        /*
         * ON changes ONLY status.
         *
         * It does not change the selected
         * Start / End time.
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


        $message =
            "$user_id switched ON.";
    }


    /* =====================================================
       REMOTE OFF BUTTON
    ===================================================== */

    elseif ($action === 'off') {

        /*
         * OFF changes ONLY status.
         *
         * Usage is NOT reset.
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


        $message =
            "$user_id switched OFF.";
    }
}


/* =========================================================
   READ CURRENT LICENSE
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


$license =
    $stmt->get_result()->fetch_assoc();


$stmt->close();


/* User does not exist */

if (!$license) {

    die(
        "User ID not found: " .
        htmlspecialchars($user_id)
    );
}


$current_mode =
    $license['license_mode'];


/* =========================================================
   DEFAULT VALUES FOR DATETIME INPUTS
========================================================= */

$start_value = '';

$end_value = '';


if (!empty($license['started_at'])) {

    $start_value =
        date(
            'Y-m-d\TH:i',
            strtotime($license['started_at'])
        );
}


if (!empty($license['expires_at'])) {

    $end_value =
        date(
            'Y-m-d\TH:i',
            strtotime($license['expires_at'])
        );
}


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

        return
            ($seconds / 86400) .
            " d";
    }


    if (
        $seconds >= 3600 &&
        $seconds % 3600 === 0
    ) {

        return
            ($seconds / 3600) .
            " h";
    }


    return
        round(
            $seconds / 60,
            1
        ) .
        " min";
}


/* Display used time */

function show_used($seconds)
{
    return
        round(
            ((int)$seconds) / 60,
            1
        ) .
        " min";
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

    max-width: 1150px;

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


input[type="datetime-local"] {

    min-width: 220px;
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


.datetime-row {

    margin-top: 15px;

    margin-bottom: 15px;
}


.datetime-row label {

    font-weight: bold;

    margin-left: 10px;
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
    <?= $current_mode === 'CALENDAR'
        ? 'selected'
        : '' ?>
>
    Calendar time
</option>


<option
    value="USAGE"
    <?= $current_mode === 'USAGE'
        ? 'selected'
        : '' ?>
>
    Actual application-use time
</option>

</select>


<br>


<!-- =====================================================
     INDEPENDENT START DATE / TIME
===================================================== -->

<div class="datetime-row">

<label>
Start Date &amp; Time
</label>


<input
    type="datetime-local"
    name="start_datetime"
    value="<?= h($start_value) ?>"
    required
>

</div>


<!-- =====================================================
     INDEPENDENT END DATE / TIME
===================================================== -->

<div class="datetime-row">

<label>
End Date &amp; Time
</label>


<input
    type="datetime-local"
    name="end_datetime"
    value="<?= h($end_value) ?>"
    required
>

</div>


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

<th>Window</th>

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
