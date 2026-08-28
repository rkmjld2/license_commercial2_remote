```php
<?php

date_default_timezone_set("Asia/Kolkata");

/*
===========================================================
 COMMERCIAL LICENSE SERVER
 license_check.php

 VERSION 4

 Supports:

 1. CALENDAR
    License is valid between started_at and expires_at.

 2. USAGE
    Actual application-use time is counted using used_seconds.

 IMPORTANT:
    expires_at is a HARD EXPIRY for BOTH modes.

    Therefore:
       CALENDAR + expired = OFF
       USAGE    + expired = OFF

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


/* =========================================================
   DATABASE TIMEZONE
========================================================= */

$conn->query(
    "SET time_zone = '+05:30'"
);


/* =========================================================
   JSON RESPONSE
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
   DATABASE CHECK
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

if (
    !$result ||
    $result->num_rows === 0
) {

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
    max(
        0,
        (int)$row["duration_seconds"]
    );


$used =
    max(
        0,
        (int)$row["used_seconds"]
    );


$now =
    time();


/* =========================================================
   START / END TIMESTAMPS
========================================================= */

$started_timestamp = false;

$expires_timestamp = false;


if (
    !empty($row["started_at"])
) {

    $started_timestamp =
        strtotime(
            $row["started_at"]
        );
}


if (
    !empty($row["expires_at"])
) {

    $expires_timestamp =
        strtotime(
            $row["expires_at"]
        );
}


/* =========================================================
   DEFAULT VALUES
========================================================= */

$status = "OFF";

$message = "Application disabled";

$remaining = 0;


/* =========================================================
   BASIC DATE VALIDATION
========================================================= */

if (
    $started_timestamp === false ||
    $expires_timestamp === false
) {

    $status = "OFF";

    $message =
        "License start/end time is not configured.";

}


/* =========================================================
   HARD EXPIRY
   ========================================================

   THIS IS THE IMPORTANT FIX.

   expires_at is checked BEFORE CALENDAR OR USAGE.

   If current time has reached expires_at,
   license is OFF regardless of database status.

========================================================= */

elseif (
    $now >= $expires_timestamp
) {

    $status = "OFF";

    $remaining = 0;

    $message =
        "License expired.";


    /*
     * Permanently switch database status OFF.
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


/* =========================================================
   BEFORE START
========================================================= */

elseif (
    $now < $started_timestamp
) {

    $status = "OFF";

    $remaining =
        0;

    $message =
        "License has not started yet.";

}


/* =========================================================
   ACTIVE LICENSE
========================================================= */

else {

    /* =====================================================
       CALENDAR MODE
    ===================================================== */

    if (
        $mode === "CALENDAR"
    ) {

        /*
         * Calendar mode uses real calendar time.
         */

        if (
            $db_status === "ON"
        ) {

            $status = "ON";

            $remaining =
                max(
                    0,
                    $expires_timestamp - $now
                );

            $message =
                "Application authorized";


            /*
             * Record last check.
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
        else {

            $status = "OFF";

            $remaining = 0;

            $message =
                "Application disabled.";
        }
    }


    /* =====================================================
       USAGE MODE
    ===================================================== */

    elseif (
        $mode === "USAGE"
    ) {

        /*
         * Remote administrator must have license ON.
         */

        if (
            $db_status !== "ON"
        ) {

            $status =
                "OFF";

            $remaining =
                0;

            $message =
                "Application disabled.";

        }

        else {

            /*
             * Calculate application-use time.
             */

            $delta = 0;


            if (
                !empty(
                    $row["last_seen_at"]
                )
            ) {

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
             * Accept only reasonable intervals.
             *
             * This prevents a long computer-off period
             * from being counted as application use.
             */

            if (
                $delta > 0 &&
                $delta <= 120
            ) {

                $used += $delta;
            }


            /*
             * Never allow used time above duration.
             */

            if (
                $used >= $duration
            ) {

                $used =
                    $duration;

                $status =
                    "OFF";

                $remaining =
                    0;

                $message =
                    "Application-use time exhausted.";


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

            }

            else {

                /*
                 * License still has usage time.
                 */

                $status =
                    "ON";


                $remaining =
                    max(
                        0,
                        $duration - $used
                    );


                $message =
                    "Application authorized";


                /*
                 * Save usage time and last check.
                 */

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
            }
        }
    }


    /* =====================================================
       INVALID MODE
    ===================================================== */

    else {

        $status =
            "OFF";

        $remaining =
            0;

        $message =
            "Invalid license mode.";
    }
}


/* =========================================================
   FINAL SAFETY
========================================================= */

/*
 * Never return ON with zero remaining time.
 */

if (
    $status === "ON" &&
    $remaining <= 0
) {

    $status =
        "OFF";

    $remaining =
        0;

    $message =
        "License expired.";


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
```
