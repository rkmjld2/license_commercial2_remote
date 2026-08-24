<?php

/*
===========================================================
 LICENSE ADMIN V3
 PASSWORD PROTECTED
===========================================================

Features:

    1. Administrator password protection
    2. Password stored in Render Environment Variable
    3. CALENDAR license mode
    4. ACTUAL APPLICATION-USE license mode
    5. Independent Start Date/Time
    6. Independent End Date/Time
    7. START / RESET LICENSE
    8. ON
    9. REMOTE OFF
   10. License information display
   11. Logout

IMPORTANT:

The administrator password is NOT stored in this file.

Render Environment Variable:

    LICENSE_ADMIN_PASSWORD

The customer application does NOT use this password.

The customer application continues to call:

    license_check.php

===========================================================
*/


/* =========================================================
   TIMEZONE
========================================================= */

date_default_timezone_set(
    "Asia/Kolkata"
);


/* =========================================================
   SESSION
========================================================= */

session_start();


/* =========================================================
   ADMIN PASSWORD FROM ENVIRONMENT
========================================================= */

$admin_password =
    getenv("LICENSE_ADMIN_PASSWORD");


/*
 * Environment variable must exist.
 */

if (
    $admin_password === false ||
    trim($admin_password) === ""
) {

    http_response_code(500);

    die(
        "LICENSE_ADMIN_PASSWORD is not configured."
    );
}


/* =========================================================
   LOGOUT
========================================================= */

if (
    isset($_GET["logout"])
) {

    /*
     * Destroy administrator session.
     */

    $_SESSION = [];


    if (
        ini_get("session.use_cookies")
    ) {

        $params =
            session_get_cookie_params();


        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }


    session_destroy();


    header(
        "Location: admin.php"
    );

    exit;
}


/* =========================================================
   ADMIN LOGIN
========================================================= */

$login_error = "";


if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["admin_login"])
) {

    $entered_password =
        $_POST["admin_password"] ?? "";


    /*
     * Compare entered password with
     * Render Environment Variable.
     *
     * hash_equals() performs a timing-safe
     * string comparison.
     */

    if (
        hash_equals(
            $admin_password,
            $entered_password
        )
    ) {

        /*
         * New session ID after successful login.
         */

        session_regenerate_id(true);


        $_SESSION[
            "license_admin_authenticated"
        ] = true;


        header(
            "Location: admin.php"
        );

        exit;

    }
    else {

        $login_error =
            "Invalid administrator password.";
    }
}


/* =========================================================
   CHECK ADMIN AUTHENTICATION
========================================================= */

if (
    !isset(
        $_SESSION[
            "license_admin_authenticated"
        ]
    ) ||
    $_SESSION[
        "license_admin_authenticated"
    ] !== true
) {

    ?>

    <!DOCTYPE html>

    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>
            License Administrator Login
        </title>


        <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 20px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f2f4f7;
        }


        .login-box {

            width: 100%;

            max-width: 420px;

            margin: 80px auto;

            background:
                white;

            padding: 30px;

            border-radius:
                12px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,0.15);

            text-align:
                center;
        }


        h1 {

            margin-top: 0;

            color:
                #1d3557;
        }


        .subtitle {

            color:
                #666;

            margin-bottom:
                25px;
        }


        .error {

            background:
                #f8d7da;

            color:
                #842029;

            padding:
                12px;

            border-radius:
                6px;

            margin-bottom:
                18px;

            font-weight:
                bold;

            line-height:
                1.5;
        }


        .form-group {

            text-align:
                left;

            margin-bottom:
                15px;
        }


        label {

            display:
                block;

            font-weight:
                bold;

            margin-bottom:
                6px;
        }


        input {

            width:
                100%;

            padding:
                12px;

            border:
                1px solid #aaa;

            border-radius:
                6px;

            font-size:
                16px;
        }


        button {

            width:
                100%;

            padding:
                12px;

            border:
                none;

            border-radius:
                6px;

            background:
                #0d6efd;

            color:
                white;

            font-size:
                16px;

            cursor:
                pointer;

            margin-top:
                10px;
        }


        button:hover {

            opacity:
                0.85;
        }


        .footer {

            margin-top:
                20px;

            color:
                #777;

            font-size:
                13px;
        }

        </style>

    </head>


    <body>


    <div class="login-box">


        <h1>
            LICENSE ADMIN
        </h1>


        <div class="subtitle">

            Administrator access only

        </div>


        <?php

        if (
            $login_error !== ""
        ) {

        ?>

        <div class="error">

            <?= htmlspecialchars(
                $login_error,
                ENT_QUOTES,
                "UTF-8"
            ) ?>

        </div>

        <?php

        }

        ?>


        <form
            method="POST"
            action="admin.php"
        >


            <div class="form-group">


                <label
                    for="admin_password"
                >
                    Administrator Password
                </label>


                <input
                    type="password"
                    id="admin_password"
                    name="admin_password"
                    required
                    autofocus
                >


            </div>


            <button
                type="submit"
                name="admin_login"
                value="1"
            >
                LOGIN
            </button>


        </form>


        <div class="footer">

            Authorized administrator only

            <br>

            Time zone:
            Asia/Kolkata (IST)

        </div>


    </div>


    </body>

    </html>

    <?php

    exit;
}


