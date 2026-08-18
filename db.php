<?php
/*
===========================================================
        REMOTE LICENSE DATABASE CONNECTION
===========================================================

Database server : TiDB Cloud
Database        : license_demo_v2
Port            : 4000

IMPORTANT:
Credentials are obtained from environment variables.
Do NOT put the TiDB password directly in this file.
===========================================================
*/


$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$dbname = getenv("DB_NAME");
$port = getenv("DB_PORT");


/*
-----------------------------------------------------------
Check required environment variables
-----------------------------------------------------------
*/

if (
    !$host ||
    !$user ||
    !$password ||
    !$dbname ||
    !$port
) {

    die("Database configuration is incomplete.");
}


/*
-----------------------------------------------------------
Create SSL connection to TiDB Cloud
-----------------------------------------------------------
*/

$conn = mysqli_init();

mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL
);


/*
-----------------------------------------------------------
Connect to TiDB Cloud
-----------------------------------------------------------
*/

if (
    !$conn->real_connect(
        $host,
        $user,
        $password,
        $dbname,
        (int)$port,
        NULL,
        MYSQLI_CLIENT_SSL
    )
) {

    die(
        "Database connection failed: "
        . htmlspecialchars(
            $conn->connect_error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}


/*
-----------------------------------------------------------
Character set
-----------------------------------------------------------
*/

$conn->set_charset("utf8mb4");

?>

