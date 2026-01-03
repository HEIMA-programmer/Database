<?php
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$scriptPath = str_replace('\\', '/', $scriptPath);

if (strpos($scriptPath, '/public') !== false) {
    $baseUrl = substr($scriptPath, 0, strpos($scriptPath, '/public') + 7);
} else {
    $baseUrl = $scriptPath . '/public';
}

$baseUrl = rtrim($baseUrl, '/');

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

if (!defined('PAYMENT_TIMEOUT_MINUTES')) {
    define('PAYMENT_TIMEOUT_MINUTES', 15);
}

if (!defined('SHIPPING_FEE')) {
    define('SHIPPING_FEE', 15.00);
}

date_default_timezone_set('Asia/Shanghai');

// ========== 数据库配置 ==========
// 优先使用环境变量（阿里云 SAE），否则使用本地配置（XAMPP）
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$dbname   = getenv('DB_NAME') ?: 'retro_echo';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$port     = getenv('DB_PORT') ?: '3306';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    
    $errorMessage = "Could not connect to the database server.";
    include __DIR__ . '/../includes/error.php';
    exit();
}
?>