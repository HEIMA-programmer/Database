<?php
/**
 * 管理仪表板 - 重构版
 * 【架构重构】Manager只能查看自己店铺的数据
 * - 四个小框：总收入、最受欢迎单品、消费最多用户、总支出
 * - 四个大框：Top Spenders（带detail）、Stagnant Inventory（带调价申请）、
 *            Low Stock Alert（带调货申请）、Shop Performance（收入支出明细）
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

// 获取当前Manager的店铺ID
$shopId = $_SESSION['user']['ShopID'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';
$isWarehouse = ($shopType === 'Warehouse');

if (!$shopId) {
    die('Error: Shop ID not found in session.');
}

// 获取店铺级别的Dashboard数据
$dashboardData = prepareDashboardData($pdo, $shopId);

// 提取变量供模板使用
$totalSales = $dashboardData['total_sales'];
$totalExpense = $dashboardData['total_expense'];
$buybackExpense = $dashboardData['buyback_expense'] ?? 0;
$procurementCost = $dashboardData['procurement_cost'] ?? 0;
$popularItem = $dashboardData['popular_item'];
$topSpenderName = $dashboardData['top_spender_name'];
$topCustomers = $dashboardData['top_customers'];
$walkInRevenue = $dashboardData['walk_in_revenue'] ?? ['TotalSpent' => 0, 'OrderCount' => 0];
$deadStock = $dashboardData['dead_stock'];
$lowStock = $dashboardData['low_stock'];
$revenueByType = $dashboardData['revenue_by_type'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="text-warning display-6 fw-bold"><i class="fa-solid fa-chart-line me-2"></i>Store Dashboard</h2>
        <p class="text-secondary">
            Real-time business intelligence for <span class="text-info fw-bold"><?= h($_SESSION['shop_name'] ?? 'Your Store') ?></span>
            <?php if ($isWarehouse): ?>
            <span class="badge bg-secondary ms-2">Warehouse</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- 四个KPI小框 -->
<div class="row g-4 mb-5">
    <!-- 1. Total Revenue -->
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-success h-100">
            <div class="card-body">
                <h6 class="text-success text-uppercase mb-2"><i class="fa-solid fa-dollar-sign me-1"></i>Total Revenue</h6>
                <h3 class="text-white fw-bold"><?= formatPrice($totalSales) ?></h3>
            </div>
        </div>
    </div>
    <!-- 2. Most Popular Item -->
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-info h-100">
            <div class="card-body">
                <h6 class="text-info text-uppercase mb-2"><i class="fa-solid fa-fire me-1"></i>Most Popular</h6>
                <h3 class="text-white fw-bold" style="font-size: 1.1rem;">
                    <?= $popularItem ? h($popularItem['Title']) : 'No Data' ?>
                </h3>
                <?php if ($popularItem): ?>
                <small class="text-muted"><?= $popularItem['TotalSold'] ?> sold</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- 3. Top Spender -->
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-warning h-100">
            <div class="card-body">
                <h6 class="text-warning text-uppercase mb-2"><i class="fa-solid fa-crown me-1"></i>Top Spender</h6>
                <h3 class="text-white fw-bold" style="font-size: 1.1rem;"><?= h($topSpenderName) ?></h3>
            </div>
        </div>
    </div>
    <!-- 4. 【重构】Total Inventory Cost - 历史库存总成本（含已售出） -->
    <div class="col-md-6 col-lg-3">
        <div class="card bg-dark border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger text-uppercase mb-2">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>Total Cost
                </h6>
                <h3 class="text-white fw-bold"><?= formatPrice($totalExpense) ?></h3>
                <small class="text-muted d-block">
                    <i class="fa-solid fa-cube me-1"></i><?= $dashboardData['inventory_count'] ?? 0 ?> items (incl. sold)
                </small>
                <small class="text-info">(Historical cost)</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Spenders 大框 -->
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-warning mb-0"><i class="fa-solid fa-trophy me-2"></i>Top Spenders</h5>
            </div>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-dark table-sm mb-0">
                    <thead class="sticky-top bg-dark">
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Tier</th>
                            <th class="text-end">Total Spent</th>
                            <th class="text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($topCustomers as $c): ?>
                        <tr>
                            <td><span class="badge bg-warning text-dark">#<?= $rank++ ?></span></td>
                            <td><?= h($c['CustomerName']) ?></td>
                            <td><small class="text-muted"><?= h($c['TierName']) ?></small></td>
                            <td class="text-end text-success fw-bold"><?= formatPrice($c['TotalSpent']) ?></td>
                            <td class="text-center">
                                <a href="customer_orders.php?customer_id=<?= $c['CustomerID'] ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topCustomers)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No registered customer sales yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <!-- 【新增】Walk-in Customer单独一行（不参与排名） -->
                    <tfoot class="border-top border-secondary">
                        <tr class="table-secondary bg-opacity-25">
                            <td><span class="badge bg-secondary">-</span></td>
                            <td>
                                <i class="fa-solid fa-user-slash me-1 text-muted"></i>Walk-in Customers
                            </td>
                            <td><small class="text-muted">N/A</small></td>
                            <td class="text-end text-info fw-bold"><?= formatPrice($walkInRevenue['TotalSpent'] ?? 0) ?></td>
                            <td class="text-center">
                                <span class="badge bg-info text-dark"><?= $walkInRevenue['OrderCount'] ?? 0 ?> orders</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Stagnant Inventory 大框 -->
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Stagnant Inventory (>60 Days)</h5>
            </div>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-dark table-sm mb-0">
                    <thead class="sticky-top bg-dark">
                        <tr>
                            <th>Days</th>
                            <th>Album</th>
                            <th>Condition</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deadStock as $d): ?>
                        <tr>
                            <td class="text-danger fw-bold"><?= $d['MaxDaysInStock'] ?>d</td>
                            <td>
                                <div><?= h($d['Title']) ?></div>
                                <small class="text-muted"><?= h($d['ArtistName']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= h($d['ConditionGrade']) ?></span></td>
                            <td class="text-center"><span class="badge bg-warning text-dark"><?= $d['Quantity'] ?></span></td>
                            <td class="text-center">
                                <!-- 【修复】强制调整该condition的全部数量，移除qty参数 -->
                                <a href="requests.php?action=price&release_id=<?= $d['ReleaseID'] ?>&condition=<?= urlencode($d['ConditionGrade']) ?>&price=<?= $d['UnitPrice'] ?>"
                                   class="btn btn-sm btn-outline-warning" title="Request Price Adjustment for ALL <?= $d['Quantity'] ?> units">
                                    <i class="fa-solid fa-tag"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($deadStock)): ?>
                            <tr><td colspan="5" class="text-center text-success py-3">No stagnant inventory found!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Low Stock Alert 大框 -->
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-danger mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Low Stock Alert (< 3 Units)</h5>
            </div>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-dark table-sm mb-0">
                    <thead class="sticky-top bg-dark">
                        <tr>
                            <th>Album</th>
                            <th>Condition</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStock as $ls): ?>
                        <tr>
                            <td>
                                <div><?= h($ls['Title']) ?></div>
                                <small class="text-muted"><?= h($ls['ArtistName']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= h($ls['ConditionGrade']) ?></span></td>
                            <td class="text-center"><span class="badge bg-danger"><?= $ls['AvailableQuantity'] ?></span></td>
                            <td class="text-center">
                                <!-- 【修复】移除店铺选择，由Admin决定从哪个店调货 -->
                                <a href="requests.php?action=transfer&release_id=<?= $ls['ReleaseID'] ?>&condition=<?= urlencode($ls['ConditionGrade']) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Request Stock Transfer (Admin will decide source)">
                                    <i class="fa-solid fa-truck"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lowStock)): ?>
                            <tr><td colspan="4" class="text-center text-success py-3">All items well stocked!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Shop Performance 大框 - 收入支出明细 -->
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-info mb-0"><i class="fa-solid fa-chart-pie me-2"></i>Revenue & Expense Breakdown</h5>
            </div>
            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-dark table-sm mb-0">
                    <thead class="sticky-top bg-dark">
                        <tr>
                            <th>Type</th>
                            <th class="text-center">Count</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // 收入部分
                        $typeLabels = [
                            'OnlineSales' => ['Online Sales (Shipping)', 'fa-globe', 'text-success'],
                            'OnlinePickup' => ['Online Pickup', 'fa-store', 'text-info'],
                            'POS' => ['POS In-Store', 'fa-cash-register', 'text-warning']
                        ];

                        // 根据店铺类型显示不同的收入类型
                        if ($isWarehouse) {
                            // Warehouse只显示Online Sales
                            $displayTypes = ['OnlineSales'];
                        } else {
                            // Retail店铺显示全部三种
                            $displayTypes = ['OnlineSales', 'OnlinePickup', 'POS'];
                        }

                        foreach ($displayTypes as $type):
                            $revenue = null;
                            foreach ($revenueByType as $r) {
                                if ($r['RevenueType'] === $type) {
                                    $revenue = $r;
                                    break;
                                }
                            }
                            $label = $typeLabels[$type];
                        ?>
                        <tr>
                            <td>
                                <i class="fa-solid <?= $label[1] ?> me-2 <?= $label[2] ?>"></i>
                                <?= $label[0] ?>
                            </td>
                            <td class="text-center"><?= $revenue ? $revenue['OrderCount'] : 0 ?></td>
                            <td class="text-end text-success fw-bold">
                                <?= $revenue ? formatPrice($revenue['Revenue'] + ($revenue['TotalShipping'] ?? 0)) : formatPrice(0) ?>
                            </td>
                            <td class="text-center">
                                <a href="order_details.php?type=<?= $type ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fa-solid fa-list"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (!$isWarehouse): ?>
                        <!-- Buyback支出（仅Retail店铺，历史统计） -->
                        <tr class="table-secondary">
                            <td>
                                <i class="fa-solid fa-money-bill-transfer me-2 text-danger"></i>
                                Buyback (Historical)
                            </td>
                            <td class="text-center"><?= $dashboardData['buyback_count'] ?? 0 ?></td>
                            <td class="text-end text-danger fw-bold">-<?= formatPrice($buybackExpense) ?></td>
                            <td class="text-center">
                                <a href="order_details.php?type=buyback" class="btn btn-sm btn-outline-danger">
                                    <i class="fa-solid fa-list"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <!-- 【重构】Total Inventory Cost - 历史库存总成本（含已售出） -->
                        <tr class="table-warning bg-opacity-25">
                            <td>
                                <i class="fa-solid fa-boxes-stacked me-2 text-warning"></i>
                                <strong>Total Inventory Cost</strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-warning text-dark"><?= $dashboardData['inventory_count'] ?? 0 ?> items</span>
                            </td>
                            <td class="text-end text-warning fw-bold"><?= formatPrice($procurementCost) ?></td>
                            <td class="text-center">
                                <!-- 【新增】添加detail按钮 -->
                                <a href="inventory_cost_details.php" class="btn btn-sm btn-outline-warning">
                                    <i class="fa-solid fa-list"></i>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
