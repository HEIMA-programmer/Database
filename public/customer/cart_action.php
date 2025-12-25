<?php
/**
 * 购物车操作处理
 * 【架构重构】遵循理想化分层架构
 * - 仅调用 functions.php 中的业务逻辑函数
 * - 无直接数据库访问
 */
session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stockId = $_POST['stock_id'] ?? null;

    // =============================================
    // 【业务逻辑层调用】通过 functions.php 处理
    // =============================================

    switch ($action) {
        case 'add':
            if ($stockId) {
                $result = addToCart($pdo, $stockId);
                flash($result['message'], $result['success'] ? 'success' : 'warning');
            }
            break;

        case 'remove':
            if ($stockId) {
                if (removeFromCart($stockId)) {
                    flash('Item removed from cart.', 'info');
                }
            }
            break;

        case 'clear':
            clearCart();
            flash('Cart cleared.', 'info');
            break;
    }

    // 重定向回来源页面
    $referer = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header("Location: $referer");
    exit();
}
