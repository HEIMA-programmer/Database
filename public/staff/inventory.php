<?php
/**
 * 【架构重构】库存管理页面
 * 表现层 - 仅负责数据展示和用户交互
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Staff', 'Manager']);

// ========== 数据准备 ==========
$shopId = $_SESSION['shop_id'];
$viewMode = $_GET['view'] ?? 'summary';

$pageData = prepareInventoryPageData($pdo, $shopId, $viewMode);
$inventory = $pageData['inventory'];
$totalItems = $pageData['total_items'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-boxes-stacked me-2"></i>Local Inventory</h2>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary fs-6"><?= $totalItems ?> Items in Stock</span>
        <div class="btn-group" role="group">
            <a href="?view=detail" class="btn btn-sm <?= $viewMode === 'detail' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="fa-solid fa-list me-1"></i>Detail
            </a>
            <a href="?view=summary" class="btn btn-sm <?= $viewMode === 'summary' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="fa-solid fa-chart-bar me-1"></i>Summary
            </a>
        </div>
    </div>
</div>

<?php if ($viewMode === 'summary'): ?>
<div class="card bg-dark border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Album</th>
                    <th>Genre</th>
                    <th>Condition</th>
                    <th class="text-center">Quantity</th>
                    <th>Price Range</th>
                    <th>Avg Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= h($item['Title']) ?></div>
                        <div class="small text-muted"><?= h($item['ArtistName']) ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?= h($item['Genre']) ?></span></td>
                    <td><?= h($item['ConditionGrade']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $item['AvailableQuantity'] < 3 ? 'bg-danger' : 'bg-success' ?> fs-6">
                            <?= $item['AvailableQuantity'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($item['MinPrice'] == $item['MaxPrice']): ?>
                            <span class="text-warning"><?= formatPrice($item['MinPrice']) ?></span>
                        <?php else: ?>
                            <span class="text-warning"><?= formatPrice($item['MinPrice']) ?></span>
                            <span class="text-muted">~</span>
                            <span class="text-warning"><?= formatPrice($item['MaxPrice']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-info"><?= formatPrice($item['AvgPrice']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($inventory)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No inventory items found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
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
                        $days = $item['DaysInStock'] ?? floor((time() - strtotime($item['AcquiredDate'])) / (60 * 60 * 24));
                        echo $days;
                        if ($days > 60) echo ' <span class="badge bg-danger ms-1">Slow</span>';
                        ?>
                    </td>
                    <td><span class="badge bg-success">Available</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($inventory)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No inventory items found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
