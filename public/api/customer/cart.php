<?php
/**
 * 购物车操作处理API
 * 遵循理想化分层架构 - 仅调用 functions.php 中的业务逻辑函数
 *
 * 【API响应格式修复】统一使用 ApiResponse 类返回JSON
 * 同时支持AJAX请求（返回JSON）和表单提交（重定向+flash消息）
 */
// 【修复】防止重复启动session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/ApiResponse.php';

// 注意：购物车API不需要登录验证，因为游客也可以添加购物车

// 检测是否为AJAX请求
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// 也可以通过Accept header检测
if (!$isAjax && isset($_SERVER['HTTP_ACCEPT'])) {
    $isAjax = strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stockId = $_POST['stock_id'] ?? null;
    $responseData = ['success' => false, 'message' => 'Unknown action'];

    switch ($action) {
        case 'add':
            if ($stockId) {
                $result = addToCart($pdo, $stockId);
                $responseData = [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'cart_count' => getCartCount()
                ];
                error_log("Cart action 'add': Stock #$stockId, Success: " . ($result['success'] ? 'Yes' : 'No'));
            } else {
                $responseData = ['success' => false, 'message' => 'Stock ID is required'];
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
                        $responseData = [
                            'success' => false,
                            'message' => 'Cannot add items from different stores. Please complete or clear your current cart first.'
                        ];
                        break;
                    }
                }

                $result = addMultipleToCart($pdo, $releaseId, $condition, $quantity);
                $responseData = [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'cart_count' => getCartCount()
                ];
                error_log("Cart action 'add_multiple': Release #$releaseId, Condition: $condition, Qty: $quantity, Shop: $shopId, Success: " . ($result['success'] ? 'Yes' : 'No'));
            } else {
                $responseData = ['success' => false, 'message' => 'Release ID and condition are required'];
            }
            break;

        case 'remove':
            if ($stockId) {
                if (removeFromCart($stockId)) {
                    $responseData = [
                        'success' => true,
                        'message' => 'Item removed from cart.',
                        'cart_count' => getCartCount()
                    ];
                    error_log("Cart action 'remove': Stock #$stockId removed successfully");
                } else {
                    $responseData = ['success' => false, 'message' => 'Failed to remove item from cart.'];
                    error_log("Cart action 'remove': Failed to remove Stock #$stockId");
                }
            } else {
                $responseData = ['success' => false, 'message' => 'Stock ID is required'];
            }
            break;

        case 'clear':
            $cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
            clearCart();
            $responseData = [
                'success' => true,
                'message' => 'Cart cleared.',
                'cart_count' => 0
            ];
            error_log("Cart action 'clear': $cartCount items cleared");
            break;

        case 'get_count':
            // 新增：仅获取购物车数量的API
            $responseData = [
                'success' => true,
                'message' => 'Cart count retrieved.',
                'cart_count' => getCartCount()
            ];
            break;

        default:
            $responseData = ['success' => false, 'message' => 'Unknown action: ' . $action];
    }

    // 确保session修改立即持久化
    session_write_close();

    // 【API响应格式修复】根据请求类型返回不同格式
    if ($isAjax) {
        // AJAX请求：返回JSON
        if ($responseData['success']) {
            ApiResponse::success($responseData, $responseData['message']);
        } else {
            ApiResponse::error($responseData['message'], 400);
        }
    } else {
        // 表单提交：使用flash消息和重定向（保持向后兼容）
        $flashType = $responseData['success'] ? 'success' : 'warning';
        if ($action === 'remove' || $action === 'clear') {
            $flashType = 'info';
        }
        flash($responseData['message'], $flashType);

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
}

// GET请求：返回当前购物车状态
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ApiResponse::success([
        'cart_count' => getCartCount(),
        'cart_items' => $_SESSION['cart'] ?? [],
        'selected_shop_id' => $_SESSION['selected_shop_id'] ?? null
    ], 'Cart status retrieved.');
}
