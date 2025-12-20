<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['Manager', 'Admin']);
require_once __DIR__ . '/../../includes/header.php';

// --- 数据查询 ---
// 1. KPI: 总收入
$rev = $pdo->query("SELECT SUM(TotalAmount) FROM CustomerOrder WHERE OrderStatus IN ('Paid','Completed')")->fetchColumn();
// 2. KPI: 库存总量
$stk = $pdo->query("SELECT COUNT(*) FROM StockItem WHERE Status='Available'")->fetchColumn();
// 3. KPI: 待处理订单
$pending = $pdo->query("SELECT COUNT(*) FROM CustomerOrder WHERE OrderStatus='Pending'")->fetchColumn();
// 4. KPI: 会员总数
$members = $pdo->query("SELECT COUNT(*) FROM Customer")->fetchColumn();

// 高级查询: 销售趋势
$sqlTrend = "SELECT DATE_FORMAT(OrderDate, '%Y-%m') as SalesMonth, SUM(TotalAmount) as TotalRevenue
             FROM CustomerOrder
             WHERE OrderStatus IN ('Paid', 'Completed')
             GROUP BY SalesMonth
             ORDER BY SalesMonth ASC
             LIMIT 12";
$trendData = $pdo->query($sqlTrend)->fetchAll();

// 高级查询: 滞销库存
$sqlDeadStock = "SELECT sh.Name as ShopName, r.Title, s.BatchNo, DATEDIFF(CURRENT_DATE, s.AcquiredDate) as DaysInStock
                 FROM StockItem s
                 JOIN Shop sh ON s.ShopID = sh.ShopID
                 JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
                 WHERE s.Status = 'Available'
                 HAVING DaysInStock > 60
                 ORDER BY DaysInStock DESC
                 LIMIT 5";
$deadStockData = $pdo->query($sqlDeadStock)->fetchAll();

// 高级查询: VIP 排行
$sqlVIP = "SELECT c.Name, mt.TierName, SUM(co.TotalAmount) as TotalSpent
           FROM Customer c
           JOIN MembershipTier mt ON c.TierID = mt.TierID
           JOIN CustomerOrder co ON c.CustomerID = co.CustomerID
           WHERE co.OrderStatus IN ('Paid', 'Completed')
           GROUP BY c.CustomerID, c.Name, mt.TierName
           ORDER BY TotalSpent DESC
           LIMIT 5";
$vipData = $pdo->query($sqlVIP)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning mb-0">Dashboard</h2>
    <span class="text-muted"><i class="fa-regular fa-calendar me-2"></i><?= date('F j, Y') ?></span>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="card stats-card h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">Total Revenue</h6>
                <h2 class="text-warning mb-0"><?= formatPrice($rev) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card h-100" style="border-left-color: #03dac6;">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">Active Inventory</h6>
                <h2 class="text-white mb-0"><?= number_format($stk) ?> <small class="fs-6 text-muted">items</small></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card h-100" style="border-left-color: #cf6679;">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">Pending Orders</h6>
                <h2 class="text-white mb-0"><?= number_format($pending) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card h-100" style="border-left-color: #bb86fc;">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">Club Members</h6>
                <h2 class="text-white mb-0"><?= number_format($members) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-arrow-trend-up me-2 text-warning"></i>Monthly Revenue Trend</span>
            </div>
            <div class="card-body">
                <canvas id="salesChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100 border-danger">
            <div class="card-header bg-danger text-white">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>Dead Stock Alert (>60 Days)
            </div>
            <ul class="list-group list-group-flush bg-dark">
                <?php if(empty($deadStockData)): ?>
                    <li class="list-group-item bg-dark text-muted text-center py-4">Inventory is healthy.</li>
                <?php else: ?>
                    <?php foreach($deadStockData as $row): ?>
                    <li class="list-group-item bg-dark text-light border-secondary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-truncate me-2">
                                <div class="fw-bold"><?= h($row['Title']) ?></div>
                                <small class="text-muted"><?= h($row['ShopName']) ?></small>
                            </div>
                            <span class="badge bg-danger rounded-pill"><?= $row['DaysInStock'] ?> days</span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header border-warning text-warning">
                <i class="fa-solid fa-crown me-2"></i>Top VIP Customers
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead><tr><th>Customer</th><th>Tier</th><th>Total Spent</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($vipData as $row): ?>
                        <tr>
                            <td class="fw-bold"><?= h($row['Name']) ?></td>
                            <td><span class="badge bg-secondary border border-warning text-warning"><?= h($row['TierName']) ?></span></td>
                            <td class="text-success fw-bold"><?= formatPrice($row['TotalSpent']) ?></td>
                            <td><i class="fa-solid fa-star text-warning"></i> Loyal</td>
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
    // Dark Theme Chart Configuration
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(212, 175, 55, 0.5)');
    gradient.addColorStop(1, 'rgba(212, 175, 55, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($trendData, 'SalesMonth')) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_column($trendData, 'TotalRevenue')) ?>,
                borderColor: '#d4af37',
                backgroundColor: gradient,
                borderWidth: 2,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#d4af37',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#333',
                    titleColor: '#d4af37',
                    bodyColor: '#fff',
                    borderColor: '#444',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    grid: { color: '#333' },
                    ticks: { color: '#888', callback: function(value) { return '¥' + value; } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#888' }
                }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>