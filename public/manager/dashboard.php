<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['Manager', 'Admin']);
require_once __DIR__ . '/../../includes/header.php';

// --- 执行高级查询 (Advanced Queries) ---

// Q1: 库存周转率 (Inventory Turnover)
$sqlTurnover = "SELECT r.Genre, COUNT(s.StockItemID) as TotalItemsSold, AVG(DATEDIFF(co.OrderDate, s.AcquiredDate)) as AvgDaysToSell
                FROM StockItem s
                JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
                JOIN OrderLine ol ON s.StockItemID = ol.StockItemID
                JOIN CustomerOrder co ON ol.OrderID = co.OrderID
                WHERE s.Status = 'Sold'
                GROUP BY r.Genre
                ORDER BY AvgDaysToSell ASC";
$turnoverData = $pdo->query($sqlTurnover)->fetchAll();

// Q2: VIP 客户消费排行 (VIP Ranking)
$sqlVIP = "SELECT c.Name, mt.TierName, SUM(co.TotalAmount) as TotalSpent
           FROM Customer c
           JOIN MembershipTier mt ON c.TierID = mt.TierID
           JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
           WHERE co.OrderStatus IN ('Paid', 'Completed')
           GROUP BY c.CustomerID, c.Name, mt.TierName
           ORDER BY TotalSpent DESC
           LIMIT 5";
$vipData = $pdo->query($sqlVIP)->fetchAll();

// Q4: 每月销售趋势 (用于图表)
$sqlTrend = "SELECT DATE_FORMAT(OrderDate, '%Y-%m') as SalesMonth, SUM(TotalAmount) as TotalRevenue
             FROM CustomerOrder
             WHERE OrderStatus IN ('Paid', 'Completed')
             GROUP BY SalesMonth
             ORDER BY SalesMonth ASC
             LIMIT 12";
$trendData = $pdo->query($sqlTrend)->fetchAll();

// Q5: 滞销预警 (Dead Stock Alert - >60 Days)
$sqlDeadStock = "SELECT sh.Name as ShopName, r.Title, s.BatchNo, DATEDIFF(CURRENT_DATE, s.AcquiredDate) as DaysInStock
                 FROM StockItem s
                 JOIN Shop sh ON s.ShopID = sh.ShopID
                 JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
                 WHERE s.Status = 'Available'
                 HAVING DaysInStock > 60
                 ORDER BY DaysInStock DESC
                 LIMIT 10";
$deadStockData = $pdo->query($sqlDeadStock)->fetchAll();
?>

<h2 class="text-warning mb-4"><i class="fa-solid fa-chart-line me-2"></i>Analytics Dashboard</h2>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-secondary text-light">
            <div class="card-body text-center">
                <h6 class="text-muted">Total Revenue (All Time)</h6>
                <?php 
                $rev = $pdo->query("SELECT SUM(TotalAmount) FROM CustomerOrder WHERE OrderStatus IN ('Paid','Completed')")->fetchColumn(); 
                ?>
                <h3 class="text-warning"><?= formatPrice($rev) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-light">
            <div class="card-body text-center">
                <h6 class="text-muted">Items in Stock</h6>
                <?php 
                $stk = $pdo->query("SELECT COUNT(*) FROM StockItem WHERE Status='Available'")->fetchColumn(); 
                ?>
                <h3 class="text-info"><?= $stk ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header border-secondary fw-bold">
                <i class="fa-solid fa-arrow-trend-up me-2"></i>Monthly Sales Trend
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>

        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary fw-bold text-info">
                <i class="fa-solid fa-rotate me-2"></i>Inventory Efficiency (By Genre)
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-striped mb-0">
                    <thead><tr><th>Genre</th><th>Sold Qty</th><th>Avg Days to Sell</th></tr></thead>
                    <tbody>
                        <?php foreach($turnoverData as $row): ?>
                        <tr>
                            <td><?= h($row['Genre']) ?></td>
                            <td><?= $row['TotalItemsSold'] ?></td>
                            <td><?= number_format($row['AvgDaysToSell'], 1) ?> days</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card bg-dark border-danger mb-4">
            <div class="card-header bg-danger text-white fw-bold">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>Dead Stock Alert (>60 Days)
            </div>
            <ul class="list-group list-group-flush bg-dark">
                <?php foreach($deadStockData as $row): ?>
                <li class="list-group-item bg-dark text-light border-secondary">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-truncate" style="max-width: 150px;"><?= h($row['Title']) ?></span>
                        <span class="badge bg-danger"><?= $row['DaysInStock'] ?> days</span>
                    </div>
                    <small class="text-muted"><?= h($row['ShopName']) ?> | Batch: <?= h($row['BatchNo']) ?></small>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card bg-dark border-warning">
            <div class="card-header bg-warning text-dark fw-bold">
                <i class="fa-solid fa-crown me-2"></i>Top VIP Customers
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Name</th><th>Tier</th><th>Spent</th></tr></thead>
                    <tbody>
                        <?php foreach($vipData as $row): ?>
                        <tr>
                            <td><?= h($row['Name']) ?></td>
                            <td><span class="badge bg-secondary"><?= h($row['TierName']) ?></span></td>
                            <td class="text-warning"><?= formatPrice($row['TotalSpent']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($trendData, 'SalesMonth')) ?>,
            datasets: [{
                label: 'Revenue (CNY)',
                data: <?= json_encode(array_column($trendData, 'TotalRevenue')) ?>,
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.2)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            scales: {
                y: { grid: { color: '#333' }, ticks: { color: '#aaa' } },
                x: { grid: { color: '#333' }, ticks: { color: '#aaa' } }
            },
            plugins: { legend: { labels: { color: '#fff' } } }
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>