<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole(['Staff', 'Manager']);
require_once __DIR__ . '/../../includes/header.php';

$shopId = $_SESSION['shop_id'];

// 查询本店库存
$sql = "SELECT s.*, r.Title, r.ArtistName 
        FROM StockItem s 
        JOIN ReleaseAlbum r ON s.ReleaseID = r.ReleaseID 
        WHERE s.ShopID = :shop AND s.Status = 'Available' 
        ORDER BY s.AcquiredDate DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':shop' => $shopId]);
$inventory = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-boxes-stacked me-2"></i>Local Inventory</h2>
    <span class="badge bg-secondary fs-6"><?= count($inventory) ?> Items in Stock</span>
</div>

<div class="card bg-dark border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Batch No</th>
                    <th>Album</th>
                    <th>Condition</th>
                    <th>Price</th>
                    <th>Days in Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): ?>
                <tr>
                    <td class="font-monospace text-info"><?= h($item['BatchNo']) ?></td>
                    <td>
                        <div class="fw-bold"><?= h($item['Title']) ?></div>
                        <div class="small text-muted"><?= h($item['ArtistName']) ?></div>
                    </td>
                    <td><?= h($item['ConditionGrade']) ?></td>
                    <td class="text-warning"><?= formatPrice($item['UnitPrice']) ?></td>
                    <td>
                        <?php 
                        $days = floor((time() - strtotime($item['AcquiredDate'])) / (60 * 60 * 24));
                        echo $days;
                        // Assignment 1.3.2: 60-day turnover target
                        if ($days > 60) echo ' <span class="badge bg-danger ms-1">Slow</span>';
                        ?>
                    </td>
                    <td><span class="badge bg-success">Available</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>