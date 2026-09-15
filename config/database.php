<?php
date_default_timezone_set('Asia/Manila');


// IMPORTANT TO NIGGA
define('DB_HOST', 'localhost');
define('DB_NAME', 'kapebilidad');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

if (!defined('BASE_URL')) {
    $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

    $basePath = '';
    if ($documentRoot !== '' && strpos($appRoot, $documentRoot) === 0) {
        $basePath = substr($appRoot, strlen($documentRoot));
    }
    define('BASE_URL', $basePath);
}

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Please check config/database.php and make sure MySQL is running.');
        }
    }

    return $pdo;
}
