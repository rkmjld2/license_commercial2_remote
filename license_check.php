<?php

date_default_timezone_set("Asia/Kolkata");

/*
===========================================================
 COMMERCIAL LICENSE SERVER
 license_check.php

 VERSION 3

 Supports:

 1. CALENDAR TIME
    Independently selected START date/time
    Independently selected END date/time

 2. ACTUAL APPLICATION-USE TIME

 All PHP/database times are handled in IST.
===========================================================
*/


ob_start();

ini_set("display_errors", "0");

error_reporting(E_ALL);

header(
    "Content-Type: application/json; charset=UTF-8"
);


/* =========================================================
   LOAD DATABASE
========================================================= */

require_once "db.php";


/*
 * Database connection timezone = IST.
 */
$conn->query(
    "SET time_zone = '+05:30'"
);


/* =========================================================
   JSON RESPONSE FUNCTION
========================================================= */

function send_json($data)
{
    while (ob_get_level() > 0) {

        ob_end_clean();
    }


    header(
        "Content-Type: application/json; charset=UTF-8"
    );


    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES
    );


    exit;
}


/* =========================================================
   DATABASE CONNECTION CHECK
========================================================= */

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

    send_json([

        "success" => false,

        "status" => "OFF",

        "message" =>
            "Database connection is not available."
    ]);
}


/* =========================================================
   USER ID
========================================================= */

$user_id =
    trim(
        $_POST["user_id"] ?? ""
    );


if ($user_id === "") {

    send_json([

        "success" => false,

        "status" => "OFF",

        "message" =>
            "Missing USER_ID"
    ]);
}


/* =========================================================
   FIND LICENSE
========================================================= */

$stmt = $conn->prepare(

    "SELECT
        id,
        user_id,
        status,
        license_mode,
        duration_seconds,
        started_at,
        expires_at,
        used_seconds,
        last_seen_at,
        updated_at

     FROM licenses

     WHERE user_id = ?

     LIMIT 1"
);


if (!$stmt) {

    send_json([

        "success" => false,

        "status" => "OFF",

        "message" =>
            "Database statement error."
    ]);
}


$stmt->bind_param(
    "s",
    $user_id
);


if (!$stmt->execute()) {

    $stmt->close();


    send_json([

        "success" => false,

        "status" => "OFF",

        "message" =>
            "Database query failed."
    ]);
}


$result =
    $stmt->get_result();


/* =========================================================
   UNKNOWN USER
========================================================= */

if ($result->num_rows === 0) {

    $stmt->close();


    send_json([

        "success" => false,

        "status" => "OFF",

        "message" =>
            "Unknown USER_ID"
    ]);
}


$row =
    $result->fetch_assoc();


$stmt->close();


/* =========================================================
   LICENSE VALUES
========================================================= */

$db_status =
    strtoupper(
        trim(
            (string)$row["status"]
        )
    );


$mode =
    strtoupper(
        trim(
            (string)$row["license_mode"]
        )
    );


$duration =
    (int)$row["duration_seconds"];


$used =
    (int)$row["used_seconds"];


/*
 * Current real time.
 *
 * time() is timezone-independent Unix time.
 */
$now =
    time();


/* =========================================================
   FINAL STATUS
========================================================= */

$status = "OFF";


/* =========================================================
   CALENDAR MODE
========================================================= */

if ($mode === "CALENDAR") {


    /*
     * We need both start and end.
     */

    $started_timestamp = false;

    $expires_timestamp = false;


    if (!empty($row["started_at"])) {

        $started_timestamp =
            strtotime(
                $row["started_at"]
            );
    }


    if (!empty($row["expires_at"])) {

        $expires_timestamp =
            strtotime(
                $row["expires_at"]
            );
    }


    /*
     * -----------------------------------------------------
     * INVALID SCHEDULE
     * -----------------------------------------------------
     */

    if (
        $started_timestamp === false ||
        $expires_timestamp === false
    ) {

        $status = "OFF";

        $message =
            "Calendar start/end time is not configured.";
    }


    /*
     * -----------------------------------------------------
     * BEFORE START
     * -----------------------------------------------------
     */

    elseif ($now < $started_timestamp) {

        /*
         * License has been scheduled but
         * its start time has not arrived.
         */

        $status = "OFF";

        $message =
            "License scheduled to start at "
            . date(
                "Y-m-d H:i:s",
                $started_timestamp
            );
    }


    /*
     * -----------------------------------------------------
     * ACTIVE PERIOD
     * -----------------------------------------------------
     */

    elseif (
        $now >= $started_timestamp &&
        $now < $expires_timestamp &&
        $db_status === "ON"
    ) {

        $status = "ON";

        $message =
            "Application authorized";


        /*
         * Record last license check.
         */

        $stmt2 =
            $conn->prepare(

                "UPDATE licenses

                 SET last_seen_at = NOW()

                 WHERE user_id = ?"
            );


        if ($stmt2) {

            $stmt2->bind_param(
                "s",
                $user_id
            );

            $stmt2->execute();

            $stmt2->close();
        }
    }


    /*
     * -----------------------------------------------------
     * END TIME REACHED
     * -----------------------------------------------------
     */

    elseif (
        $now >= $expires_timestamp
    ) {

        $status = "OFF";

        $message =
            "License expired";


        /*
         * Automatically turn license OFF.
         */

        $stmt2 =
            $conn->prepare(

                "UPDATE licenses

                 SET
                    status = 'OFF',
                    last_seen_at = NOW()

                 WHERE user_id = ?"
            );


        if ($stmt2) {

            $stmt2->bind_param(
                "s",
                $user_id
            );

            $stmt2->execute();

            $stmt2->close();
        }
    }


    /*
     * -----------------------------------------------------
     * REMOTE OFF
     * -----------------------------------------------------
     */

    elseif ($db_status !== "ON") {

        $status = "OFF";

        $message =
            "Application disabled";
    }
}


