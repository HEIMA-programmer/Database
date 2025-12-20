<?php
session_start();
require_once __DIR__ . '/../../includes/functions.php';

// 初始化购物车
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';
$stockId = (int)($_POST['stock_id'] ?? 0);

if ($action === 'add' && $stockId > 0) {
    // 检查是否已经在购物车中
    if (in_array($stockId, $_SESSION['cart'])) {
        flash('This exact item is already in your cart.', 'info');
    } else {
        // 将 StockID 加入购物车数组
        $_SESSION['cart'][] = $stockId;
        flash('Item added to cart!', 'success');
    }
} 

elseif ($action === 'remove' && $stockId > 0) {
    // 从数组中移除
    $key = array_search($stockId, $_SESSION['cart']);
    if ($key !== false) {
        unset($_SESSION['cart'][$key]);
        // 重建索引
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        flash('Item removed from cart.', 'info');
    }
} 

elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
    flash('Cart cleared.', 'info');
}

// 返回来源页面
$referer = $_SERVER['HTTP_REFERER'] ?? '/customer/catalog.php';
header("Location: $referer");
exit();