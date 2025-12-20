<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['Manager', 'Admin']);
require_once __DIR__ . '/../../includes/header.php';

// 报表 1: 按流派统计销售额 (Sales by Genre)
$sqlGenre = "SELECT r.Genre, COUNT(ol.OrderLineID) as UnitsSold, SUM(ol.PriceAtSale) as TotalRevenue
             FROM OrderLine ol
             JOIN StockItem s ON ol.StockItemID = s.StockItemID
             JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID
             JOIN CustomerOrder co ON ol.OrderID = co.OrderID
             WHERE co.OrderStatus IN ('Paid', 'Completed')
             GROUP BY r.Genre
             ORDER BY TotalRevenue DESC";
$genreReport = $pdo->query($sqlGenre)->fetchAll();

// 报表 2: 员工进货/回购绩效 (Staff Activity)
// 统计每个员工经手的 StockItem 数量 (通过 BatchNo 或 created_by 追踪，这里简单统计回购量)
// 注意：当前 schema 只有 Buyback 会生成带有 'BUYBACK' 的 BatchNo，且没有直接存储 EmployeeID 在 StockItem
// 我们做一个近似查询：统计各个店铺的库存量和价值
$sqlShop = "SELECT sh.Name as ShopName, COUNT(s.StockItemID) as TotalItems, SUM(s.UnitPrice) as InventoryValue
            FROM Shop sh
            LEFT JOIN StockItem s ON sh.ShopID = s.ShopID AND s.Status = 'Available'
            GROUP BY sh.Name";
$shopReport = $pdo->query($sqlShop)->fetchAll();

// 报表 3: 近期高价值订单 (High Value Orders)
$sqlOrders = "SELECT co.OrderID, co.OrderDate, c.Name as Customer, co.TotalAmount, co.OrderType
              FROM CustomerOrder co
              JOIN Customer c ON co.CustomerID = c.CustomerID
              WHERE co.TotalAmount > 500
              ORDER BY co.TotalAmount DESC
              LIMIT 10";
$highValReport = $pdo->query($sqlOrders)->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning">Business Reports</h2>
    <button onclick="window.print()" class="btn btn-outline-light"><i class="fa-solid fa-print me-2"></i>Print Reports</button>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary text-info">
                <i class="fa-solid fa-music me-2"></i>Sales Performance by Genre
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Genre</th><th class="text-end">Units</th><th class="text-end">Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach($genreReport as $row): ?>
                        <tr>
                            <td><?= h($row['Genre']) ?></td>
                            <td class="text-end"><?= $row['UnitsSold'] ?></td>
                            <td class="text-end text-warning"><?= formatPrice($row['TotalRevenue']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary text-success">
                <i class="fa-solid fa-shop me-2"></i>Inventory Assets by Location
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead><tr><th>Location</th><th class="text-end">Items In Stock</th><th class="text-end">Total Value</th></tr></thead>
                    <tbody>
                        <?php foreach($shopReport as $row): ?>
                        <tr>
                            <td><?= h($row['ShopName']) ?></td>
                            <td class="text-end"><?= number_format($row['TotalItems']) ?></td>
                            <td class="text-end text-success"><?= formatPrice($row['InventoryValue']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary text-warning">
                <i class="fa-solid fa-trophy me-2"></i>Recent High-Value Orders (> ¥500)
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead><tr><th>ID</th><th>Date</th><th>Customer</th><th>Type</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach($highValReport as $row): ?>
                        <tr>
                            <td>#<?= $row['OrderID'] ?></td>
                            <td><?= $row['OrderDate'] ?></td>
                            <td><?= h($row['Customer']) ?></td>
                            <td><span class="badge bg-secondary"><?= $row['OrderType'] ?></span></td>
                            <td class="text-end text-warning fw-bold"><?= formatPrice($row['TotalAmount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>