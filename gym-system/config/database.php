<?php
// Database configuration
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'gym_system');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME',   'FitCore Pro');
define('APP_URL',    'http://localhost/gym-system');

// Returns a singleton database connection
function db(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            die('<div style="font-family:monospace;color:#f44;padding:20px;background:#111">
                 Database Error: ' . htmlspecialchars($conn->connect_error) . '<br>
                 Check your MySQL service and config/database.php settings.
                 </div>');
        }

        $conn->set_charset(DB_CHARSET);
    }

    return $conn;
}
