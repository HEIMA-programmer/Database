<?php
// includes/auth_guard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 强制要求登录
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // 记录用户想去的页面，登录后跳转回来（优化体验）
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        flash('Please login to access this page.', 'warning');
        header("Location: " . BASE_URL . "/login.php");
        exit();
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