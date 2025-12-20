<?php
// config/db_connect.php

// 设置默认时区，确保订单时间准确
date_default_timezone_set('Asia/Shanghai');

// 数据库配置 - 优先读取环境变量（AWS部署最佳实践），否则回退到本地默认值
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$dbname   = getenv('DB_NAME') ?: 'retro_echo';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: ''; // 本地开发通常为空，生产环境请配置
$port     = getenv('DB_PORT') ?: '3306';
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 抛出异常而不是静默失败
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // 默认返回关联数组
    PDO::ATTR_EMULATE_PREPARES   => false,                 // 禁用模拟预处理，防止SQL注入
    PDO::ATTR_PERSISTENT         => false,                 // 禁用持久连接，避免进程池问题
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // 生产环境中不应直接将错误详情输出给用户，而是记录日志
    error_log("Database Connection Error: " . $e->getMessage());
    
    // 给用户显示友好的错误页面
    die("
        <div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
            <h1>System Unavailable</h1>
            <p>We are experiencing technical difficulties. Please try again later.</p>
            <small>Error Ref: DB_CONN_FAIL</small>
        </div>
    ");
}
?>