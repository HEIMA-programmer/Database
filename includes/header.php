<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';

// 获取当前脚本名称，用于高亮导航栏
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retro Echo Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-dark text-light d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-black border-bottom border-secondary">
  <div class="container">
    <a class="navbar-brand text-warning fw-bold" href="/">
        <i class="fa-solid fa-record-vinyl me-2"></i>Retro Echo
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        
        <?php if (hasRole('Customer')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'catalog.php' ? 'active' : '' ?>" href="/customer/catalog.php">Catalog</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'orders.php' ? 'active' : '' ?>" href="/customer/orders.php">My Orders</a>
            </li>
        <?php endif; ?>

        <?php if (hasRole('Staff')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'pos.php' ? 'active' : '' ?>" href="/staff/pos.php">POS System</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'buyback.php' ? 'active' : '' ?>" href="/staff/buyback.php">Buyback</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'inventory.php' ? 'active' : '' ?>" href="/staff/inventory.php">Inventory</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'pickup.php' ? 'active' : '' ?>" href="/staff/pickup.php">Pickups</a>
            </li>
        <?php endif; ?>

        <?php if (hasRole('Manager')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="/manager/dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'transfer.php' ? 'active' : '' ?>" href="/manager/transfer.php">Stock Transfers</a>
            </li>
        <?php endif; ?>

        <?php if (hasRole('Admin')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>" href="/admin/products.php">Products</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>" href="/admin/users.php">Users</a>
            </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto align-items-center">
        <?php if (isset($_SESSION['user_id'])): ?>
            
            <?php if (hasRole('Customer')): ?>
                <li class="nav-item me-3">
                    <a class="nav-link position-relative" href="/customer/cart.php">
                        <i class="fa-solid fa-cart-shopping fa-lg"></i>
                        <?php $cartCount = getCartCount(); ?>
                        <?php if($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $cartCount ?>
                                <span class="visually-hidden">items in cart</span>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-warning" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <?= h($_SESSION['username']) ?> <small class="text-secondary">(<?= h($_SESSION['role']) ?>)</small>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    <li><a class="dropdown-item" href="/logout.php">Logout</a></li>
                </ul>
            </li>

        <?php else: ?>
            <li class="nav-item">
                <a class="btn btn-outline-warning btn-sm" href="/login.php">Login</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 flex-grow-1">
    <?php displayFlash(); ?>