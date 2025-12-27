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
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/enhancements.css">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>/">
        <i class="fa-solid fa-record-vinyl me-2"></i>Retro Echo
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        
        <?php if (hasRole('Customer')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'catalog.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/customer/catalog.php">Catalog</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'orders.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/customer/orders.php">Orders</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/customer/profile.php">Membership</a>
            </li>
        <?php endif; ?>

        <?php if (hasRole('Staff')):
            // 【修复】根据店铺类型动态显示菜单
            // 仓库员工只显示 Fulfillment 和 Inventory
            $isWarehouseStaff = ($_SESSION['user']['ShopType'] ?? '') === 'Warehouse';
        ?>
            <?php if (!$isWarehouseStaff): // 门店员工专有菜单 ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'pos.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/staff/pos.php">
                    <i class="fa-solid fa-cash-register me-1"></i>POS
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'buyback.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/staff/buyback.php">
                    <i class="fa-solid fa-recycle me-1"></i>Buyback
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'pickup.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/staff/pickup.php">
                    <i class="fa-solid fa-box-open me-1"></i>Pickups
                </a>
            </li>
            <?php endif; ?>
            <!-- 所有员工通用菜单 -->
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'fulfillment.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/staff/fulfillment.php">
                    <i class="fa-solid fa-truck-fast me-1"></i>Fulfillment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'inventory.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/staff/inventory.php">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>Inventory
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasRole('Manager')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/manager/dashboard.php">
                    <i class="fa-solid fa-chart-line me-1"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/manager/reports.php">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'transfer.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/manager/transfer.php">
                    <i class="fa-solid fa-truck-ramp-box me-1"></i>Transfers
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasRole('Admin')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'products.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/products.php">
                    <i class="fa-solid fa-record-vinyl me-1"></i>Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'procurement.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/procurement.php">
                    <i class="fa-solid fa-boxes-packing me-1"></i>Procurement
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'suppliers.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/suppliers.php">
                    <i class="fa-solid fa-truck-field me-1"></i>Suppliers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/users.php">
                    <i class="fa-solid fa-users-gear me-1"></i>Users
                </a>
            </li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto align-items-center">
        <?php if (isset($_SESSION['user_id'])): ?>
            
            <?php if (hasRole('Customer')): ?>
                <li class="nav-item me-3">
                    <a class="nav-link position-relative btn btn-sm btn-outline-warning border-0 px-3" href="<?= BASE_URL ?>/customer/cart.php">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <?php $cartCount = getCartCount(); ?>
                        <?php if($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $cartCount ?>
                                <span class="visually-hidden">items</span>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>

            <?php if (isset($_SESSION['shop_name'])): ?>
                <li class="nav-item me-3">
                    <span class="nav-link text-info">
                        <i class="fa-solid fa-store me-1"></i><?= h($_SESSION['shop_name']) ?>
                    </span>
                </li>
            <?php endif; ?>

            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-warning fw-bold" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fa-regular fa-circle-user me-1"></i><?= h($_SESSION['username']) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end border-warning">
                    <li><span class="dropdown-header text-muted">
                        <i class="fa-solid fa-user-tag me-1"></i>Role: <?= h($_SESSION['role']) ?>
                    </span></li>
                    <?php if (isset($_SESSION['shop_name'])): ?>
                        <li><span class="dropdown-header text-muted small">
                            <i class="fa-solid fa-location-dot me-1"></i><?= h($_SESSION['shop_name']) ?>
                        </span></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider border-secondary"></li>
                    <?php if (hasRole('Customer')): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/customer/profile.php">
                            <i class="fa-solid fa-id-card me-2"></i>My Profile
                        </a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
                    </a></li>
                </ul>
            </li>

        <?php else: ?>
            <li class="nav-item">
                <a class="btn btn-warning btn-sm fw-bold px-4" href="<?= BASE_URL ?>/login.php">Login</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-5 flex-grow-1">
    <?php displayFlash(); ?>