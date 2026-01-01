<?php
/**
 * 库存成本明细 - Manager查看
 * 显示历史已交易的release成本和现有库存中的商品成本
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');

// 【修复】兼容多种session结构
$shopId = $_SESSION['user']['ShopID'] ?? $_SESSION['shop_id'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';

if (!$shopId) {
    flash('Shop ID not found in session. Please re-login.', 'warning');
    header('Location: dashboard.php');
    exit;
}

// 获取库存成本明细
$soldInventory = DBProcedures::getShopSoldInventoryCost($pdo, $shopId);
$currentInventory = DBProcedures::getShopCurrentInventoryCost($pdo, $shopId);

// 计算总计
$soldTotal = array_sum(array_column($soldInventory, 'TotalCost'));
$currentTotal = array_sum(array_column($currentInventory, 'TotalCost'));
$grandTotal = $soldTotal + $currentTotal;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-warning">Dashboard</a></li>
                <li class="breadcrumb-item active text-light">Inventory Cost Details</li>
            </ol>
        </nav>
        <h2 class="text-warning display-6 fw-bold">
            <i class="fa-solid fa-boxes-stacked me-2"></i>Inventory Cost Breakdown
        </h2>
        <p class="text-secondary">
            Complete cost breakdown for <?= h($_SESSION['shop_name'] ?? 'this store') ?>
            <span class="badge bg-warning text-dark ms-2">Total: <?= formatPrice($grandTotal) ?></span>
        </p>
    </div>
</div>

<!-- 成本摘要卡片 -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-dark border-success h-100">
            <div class="card-body text-center">
                <h6 class="text-success text-uppercase mb-2">
                    <i class="fa-solid fa-check-circle me-1"></i>Sold Items Cost
                </h6>
                <h3 class="text-white fw-bold"><?= formatPrice($soldTotal) ?></h3>
                <small class="text-muted"><?= count($soldInventory) ?> records</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-info h-100">
            <div class="card-body text-center">
                <h6 class="text-info text-uppercase mb-2">
                    <i class="fa-solid fa-warehouse me-1"></i>Current Stock Cost
                </h6>
                <h3 class="text-white fw-bold"><?= formatPrice($currentTotal) ?></h3>
                <small class="text-muted"><?= count($currentInventory) ?> items</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-warning h-100">
            <div class="card-body text-center">
                <h6 class="text-warning text-uppercase mb-2">
                    <i class="fa-solid fa-calculator me-1"></i>Total Inventory Cost
                </h6>
                <h3 class="text-white fw-bold"><?= formatPrice($grandTotal) ?></h3>
                <small class="text-muted">Historical total</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- 已售商品成本 -->
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-success mb-0">
                    <i class="fa-solid fa-check-circle me-2"></i>Sold Items Cost (Historical)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($soldInventory)): ?>
                    <div class="p-4 text-center text-muted">No sold items yet.</div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-dark table-sm mb-0">
                            <thead class="sticky-top bg-dark">
                                <tr>
                                    <th>Album</th>
                                    <th>Condition</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total</th>
                                    <th>Sold Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soldInventory as $item): ?>
                                <tr>
                                    <td>
                                        <div class="text-white"><?= h($item['Title']) ?></div>
                                        <small class="text-muted"><?= h($item['ArtistName']) ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= h($item['ConditionGrade']) ?></span></td>
                                    <td class="text-center"><?= $item['Quantity'] ?? 1 ?></td>
                                    <td class="text-end"><?= formatPrice($item['UnitCost']) ?></td>
                                    <td class="text-end text-success fw-bold"><?= formatPrice($item['TotalCost']) ?></td>
                                    <td><small class="text-muted"><?= formatDate($item['DateSold']) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="border-top border-secondary">
                                <tr class="table-success bg-opacity-25">
                                    <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end text-success fw-bold"><?= formatPrice($soldTotal) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 当前库存成本 -->
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary bg-transparent">
                <h5 class="card-title text-info mb-0">
                    <i class="fa-solid fa-warehouse me-2"></i>Current Stock Cost
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($currentInventory)): ?>
                    <div class="p-4 text-center text-muted">No current stock.</div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-dark table-sm mb-0">
                            <thead class="sticky-top bg-dark">
                                <tr>
                                    <th>Album</th>
                                    <th>Condition</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentInventory as $item): ?>
                                <tr>
                                    <td>
                                        <div class="text-white"><?= h($item['Title']) ?></div>
                                        <small class="text-muted"><?= h($item['ArtistName']) ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= h($item['ConditionGrade']) ?></span></td>
                                    <td class="text-center"><?= $item['Quantity'] ?? 1 ?></td>
                                    <td class="text-end"><?= formatPrice($item['UnitCost']) ?></td>
                                    <td class="text-end text-info fw-bold"><?= formatPrice($item['TotalCost']) ?></td>
                                    <td>
                                        <?php if ($item['SourceType'] === 'Buyback'): ?>
                                            <span class="badge bg-warning text-dark">Buyback</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Supplier</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="border-top border-secondary">
                                <tr class="table-info bg-opacity-25">
                                    <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end text-info fw-bold"><?= formatPrice($currentTotal) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
