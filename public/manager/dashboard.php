<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/header.php';

// --- Advanced SQL 1: Top Spending VIPs (using Window Function) ---
// 使用 DENSE_RANK() 对客户消费进行排名
$vipSql = "
    SELECT * FROM (
        SELECT
            c.Name,
            c.Email,
            mt.TierName,
            SUM(co.TotalAmount) as TotalSpent,
            DENSE_RANK() OVER (ORDER BY SUM(co.TotalAmount) DESC) as RankPosition
        FROM Customer c
        JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
        JOIN MembershipTier mt ON c.TierID = mt.TierID
        WHERE co.OrderStatus != 'Cancelled'
        GROUP BY c.CustomerID, c.Name, c.Email, mt.TierName
    ) as RankedCustomers
    WHERE RankPosition <= 5
";
$vips = $pdo->query($vipSql)->fetchAll();

// [Robustness Fix] 获取第一名 VIP 用于卡片展示，如果为空则提供默认值
$topVipName = !empty($vips) ? $vips[0]['Name'] : 'No Data';

// --- Advanced SQL 2: Dead Stock Alert ---
// 找出进货超过 60 天但从未卖出过的商品
$deadStockSql = "
    SELECT
        r.Title,
        r.ArtistName,
        s.BatchNo,
        s.AcquiredDate,
        DATEDIFF(NOW(), s.AcquiredDate) as DaysInStock
    FROM StockItem s
    JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
    WHERE s.Status = 'Available'
    AND s.AcquiredDate < DATE_SUB(NOW(), INTERVAL 60 DAY)
    ORDER BY s.AcquiredDate ASC
    LIMIT 10
";
$deadStock = $pdo->query($deadStockSql)->fetchAll();

// --- KPI Stats ---
$totalSales = $pdo->query("SELECT SUM(TotalAmount) FROM CustomerOrder WHERE OrderStatus != 'Cancelled'")->fetchColumn() ?: 0.00;
$activeOrders = $pdo->query("SELECT COUNT(*) FROM CustomerOrder WHERE OrderStatus IN ('Pending', 'Paid', 'Shipped')")->fetchColumn();
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>