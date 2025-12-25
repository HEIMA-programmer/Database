<?php
/**
 * 管理仪表板
 * 【架构重构】遵循理想化分层架构
 * - 通过 functions.php 的数据准备函数获取所有数据
 * - 底部仅负责 HTML 渲染
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/functions.php';

// =============================================
// 【数据准备层】调用 functions.php 获取所有数据
// =============================================
$dashboardData = prepareDashboardData($pdo);

// 提取变量供模板使用
$totalSales = $dashboardData['total_sales'];
$activeOrders = $dashboardData['active_orders'];
$lowStockCount = $dashboardData['low_stock_count'];
$topVipName = $dashboardData['top_vip_name'];
$vips = $dashboardData['vips'];
$deadStock = $dashboardData['dead_stock'];
$lowStock = $dashboardData['low_stock'];
$shopPerformance = $dashboardData['shop_performance'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- =============================================
     【表现层】仅负责 HTML 渲染，无任何业务逻辑
     ============================================= -->

<div class="row mb-4">
    <div class="col-12">
        <h2 class="text-warning display-6 fw-bold"><i class="fa-solid fa-chart-line me-2"></i>Executive Dashboard</h2>
        <p class="text-secondary">Real-time business intelligence and performance metrics.</p>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-success h-100">
            <div class="card-body">
                <h6 class="text-success text-uppercase mb-2">Total Revenue</h6>
                <h3 class="text-white fw-bold"><?= formatPrice($totalSales) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-info h-100">
            <div class="card-body">
                <h6 class="text-info text-uppercase mb-2">Active Orders</h6>
                <h3 class="text-white fw-bold"><?= $activeOrders ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-warning h-100">
            <div class="card-body">
                <h6 class="text-warning text-uppercase mb-2">Top VIP</h6>
                <h3 class="text-white fw-bold"><?= h($topVipName) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger text-uppercase mb-2">Low Stock Items</h6>
                <h3 class="text-white fw-bold"><?= $lowStockCount ?> Types</h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-warning mb-0"><i class="fa-solid fa-trophy me-2"></i>Top Spenders (Window Func)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Customer</th>
                            <th>Tier</th>
                            <th class="text-end">Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vips as $v): ?>
                        <tr>
                            <td><span class="badge bg-warning text-dark">#<?= $v['RankPosition'] ?></span></td>
                            <td><?= h($v['Name']) ?></td>
                            <td><small class="text-muted"><?= h($v['TierName']) ?></small></td>
                            <td class="text-end text-success fw-bold"><?= formatPrice($v['TotalSpent']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($vips)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No sales data available yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Stagnant Inventory (>60 Days)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Days</th>
                            <th>Album</th>
                            <th>Batch</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deadStock as $d): ?>
                        <tr>
                            <td class="text-danger fw-bold"><?= $d['DaysInStock'] ?> days</td>
                            <td>
                                <div><?= h($d['Title']) ?></div>
                                <small class="text-muted"><?= h($d['ArtistName']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= h($d['BatchNo']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($deadStock)): ?>
                            <tr><td colspan="3" class="text-center text-success py-3">No stagnant inventory found! Excellent work.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-danger mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Low Stock Alert (< 3 Units)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Album</th>
                            <th>Shop</th>
                            <th class="text-center">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStock as $ls): ?>
                        <tr>
                            <td>
                                <div><?= h($ls['Title']) ?></div>
                                <small class="text-muted"><?= h($ls['ArtistName']) ?></small>
                            </td>
                            <td><?= h($ls['ShopName']) ?></td>
                            <td class="text-center"><span class="badge bg-danger"><?= $ls['AvailableQuantity'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lowStock)): ?>
                            <tr><td colspan="3" class="text-center text-success py-3">All items well stocked!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-info mb-0"><i class="fa-solid fa-store me-2"></i>Shop Performance</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th>Type</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shopPerformance as $sp): ?>
                        <tr>
                            <td class="fw-bold"><?= h($sp['ShopName']) ?></td>
                            <td><span class="badge bg-secondary"><?= h($sp['Type']) ?></span></td>
                            <td class="text-center"><?= $sp['TotalOrders'] ?></td>
                            <td class="text-end text-success fw-bold"><?= formatPrice($sp['Revenue']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($shopPerformance)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No sales data yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
