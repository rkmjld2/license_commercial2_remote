```php
<?php

/*
===========================================================
COMMERCIAL LICENSE SERVER
license_check.php

VERSION 4

Supports:

1. CALENDAR
2. ACTUAL APPLICATION-USE

IMPORTANT:

expires_at is a HARD EXPIRY in BOTH modes.

Therefore:

    now >= expires_at
        => OFF

Even if:

    status = ON

This prevents USAGE licenses from continuing after
their absolute end date/time.

Timezone:
    Asia/Kolkata
===========================================================
*/

ob_start();

ini_set(
    "display_errors",
    "0"
);

error_reporting(E_ALL);

date_default_timezone_set(
    "Asia/Kolkata"
);


/* =========================================================
   CORS
========================================================= */

header(
    "Access-Control-Allow-Origin: *"
);

header(
    "Access-Control-Allow-Methods: POST, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type"
);

header(
    "Content-Type: application/json; charset=UTF-8"
);


/* =========================================================
   OPTIONS REQUEST
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "OPTIONS"
) {

    http_response_code(204);

    exit;
}


/* =========================================================
   DATABASE
========================================================= */

require_once "db.php";


/*
 * Database timezone = IST.
 */

$conn->query(
    "SET time_zone = '+05:30'"
);


/* =========================================================
   JSON RESPONSE
========================================================= */

function send_json($data)
{
    while (
        ob_get_level() > 0
    ) {

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


if (
    $user_id === ""
) {

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

$stmt =
    $conn->prepare("

        SELECT
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

        LIMIT 1

    ");


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
   VALUES
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


/*
 * Unix timestamp is independent of PHP timezone.
 */

$now =
    time();


$started_timestamp =
    false;

$expires_timestamp =
    false;


if (
    !empty(
        $row["started_at"]
    )
) {

    $started_timestamp =
        strtotime(
            $row["started_at"]
        );
}


if (
    !empty(
        $row["expires_at"]
    )
) {

    $expires_timestamp =
        strtotime(
            $row["expires_at"]
        );
}


$status =
    "OFF";


$message =
    "Application disabled";


/* =========================================================
   UNIVERSAL DATE/TIME VALIDATION
========================================================= */

/*
 * A license without a valid start or expiry cannot run.
 */

if (
    $started_timestamp === false ||
    $expires_timestamp === false
) {

    $status =
        "OFF";

    $message =
        "License start/end time is not configured.";

}


/* =========================================================
   BEFORE START
========================================================= */

elseif (
    $now < $started_timestamp
) {

    $status =
        "OFF";

    $message =
        "License has not started yet.";


/* =========================================================
   HARD EXPIRY
========================================================= */

} elseif (
    $now >= $expires_timestamp
) {

    /*
     * THIS IS THE IMPORTANT FIX.

     * It applies to BOTH:
     *
     *     CALENDAR
     *     USAGE
     *
     * Even if database status is ON,
     * the license is OFF after expires_at.
     */

    $status =
        "OFF";


    $message =
        "License expired.";


    /*
     * Automatically switch database status OFF.
     */

    $stmt2 =
        $conn->prepare("

            UPDATE licenses

            SET
                status = 'OFF',
                last_seen_at = NOW()

            WHERE user_id = ?

        ");


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
   CALENDAR MODE
========================================================= */

elseif (
    $mode === "CALENDAR"
) {

    /*
     * Remote administrator must have license ON.
     */

    if (
        $db_status !== "ON"
    ) {

        $status =
            "OFF";

        $message =
            "Application disabled.";

    } else {

        $status =
            "ON";

        $message =
            "Application authorized";


        /*
         * Record last check.
         */

        $stmt2 =
            $conn->prepare("

                UPDATE licenses

                SET last_seen_at = NOW()

                WHERE user_id = ?

            ");


        if ($stmt2) {

            $stmt2->bind_param(
                "s",
                $user_id
            );

            $stmt2->execute();

            $stmt2->close();
        }
    }
}


/* =========================================================
   ACTUAL APPLICATION-USE MODE
========================================================= */

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

        $message =
            "Application disabled.";

    } else {


        /* =================================================
           CALCULATE NEW USAGE
        ================================================= */

        $new_used =
            $used;


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


                /*
                 * Count only reasonable polling gaps.

                 * This prevents a long computer shutdown
                 * from consuming the whole license.
                 */

                if (
                    $delta > 0 &&
                    $delta <= 120
                ) {

                    $new_used +=
                        $delta;
                }
            }
        }


        /*
         * Never allow usage to exceed duration.
         */

        if (
            $new_used >= $duration
        ) {

            $new_used =
                $duration;


            $status =
                "OFF";


            $message =
                "Application-use time exhausted.";


            $stmt2 =
                $conn->prepare("

                    UPDATE licenses

                    SET
                        status = 'OFF',
                        used_seconds = ?,
                        last_seen_at = NOW()

                    WHERE user_id = ?

                ");


            if ($stmt2) {

                $stmt2->bind_param(
                    "is",
                    $new_used,
                    $user_id
                );

                $stmt2->execute();

                $stmt2->close();
            }


        } else {


            /*
             * Actual application-use time is still
             * available.
             */

            $used =
                $new_used;


            $status =
                "ON";


            $message =
                "Application authorized";


            $stmt2 =
                $conn->prepare("

                    UPDATE licenses

                    SET
                        used_seconds = ?,
                        last_seen_at = NOW()

                    WHERE user_id = ?

                ");


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


/* =========================================================
   INVALID MODE
========================================================= */

else {

    $status =
        "OFF";

    $message =
        "Invalid license mode.";
}


/* =========================================================
   REMAINING TIME
========================================================= */

$remaining =
    null;


/* =========================================================
   CALENDAR REMAINING
========================================================= */

if (
    $status === "ON" &&
    $mode === "CALENDAR"
) {

    $remaining =
        max(
            0,
            $expires_timestamp -
            $now
        );
}


/* =========================================================
   USAGE REMAINING
========================================================= */

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


    /*
     * Also obey absolute expiry.

     * This is a second safety check.
     */

    $calendar_remaining =
        max(
            0,
            $expires_timestamp -
            $now
        );


    if (
        $calendar_remaining <= 0
    ) {

        $remaining =
            0;

        $status =
            "OFF";

        $message =
            "License expired.";


        $stmt2 =
            $conn->prepare("

                UPDATE licenses

                SET
                    status = 'OFF',
                    last_seen_at = NOW()

                WHERE user_id = ?

            ");


        if ($stmt2) {

            $stmt2->bind_param(
                "s",
                $user_id
            );

            $stmt2->execute();

            $stmt2->close();
        }

    } else {

        /*
         * Return the smaller of:
         *
         * usage remaining
         * absolute calendar remaining
         */

        $remaining =
            min(
                $remaining,
                $calendar_remaining
            );
    }
}


/* =========================================================
   FINAL RESPONSE
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