/* =========================================================
   LOAD DATABASE
========================================================= */

require_once "db.php";


/*
 * Database session timezone = IST
 */

$conn->query(
    "SET time_zone = '+05:30'"
);


/* =========================================================
   USER ID
========================================================= */

$user_id =
    $_POST["user_id"]
    ??
    $_GET["user_id"]
    ??
    "USER001";


$user_id =
    trim($user_id);


$message = "";


/* =========================================================
   HANDLE BUTTON ACTIONS
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $action =
        $_POST["action"] ?? "";


    $license_mode =
        $_POST["license_mode"]
        ??
        "CALENDAR";


    /* =====================================================
       START / RESET LICENSE
    ===================================================== */

    if (
        $action === "start"
    ) {

        $start_input =
            trim(
                $_POST["start_datetime"] ?? ""
            );


        $end_input =
            trim(
                $_POST["end_datetime"] ?? ""
            );


        if (
            $start_input === "" ||
            $end_input === ""
        ) {

            $message =
                "Please select both Start Date/Time and End Date/Time.";

        }

        else {

            /*
             * Convert HTML datetime-local
             * T into MySQL space.
             */

            $started_at =
                str_replace(
                    "T",
                    " ",
                    $start_input
                );


            $expires_at =
                str_replace(
                    "T",
                    " ",
                    $end_input
                );


            /*
             * Convert to timestamps.
             */

            $start_timestamp =
                strtotime(
                    $started_at
                );


            $end_timestamp =
                strtotime(
                    $expires_at
                );


            /*
             * Validate dates.
             */

            if (
                $start_timestamp === false ||
                $end_timestamp === false
            ) {

                $message =
                    "Invalid Start or End Date/Time.";

            }

            elseif (
                $end_timestamp <=
                $start_timestamp
            ) {

                $message =
                    "End Date/Time must be later than Start Date/Time.";

            }

            else {

                /*
                 * Calculate license window.
                 */

                $duration_seconds =
                    $end_timestamp -
                    $start_timestamp;


                /*
                 * START / RESET
                 */

                $stmt =
                    $conn->prepare(

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


                    if (
                        $stmt->execute()
                    ) {

                        if (
                            $license_mode ===
                            "USAGE"
                        ) {

                            $label =
                                "Actual application-use time";

                        }
                        else {

                            $label =
                                "Calendar time";
                        }


                        $message =
                            "$user_id started: " .
                            "$label from " .
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

    elseif (
        $action === "on"
    ) {

        /*
         * ON changes only status.
         */

        $stmt =
            $conn->prepare(

                "UPDATE licenses
                 SET status = 'ON'
                 WHERE user_id = ?"
            );


        if ($stmt) {

            $stmt->bind_param(
                "s",
                $user_id
            );


            $stmt->execute();


            $stmt->close();


            $message =
                "$user_id switched ON.";

        }
        else {

            $message =
                "Database statement error.";
        }
    }


    /* =====================================================
       REMOTE OFF
    ===================================================== */

    elseif (
        $action === "off"
    ) {

        /*
         * OFF changes only status.
         *
         * Usage time is NOT reset.
         */

        $stmt =
            $conn->prepare(

                "UPDATE licenses
                 SET status = 'OFF'
                 WHERE user_id = ?"
            );


        if ($stmt) {

            $stmt->bind_param(
                "s",
                $user_id
            );


            $stmt->execute();


            $stmt->close();


            $message =
                "$user_id switched OFF.";

        }
        else {

            $message =
                "Database statement error.";
        }
    }
}


/* =========================================================
   READ CURRENT LICENSE
========================================================= */

$stmt =
    $conn->prepare(

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
    $stmt
    ->get_result()
    ->fetch_assoc();


$stmt->close();


/* =========================================================
   USER DOES NOT EXIST
========================================================= */

if (!$license) {

    die(
        "User ID not found: " .
        htmlspecialchars(
            $user_id,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}


$current_mode =
    $license["license_mode"];


/* =========================================================
   DEFAULT DATETIME VALUES
========================================================= */

$start_value = "";

$end_value = "";


if (
    !empty(
        $license["started_at"]
    )
) {

    $start_value =
        date(
            "Y-m-d\TH:i",
            strtotime(
                $license["started_at"]
            )
        );
}


if (
    !empty(
        $license["expires_at"]
    )
) {

    $end_value =
        date(
            "Y-m-d\TH:i",
            strtotime(
                $license["expires_at"]
            )
        );
}


/* =========================================================
   HTML ESCAPE
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   DISPLAY DURATION
========================================================= */

function show_duration($seconds)
{
    $seconds =
        (int)$seconds;


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


/* =========================================================
   DISPLAY USED TIME
========================================================= */

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

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
License Admin V3
</title>


<style>

body {

    font-family:
        Arial,
        sans-serif;

    text-align:
        center;

    margin:
        30px;
}


.box {

    max-width:
        1150px;

    margin:
        auto;

    border:
        1px solid #ccc;

    border-radius:
        10px;

    padding:
        20px;
}


.topbar {

    text-align:
        right;

    margin-bottom:
        15px;
}


.logout {

    display:
        inline-block;

    padding:
        8px 15px;

    background:
        #dc3545;

    color:
        white;

    text-decoration:
        none;

    border-radius:
        6px;
}


input,
select,
button {

    padding:
        9px;

    margin:
        5px;
}


input[type="datetime-local"] {

    min-width:
        220px;
}


button {

    cursor:
        pointer;
}


table {

    border-collapse:
        collapse;

    width:
        100%;

    margin-top:
        20px;
}


th,
td {

    border:
        1px solid #ccc;

    padding:
        10px;
}


th {

    background:
        #f2f2f2;
}


.message {

    margin:
        15px;

    padding:
        10px;

    background:
        #eef7ee;

    border:
        1px solid #b7d7b7;

    border-radius:
        5px;
}


.datetime-row {

    margin-top:
        15px;

    margin-bottom:
        15px;
}


.datetime-row label {

    font-weight:
        bold;

    margin-left:
        10px;
}

</style>

</head>


<body>


<h1>
License Control Panel — V3
</h1>


<div class="box">


<!-- =====================================================
     LOGOUT
===================================================== -->

<div class="topbar">

<a
    class="logout"
    href="admin.php?logout=1"
>
    LOGOUT
</a>

</div>


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
    <?= $current_mode === "CALENDAR"
        ? "selected"
        : "" ?>
>
    Calendar time
</option>


<option
    value="USAGE"
    <?= $current_mode === "USAGE"
        ? "selected"
        : "" ?>
>
    Actual application-use time
</option>


</select>


<br>


<!-- =====================================================
     START DATE / TIME
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
     END DATE / TIME
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

<?php

if (
    $message !== ""
) {

?>

<div class="message">

<strong>

<?= h($message) ?>

</strong>

</div>

<?php

}

?>


<!-- =====================================================
     LICENSE INFORMATION
===================================================== -->

<table>

<tr>

<th>
User
</th>

<th>
Status
</th>

<th>
Mode
</th>

<th>
Window
</th>

<th>
Used
</th>

<th>
Started
</th>

<th>
Expires
</th>

<th>
Last Check
</th>

</tr>


<tr>

<td>
<?= h(
    $license["user_id"]
) ?>
</td>


<td>
<?= h(
    $license["status"]
) ?>
</td>


<td>
<?= h(
    $license["license_mode"]
) ?>
</td>


<td>
<?= h(
    show_duration(
        $license["duration_seconds"]
    )
) ?>
</td>


<td>
<?= h(
    show_used(
        $license["used_seconds"]
    )
) ?>
</td>


<td>
<?= h(
    $license["started_at"]
    ?: "-"
) ?>
</td>


<td>
<?= h(
    $license["expires_at"]
    ?: "-"
) ?>
</td>


<td>
<?= h(
    $license["last_seen_at"]
    ?: "-"
) ?>
</td>

</tr>

</table>


</div>


</body>

</html>

<?php

$conn->close();

?>
