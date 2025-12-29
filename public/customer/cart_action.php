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
                error_log("Cart action 'add': Stock #$stockId, Success: " . ($result['success'] ? 'Yes' : 'No'));
            }
            break;

        case 'add_multiple':
            // 【新增】支持从专辑详情页按条件和数量添加
            $releaseId = $_POST['release_id'] ?? null;
            $condition = $_POST['condition'] ?? null;
            $quantity = $_POST['quantity'] ?? 1;
            $shopId = $_POST['shop_id'] ?? null;

            if ($releaseId && $condition) {
                // 【修复】设置或验证店铺ID
                if ($shopId) {
                    if (!isset($_SESSION['selected_shop_id'])) {
                        $_SESSION['selected_shop_id'] = (int)$shopId;
                    } elseif ($_SESSION['selected_shop_id'] != (int)$shopId) {
                        flash('Cannot add items from different stores. Please complete or clear your current cart first.', 'danger');
                        break;
                    }
                }

                $result = addMultipleToCart($pdo, $releaseId, $condition, $quantity);
                flash($result['message'], $result['success'] ? 'success' : 'warning');
                error_log("Cart action 'add_multiple': Release #$releaseId, Condition: $condition, Qty: $quantity, Shop: $shopId, Success: " . ($result['success'] ? 'Yes' : 'No'));
            }
            break;

        case 'remove':
            if ($stockId) {
                if (removeFromCart($stockId)) {
                    flash('Item removed from cart.', 'info');
                    error_log("Cart action 'remove': Stock #$stockId removed successfully");
                }
            }
            break;

        case 'clear':
            $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
            clearCart();
            flash('Cart cleared.', 'info');
            error_log("Cart action 'clear': $cartCount items cleared");
            break;
    }

    // 【修复5】确保session修改立即持久化
    session_write_close();

    // 重定向回来源页面
    $referer = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header("Location: $referer");
    exit();
}