/* =========================================================
   ACTUAL APPLICATION-USE MODE
========================================================= */

elseif ($mode === "USAGE") {


    /*
     * The license must be ON.
     */

    if ($db_status === "ON") {


        $delta = null;


        /*
         * Calculate time since previous check.
         */

        if (!empty($row["last_seen_at"])) {

            $last_seen_timestamp =
                strtotime(
                    $row["last_seen_at"]
                );


            if (
                $last_seen_timestamp !== false
            ) {

                $delta =
                    $now -
                    $last_seen_timestamp;
            }
        }


        /*
         * Count reasonable active-use intervals.
         */

        if (
            $delta !== null &&
            $delta > 0 &&
            $delta <= 120
        ) {

            $used += $delta;
        }


        /*
         * Maximum duration reached.
         */

        if ($used >= $duration) {

            $used =
                $duration;

            $status =
                "OFF";


            $stmt2 =
                $conn->prepare(

                    "UPDATE licenses

                     SET
                        status = 'OFF',
                        used_seconds = ?,
                        last_seen_at = NOW()

                     WHERE user_id = ?"
                );


            if ($stmt2) {

                $stmt2->bind_param(
                    "is",
                    $used,
                    $user_id
                );

                $stmt2->execute();

                $stmt2->close();
            }


            $message =
                "Application-use time exhausted.";
        }


        /*
         * Still available.
         */

        else {

            $status =
                "ON";


            $stmt2 =
                $conn->prepare(

                    "UPDATE licenses

                     SET
                        used_seconds = ?,
                        last_seen_at = NOW()

                     WHERE user_id = ?"
                );


            if ($stmt2) {

                $stmt2->bind_param(
                    "is",
                    $used,
                    $user_id
                );

                $stmt2->execute();

                $stmt2->close();
            }


            $message =
                "Application authorized";
        }
    }


    /*
     * Remote OFF.
     */

    else {

        $status =
            "OFF";


        $message =
            "Application disabled";
    }
}


/* =========================================================
   UNKNOWN MODE
========================================================= */

else {

    $status =
        "OFF";


    $message =
        "Invalid license mode.";
}


/* =========================================================
   CALCULATE REMAINING TIME
========================================================= */

$remaining = null;


/* ---------------------------------------------------------
   CALENDAR
--------------------------------------------------------- */

if (
    $status === "ON" &&
    $mode === "CALENDAR"
) {

    if (
        $expires_timestamp !== false
    ) {

        $remaining =
            max(
                0,
                $expires_timestamp -
                $now
            );
    }
}


/* ---------------------------------------------------------
   USAGE
--------------------------------------------------------- */

elseif (
    $status === "ON" &&
    $mode === "USAGE"
) {

    $remaining =
        max(
            0,
            $duration -
            $used
        );
}


/* =========================================================
   FINAL JSON
========================================================= */

send_json([

    "success" => true,

    "user_id" =>
        $user_id,

    "status" =>
        $status,

    "license_mode" =>
        $mode,

    "remaining_seconds" =>
        $remaining,

    "used_seconds" =>
        $used,

    "duration_seconds" =>
        $duration,

    "started_at" =>
        $row["started_at"],

    "expires_at" =>
        $row["expires_at"],

    "server_time" =>
        date(
            "Y-m-d H:i:s"
        ),

    "message" =>
        $message
]);

?>
