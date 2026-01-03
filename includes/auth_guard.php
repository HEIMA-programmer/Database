<?php
// includes/auth_guard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 【修复】确保依赖项已加载
// BASE_URL 来自 db_connect.php，flash() 来自 functions.php
// 这些文件应该在调用 auth_guard.php 之前已被导入
// 但为安全起见，在此处也检查并导入
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/db_connect.php';
}
if (!function_exists('flash')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * 【并发登录控制】验证当前 session 是否有效
 * 检查数据库中的 CurrentSessionID 是否与当前 session_id() 匹配
 * 如果不匹配，说明账号在其他地方登录了，踢出当前用户
 *
 * @param PDO $pdo 数据库连接
 * @return bool 返回 session 是否有效
 */
function validateSessionConcurrency($pdo) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return true; // 未登录状态，跳过验证
    }

    $userId = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $currentSessionId = session_id();

    // 根据角色查询对应表的 CurrentSessionID
    if ($role === 'Customer') {
        $storedSessionId = DBProcedures::getCustomerSessionId($pdo, $userId);
    } else {
        // Admin, Manager, Staff 都是 Employee
        $storedSessionId = DBProcedures::getEmployeeSessionId($pdo, $userId);
    }

    // 如果数据库中的 session_id 与当前不匹配，踢出用户
    if ($storedSessionId !== null && $storedSessionId !== $currentSessionId) {
        // 清除 session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // 重新启动 session 以便设置 flash 消息
        session_start();
        flash('Your account has been logged in from another location. You have been logged out.', 'warning');

        header("Location: " . BASE_URL . "/login.php");
        exit();
    }

    return true;
}

/**
 * 强制要求登录
 */
function requireLogin() {
    global $pdo;

    if (!isset($_SESSION['user_id'])) {
        // 记录用户想去的页面，登录后跳转回来（优化体验）
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

        flash('Please login to access this page.', 'warning');
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }

    // 【并发登录控制】验证 session 是否仍然有效
    if (isset($pdo)) {
        validateSessionConcurrency($pdo);
    }
}

/**
 * 强制要求特定角色
 * @param string|array $allowedRoles 允许的角色，如 'Admin' 或 ['Manager', 'Staff']
 */
function requireRole($allowedRoles) {
    requireLogin(); // 先确保已登录

    $currentUserRole = $_SESSION['role'] ?? '';
    
    // 将单个字符串转换为数组，统一处理
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    if (!in_array($currentUserRole, $allowedRoles)) {
        // 记录非法访问日志
        error_log("Access Denied: User ID {$_SESSION['user_id']} with role {$currentUserRole} tried to access " . $_SERVER['PHP_SELF']);
        
        // 显示错误页面或跳转
        flash('Access Denied: You do not have permission to view this area.', 'danger');
        header("Location: " . BASE_URL . "/index.php"); // 跳转回首页
        exit();
    }
}
?>