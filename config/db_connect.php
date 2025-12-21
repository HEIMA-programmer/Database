<?php
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); // 获取当前执行脚本的目录
$scriptPath = str_replace('\\', '/', $scriptPath); // 兼容 Windows 反斜杠

// 自动定位到 /public 目录
if (strpos($scriptPath, '/public') !== false) {
    // 截取到 /public 结束
    $baseUrl = substr($scriptPath, 0, strpos($scriptPath, '/public') + 7);
} else {
    // 如果还没进入 public 目录（备用）
    $baseUrl = $scriptPath . '/public';
}

// 移除末尾的斜杠（如果有多余的话）
$baseUrl = rtrim($baseUrl, '/');

define('BASE_URL', $baseUrl);
// 本地开发环境配置
date_default_timezone_set('Asia/Shanghai');

// XAMPP 默认设置
$host     = '127.0.0.1'; // 或 'localhost'
$dbname   = 'retro_echo';
$username = 'root';      // XAMPP 默认用户名
$password = '';          // XAMPP 默认密码为空 (注意这里留空字符串)
$port     = '3306';
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
    
    // 加载友好的错误页面
    $errorMessage = "Could not connect to the database server.";
    include __DIR__ . '/../includes/error.php';
    exit();
}
?>