<?php
session_start();
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $stockId = $_POST['stock_id'] ?? null;

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($action === 'add' && $stockId) {
        // 简单检查该物品是否已在购物车
        if (!in_array($stockId, $_SESSION['cart'])) {
            // 检查数据库中状态是否仍为 Available (防止重复添加已售出的)
            $stmt = $pdo->prepare("SELECT Status FROM StockItem WHERE StockItemID = ?");
            $stmt->execute([$stockId]);
            $status = $stmt->fetchColumn();

            if ($status === 'Available') {
                $_SESSION['cart'][] = $stockId;
                flash('Item added to cart.', 'success');
            } else {
                flash('Item is no longer available.', 'danger');
            }
        } else {
            flash('Item is already in your cart.', 'warning');
        }
    } 
    
    elseif ($action === 'remove' && $stockId) {
        $key = array_search($stockId, $_SESSION['cart']);
        if ($key !== false) {
            unset($_SESSION['cart'][$key]);
            flash('Item removed from cart.', 'info');
        }
    }

    // 重定向回来源页面
    $referer = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
    header("Location: $referer");
    exit();
}