<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/header.php';

// --- Report: Inventory Turnover Rate ---
// 计算已售出商品的平均售出天数 (DateSold - DateAdded)
$turnoverSql = "
    SELECT 
        r.Genre,
        COUNT(s.StockItemID) as ItemsSold,
        AVG(DATEDIFF(s.DateSold, s.DateAdded)) as AvgDaysToSell,
        SUM(ol.PriceAtSale) as RevenueGenerated
    FROM StockItem s
    JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
    JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
    WHERE s.Status = 'Sold'
    GROUP BY r.Genre
    ORDER BY AvgDaysToSell ASC
";
$turnoverStats = $pdo->query($turnoverSql)->fetchAll();

// --- Report: Monthly Sales Trend ---
$trendSql = "
    SELECT 
        DATE_FORMAT(OrderDate, '%Y-%m') as SalesMonth,
        COUNT(*) as OrderCount,
        SUM(TotalAmount) as MonthlyRevenue
    FROM CustomerOrder
    WHERE Status != 'Cancelled'
    GROUP BY SalesMonth
    ORDER BY SalesMonth DESC
    LIMIT 12
";
$salesTrend = $pdo->query($trendSql)->fetchAll();
?>

<div class="mb-4">
    <h2 class="text-info"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Performance Reports</h2>
</div>

<div class="card bg-dark border-secondary mb-5">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0">Inventory Turnover by Genre</h5>
        <small class="text-muted">How fast are we selling different types of music?</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Genre</th>
                        <th>Items Sold</th>
                        <th>Avg. Days to Sell</th>
                        <th>Turnover Speed</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($turnoverStats as $stat): ?>
                    <?php 
                        $days = round($stat['AvgDaysToSell'], 1);
                        $speedClass = $days < 30 ? 'text-success' : ($days < 90 ? 'text-warning' : 'text-danger');
                        $speedLabel = $days < 30 ? 'Fast' : ($days < 90 ? 'Moderate' : 'Slow');
                    ?>
                    <tr>
                        <td class="fw-bold"><?= h($stat['Genre']) ?></td>
                        <td><?= $stat['ItemsSold'] ?></td>
                        <td><?= $days ?> days</td>
                        <td class="<?= $speedClass ?> fw-bold"><?= $speedLabel ?></td>
                        <td class="text-end"><?= formatPrice($stat['RevenueGenerated']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0">Monthly Sales Trend</h5>
    </div>
    <div class="card-body">
        <table class="table table-dark table-sm">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Orders</th>
                    <th class="text-end">Revenue</th>
                    <th style="width: 40%;">Visualization</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $maxRev = 1;
                foreach($salesTrend as $t) $maxRev = max($maxRev, $t['MonthlyRevenue']);
                
                foreach ($salesTrend as $trend): 
                    $percent = ($trend['MonthlyRevenue'] / $maxRev) * 100;
                ?>
                <tr>
                    <td><?= $trend['SalesMonth'] ?></td>
                    <td><?= $trend['OrderCount'] ?></td>
                    <td class="text-end"><?= formatPrice($trend['MonthlyRevenue']) ?></td>
                    <td>
                        <div class="progress bg-secondary" style="height: 10px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= $percent ?>%"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>