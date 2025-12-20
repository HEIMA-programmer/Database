<?php
session_start();

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
header("Location: /login.php");
exit();