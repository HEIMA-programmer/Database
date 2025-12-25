<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/header.php';

// --- 使用视图: Top Spending VIPs ---
// 使用报表视图获取顶级客户（限制前5名）
$vips = $pdo->query("SELECT * FROM vw_report_top_customers LIMIT 5")->fetchAll();

// [Robustness Fix] 获取第一名 VIP 用于卡片展示，如果为空则提供默认值
$topVipName = !empty($vips) ? $vips[0]['Name'] : 'No Data';

// 使用视图查询死库存预警（超过60天未售出）
$deadStock = $pdo->query("SELECT * FROM vw_dead_stock_alert LIMIT 10")->fetchAll();

// 【新增】使用视图查询低库存预警（库存少于3件）
$lowStock = $pdo->query("SELECT * FROM vw_low_stock_alert LIMIT 10")->fetchAll();

// 【新增】使用视图查询店铺业绩
$shopPerformance = $pdo->query("SELECT * FROM vw_manager_shop_performance ORDER BY Revenue DESC")->fetchAll();

// --- KPI Stats ---
$totalSales = $pdo->query("SELECT SUM(TotalAmount) FROM CustomerOrder WHERE OrderStatus != 'Cancelled'")->fetchColumn() ?: 0.00;
$activeOrders = $pdo->query("SELECT COUNT(*) FROM CustomerOrder WHERE OrderStatus IN ('Pending', 'Paid', 'Shipped')")->fetchColumn();
$lowStockCount = count($lowStock);
?>

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
                        <?php if(empty($vips)): ?>
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
                        <?php if(empty($deadStock)): ?>
                            <tr><td colspan="3" class="text-center text-success py-3">No stagnant inventory found! Excellent work.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 【新增】低库存预警和店铺业绩 -->
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
                        <?php if(empty($lowStock)): ?>
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
                        <?php if(empty($shopPerformance)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No sales data yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>