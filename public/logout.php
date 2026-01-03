<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/db_procedures.php';

// 【修复】防止重复启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 【并发登录控制】登出时清空数据库中的 CurrentSessionID
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    if ($role === 'Customer') {
        DBProcedures::updateCustomerSessionId($pdo, $userId, null);
    } else {
        // Admin, Manager, Staff 都是 Employee
        DBProcedures::updateEmployeeSessionId($pdo, $userId, null);
    }
}

// 清除所有 Session 变量
$_SESSION = [];

// 销毁 Session Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁 Session
session_destroy();

// 重定向回首页或登录页
header("Location: " . BASE_URL . "/login.php");
exit();