<?php
/**
 * 购物车操作处理API
 * 遵循理想化分层架构 - 仅调用 functions.php 中的业务逻辑函数
 */
session_start();
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';

// 注意：购物车API不需要登录验证，因为游客也可以添加购物车

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stockId = $_POST['stock_id'] ?? null;

    switch ($action) {
        case 'add':
            if ($stockId) {
                $result = addToCart($pdo, $stockId);
                flash($result['message'], $result['success'] ? 'success' : 'warning');
                error_log("Cart action 'add': Stock #$stockId, Success: " . ($result['success'] ? 'Yes' : 'No'));
            }
            break;

        case 'add_multiple':
            // 支持从专辑详情页按条件和数量添加
            $releaseId = $_POST['release_id'] ?? null;
            $condition = $_POST['condition'] ?? null;
            $quantity = $_POST['quantity'] ?? 1;
            $shopId = $_POST['shop_id'] ?? null;

            if ($releaseId && $condition) {
                // 设置或验证店铺ID
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
                } else {
                    flash('Failed to remove item from cart.', 'warning');
                    error_log("Cart action 'remove': Failed to remove Stock #$stockId");
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

    // 确保session修改立即持久化
    session_write_close();

    // 重定向回来源页面（安全验证：只允许站内重定向）
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $defaultRedirect = '../customer/cart.php';

    // 验证 referer 是相对路径或同域名
    if (!empty($referer)) {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';

        // 只有同域名或空主机（相对路径）才允许重定向
        if ($refererHost !== null && $refererHost !== $currentHost) {
            $referer = $defaultRedirect;
        }
    } else {
        $referer = $defaultRedirect;
    }

    header("Location: $referer");
    exit();
}
