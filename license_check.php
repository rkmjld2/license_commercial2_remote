<?php

date_default_timezone_set("Asia/Kolkata");

/*
===========================================================
 COMMERCIAL LICENSE SERVER
 license_check.php

 IMPORTANT:
 This file must return PURE JSON.
 Do NOT put HTML or Markdown code fences in this file.

 Database:
 license_demo_v2

 Table:
 licenses

 Supports:
 CALENDAR
 USAGE
 Remote ON/OFF
===========================================================
*/

ob_start();

ini_set("display_errors", "0");
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");


/* ---------------------------------------------------------
   LOAD DATABASE
--------------------------------------------------------- */

require_once "db.php";

/*
 * Set database session timezone to Indian Standard Time.
 *
 * This makes NOW() and DATE_ADD(NOW(), ...)
 * use IST for this database connection.
 */
$conn->query("SET time_zone = '+05:30'");


/* ---------------------------------------------------------
   FUNCTION: JSON RESPONSE
--------------------------------------------------------- */

function send_json($data)
{
    /*
     * Remove anything accidentally produced before JSON.
     */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header("Content-Type: application/json; charset=UTF-8");

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* ---------------------------------------------------------
   CHECK DATABASE CONNECTION
--------------------------------------------------------- */

if (!isset($conn) || !($conn instanceof mysqli)) {

    send_json([
        "success" => false,
        "status" => "OFF",
        "message" => "Database connection is not available."
    ]);
}


/* ---------------------------------------------------------
   GET USER ID
--------------------------------------------------------- */

$user_id = trim($_POST["user_id"] ?? "");


if ($user_id === "") {

    send_json([
        "success" => false,
        "status" => "OFF",
        "message" => "Missing USER_ID"
    ]);
}


/* ---------------------------------------------------------
   FIND LICENSE
--------------------------------------------------------- */

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
        "message" => "Database statement error."
    ]);
}


$stmt->bind_param("s", $user_id);


if (!$stmt->execute()) {

    $stmt->close();

    send_json([
        "success" => false,
        "status" => "OFF",
        "message" => "Database query failed."
    ]);
}


$result = $stmt->get_result();


/* ---------------------------------------------------------
   UNKNOWN USER
--------------------------------------------------------- */

if ($result->num_rows === 0) {

    $stmt->close();

    send_json([
        "success" => false,
        "status" => "OFF",
        "message" => "Unknown USER_ID"
    ]);
}


$row = $result->fetch_assoc();

$stmt->close();


/* ---------------------------------------------------------
   LICENSE VALUES
--------------------------------------------------------- */

$status =
    strtoupper(trim((string)$row["status"]));

$mode =
    strtoupper(trim((string)$row["license_mode"]));

$duration =
    (int)$row["duration_seconds"];

$used =
    (int)$row["used_seconds"];


/* =========================================================
   CALENDAR MODE
========================================================= */

if ($status === "ON" && $mode === "CALENDAR") {

    $now = time();

    $expires_timestamp = null;

    if (!empty($row["expires_at"])) {

        $expires_timestamp =
            strtotime($row["expires_at"]);
    }


    /*
     * Create expiry if it does not exist.
     */

    if ($expires_timestamp === false ||
        $expires_timestamp === null) {

        $started_timestamp = null;

        if (!empty($row["started_at"])) {

            $started_timestamp =
                strtotime($row["started_at"]);
        }


        if ($started_timestamp === false ||
            $started_timestamp === null) {

            $started_timestamp = $now;
        }


        $expires_timestamp =
            $started_timestamp + $duration;


        $expires_at =
            date(
                "Y-m-d H:i:s",
                $expires_timestamp
            );


        $stmt2 = $conn->prepare(
            "UPDATE licenses
             SET started_at = COALESCE(started_at, NOW()),
                 expires_at = ?
             WHERE user_id = ?"
        );


        if ($stmt2) {

            $stmt2->bind_param(
                "ss",
                $expires_at,
                $user_id
            );

            $stmt2->execute();

            $stmt2->close();
        }


        $row["expires_at"] = $expires_at;
    }


    /* -----------------------------------------------------
       CHECK EXPIRY
    ----------------------------------------------------- */

    if ($now >= $expires_timestamp) {

        $status = "OFF";


        $stmt2 = $conn->prepare(
            "UPDATE licenses
             SET status = 'OFF',
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

    } else {

        /*
         * License is still valid.
         */

        $stmt2 = $conn->prepare(
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
}


/* =========================================================
   ACTUAL APPLICATION-USE MODE
========================================================= */

elseif ($status === "ON" && $mode === "USAGE") {

    $delta = null;


    /*
     * Calculate time since previous license check.
     */

    if (!empty($row["last_seen_at"])) {

        $last_seen_timestamp =
            strtotime($row["last_seen_at"]);


        if ($last_seen_timestamp !== false) {

            $delta =
                time() - $last_seen_timestamp;
        }
    }


    /*
     * Count only reasonable active-use intervals.
     *
     * Normal index refresh = 30 seconds.
     *
     * Maximum countable gap = 120 seconds.
     */

    if (
        $delta !== null &&
        $delta > 0 &&
        $delta <= 120
    ) {

        $used += $delta;
    }


    /*
     * Do not exceed total duration.
     */

    if ($used >= $duration) {

        $used = $duration;

        $status = "OFF";


        $stmt2 = $conn->prepare(
            "UPDATE licenses
             SET status = 'OFF',
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

    } else {

        /*
         * Save accumulated usage.
         */

        $stmt2 = $conn->prepare(
            "UPDATE licenses
             SET used_seconds = ?,
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


/* =========================================================
   CALCULATE REMAINING TIME
========================================================= */

$remaining = null;


if ($status === "ON") {

    /* -----------------------------------------------------
       CALENDAR
    ----------------------------------------------------- */

    if ($mode === "CALENDAR") {

        if (!empty($row["expires_at"])) {

            $expires_timestamp =
                strtotime($row["expires_at"]);


            if ($expires_timestamp !== false) {

                $remaining =
                    max(
                        0,
                        $expires_timestamp - time()
                    );
            }
        }
    }


    /* -----------------------------------------------------
       USAGE
    ----------------------------------------------------- */

    elseif ($mode === "USAGE") {

        $remaining =
            max(
                0,
                $duration - $used
            );
    }
}


/* =========================================================
   FINAL JSON RESPONSE
========================================================= */

send_json([

    "success" => true,

    "user_id" => $user_id,

    "status" => $status,

    "license_mode" => $mode,

    "remaining_seconds" => $remaining,

    "used_seconds" => $used,

    "duration_seconds" => $duration,

    "started_at" =>
        $row["started_at"],

    "expires_at" =>
        $row["expires_at"],

    "server_time" =>
        date("Y-m-d H:i:s"),

    "message" =>
        ($status === "ON")
        ? "Application authorized"
        : "Application disabled"
]);

?>
