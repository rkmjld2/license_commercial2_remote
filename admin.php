<?php

/*
===========================================================
 LICENSE ADMIN V3
===========================================================
*/

date_default_timezone_set("Asia/Kolkata");

require_once 'db.php';

/*
 * Database session timezone = IST
 */
$conn->query("SET time_zone = '+05:30'");


$user_id = $_POST['user_id']
    ?? $_GET['user_id']
    ?? 'USER001';

$message = '';


/* =========================================================
   HANDLE BUTTON ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $license_mode =
        $_POST['license_mode'] ?? 'CALENDAR';


    /* =====================================================
       START / SAVE LICENSE
    ===================================================== */

    if ($action === 'start') {

        /*
         * -------------------------------------------------
         * CALENDAR MODE
         * -------------------------------------------------
         */

        if ($license_mode === 'CALENDAR') {

            $start_date =
                trim($_POST['start_date'] ?? '');

            $start_time =
                trim($_POST['start_time'] ?? '');

            $end_date =
                trim($_POST['end_date'] ?? '');

            $end_time =
                trim($_POST['end_time'] ?? '');


            /*
             * Check that all four fields are supplied.
             */

            if (
                $start_date === '' ||
                $start_time === '' ||
                $end_date === '' ||
                $end_time === ''
            ) {

                $message =
                    "Please select Start Date, Start Time, "
                    . "End Date and End Time.";

            } else {

                /*
                 * Convert HTML date/time values
                 * into MySQL DATETIME.
                 */

                $started_at =
                    $start_date . ' ' . $start_time . ':00';

                $expires_at =
                    $end_date . ' ' . $end_time . ':00';


                /*
                 * Validate dates using PHP/IST.
                 */

                $start_timestamp =
                    strtotime($started_at);

                $end_timestamp =
                    strtotime($expires_at);


                if (
                    $start_timestamp === false ||
                    $end_timestamp === false
                ) {

                    $message =
                        "Invalid date or time selected.";

                }
                elseif ($end_timestamp <= $start_timestamp) {

                    $message =
                        "End date/time must be later than "
                        . "Start date/time.";

                }
                else {

                    /*
                     * Calculate total calendar duration.
                     *
                     * This is stored in duration_seconds
                     * for information only.
                     */

                    $duration_seconds =
                        $end_timestamp - $start_timestamp;


                    $stmt = $conn->prepare(
                        "UPDATE licenses
                         SET
                            status = 'ON',
                            license_mode = 'CALENDAR',
                            duration_seconds = ?,
                            used_seconds = 0,
                            started_at = ?,
                            expires_at = ?,
                            last_seen_at = NULL
                         WHERE user_id = ?"
                    );


                    $stmt->bind_param(
                        "isss",
                        $duration_seconds,
                        $started_at,
                        $expires_at,
                        $user_id
                    );


                    $stmt->execute();

                    $stmt->close();


                    $message =
                        "$user_id calendar license saved. "
                        . "Start: "
                        . $started_at
                        . " | End: "
                        . $expires_at;
                }
            }
        }


        /*
         * -------------------------------------------------
         * ACTUAL APPLICATION-USE MODE
         * -------------------------------------------------
         */

        else {

            $duration =
                max(
                    1,
                    (int)(
                        $_POST['duration'] ?? 1
                    )
                );


            $unit =
                $_POST['unit'] ?? 'HOURS';


            $multiplier = 3600;


            if ($unit === 'MINUTES') {

                $multiplier = 60;

            }
            elseif ($unit === 'DAYS') {

                $multiplier = 86400;
            }


            $duration_seconds =
                $duration * $multiplier;


            $stmt = $conn->prepare(
                "UPDATE licenses
                 SET
                    status = 'ON',
                    license_mode = 'USAGE',
                    duration_seconds = ?,
                    used_seconds = 0,
                    started_at = NOW(),
                    expires_at = NULL,
                    last_seen_at = NULL
                 WHERE user_id = ?"
            );


            $stmt->bind_param(
                "is",
                $duration_seconds,
                $user_id
            );


            $stmt->execute();

            $stmt->close();


            $message =
                "$user_id started: Actual "
                . "application-use for "
                . $duration . " "
                . strtolower($unit) . ".";
        }
    }


    /* =====================================================
       ON BUTTON
    ===================================================== */

    elseif ($action === 'on') {

        /*
         * ON changes ONLY status.
         *
         * Existing dates and usage are preserved.
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
         * Dates and usage are preserved.
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


/* =========================================================
   USER DOES NOT EXIST
========================================================= */

if (!$license) {

    die(
        "User ID not found: "
        . htmlspecialchars($user_id)
    );
}


$current_mode =
    $license['license_mode'];


/* =========================================================
   HELPER
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   DISPLAY DURATION
========================================================= */

function show_duration($seconds)
{
    $seconds = (int)$seconds;


    if (
        $seconds >= 86400 &&
        $seconds % 86400 === 0
    ) {

        return
            ($seconds / 86400)
            . " d";
    }


    if (
        $seconds >= 3600 &&
        $seconds % 3600 === 0
    ) {

        return
            ($seconds / 3600)
            . " h";
    }


    return
        round(
            $seconds / 60,
            1
        )
        . " min";
}


/* =========================================================
   DISPLAY USED TIME
========================================================= */

function show_used($seconds)
{
    return
        round(
            ((int)$seconds) / 60,
            1
        )
        . " min";
}


/* =========================================================
   HTML DATE/TIME VALUES
========================================================= */

$start_date_value = '';

$start_time_value = '';

$end_date_value = '';

$end_time_value = '';


if (
    $current_mode === 'CALENDAR'
) {

    if (!empty($license['started_at'])) {

        $start_timestamp =
            strtotime(
                $license['started_at']
            );


        if ($start_timestamp !== false) {

            $start_date_value =
                date(
                    'Y-m-d',
                    $start_timestamp
                );

            $start_time_value =
                date(
                    'H:i',
                    $start_timestamp
                );
        }
    }


    if (!empty($license['expires_at'])) {

        $end_timestamp =
            strtotime(
                $license['expires_at']
            );


        if ($end_timestamp !== false) {

            $end_date_value =
                date(
                    'Y-m-d',
                    $end_timestamp
                );

            $end_time_value =
                date(
                    'H:i',
                    $end_timestamp
                );
        }
    }
}

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>
License Control Panel — V3
</title>


<style>

body {

    font-family: Arial, sans-serif;

    text-align: center;

    margin: 30px;

    background: #f7f7f7;
}


.box {

    max-width: 1100px;

    margin: auto;

    background: white;

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


input[type="date"],
input[type="time"] {

    min-width: 145px;
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


.calendar-box {

    margin: 20px auto;

    padding: 15px;

    border: 1px solid #ccc;

    border-radius: 8px;

    max-width: 800px;

    background: #fafafa;
}


.calendar-row {

    margin: 10px;
}


.calendar-row label {

    display: inline-block;

    width: 110px;

    font-weight: bold;

    text-align: right;

    margin-right: 8px;
}


#usage-box {

    margin: 15px;
}


.note {

    color: #555;

    font-size: 14px;

    margin: 10px;
}

</style>


<script>

function updateModeFields()
{
    var mode =
        document.getElementById(
            "license_mode"
        ).value;


    var calendarBox =
        document.getElementById(
            "calendar-box"
        );


    var usageBox =
        document.getElementById(
            "usage-box"
        );


    if (mode === "CALENDAR") {

        calendarBox.style.display =
            "block";

        usageBox.style.display =
            "none";

    } else {

        calendarBox.style.display =
            "none";

        usageBox.style.display =
            "block";
    }
}

</script>

</head>


<body onload="updateModeFields()">


<h1>
License Control Panel — V3
</h1>


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
    required
>


<!-- =====================================================
     LICENSE MODE
===================================================== -->

<label>
Mode
</label>


<select
    name="license_mode"
    id="license_mode"
    onchange="updateModeFields()"
>


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


<!-- =====================================================
     CALENDAR SETTINGS
===================================================== -->

<div
    id="calendar-box"
    class="calendar-box"
>


<h3>
Calendar License Period
</h3>


<div class="calendar-row">

<label>
Start Date:
</label>


<input
    type="date"
    name="start_date"
    value="<?= h($start_date_value) ?>"
>


<label>
Start Time:
</label>


<input
    type="time"
    name="start_time"
    value="<?= h($start_time_value) ?>"
>

</div>


<div class="calendar-row">

<label>
End Date:
</label>


<input
    type="date"
    name="end_date"
    value="<?= h($end_date_value) ?>"
>


<label>
End Time:
</label>


<input
    type="time"
    name="end_time"
    value="<?= h($end_time_value) ?>"
>

</div>


<div class="note">

Select the exact date and time when the
license should start and end.

All times are India Standard Time (IST).

</div>

</div>


<!-- =====================================================
     USAGE SETTINGS
===================================================== -->

<div
    id="usage-box"
>


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


<div class="note">

Actual application-use time is counted only
while the application is actively checking
the license.

</div>

</div>


<br>


<!-- =====================================================
     START / SAVE
===================================================== -->

<button
    type="submit"
    name="action"
    value="start"
>

START / SAVE LICENSE

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
