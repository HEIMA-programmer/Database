<?php
/**
 * Warehouse Stock Dispatch Page - Admin Version
 * Dispatch inventory from warehouse to retail shops
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

// 获取Warehouse ID
$warehouseId = getShopIdByType($pdo, 'Warehouse');

// 【架构重构Phase2】使用DBProcedures替换直接SQL
$retailShops = DBProcedures::getRetailShops($pdo);

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispatch_stock'])) {
    $releaseId = (int)$_POST['release_id'];
    $conditionGrade = $_POST['condition_grade'];
    $quantity = (int)$_POST['quantity'];
    $targetShopId = (int)$_POST['target_shop_id'];
    $employeeId = $_SESSION['user_id'];

    // 验证目标店铺
    $validShop = false;
    foreach ($retailShops as $shop) {
        if ($shop['ShopID'] == $targetShopId) {
            $validShop = true;
            break;
        }
    }

    if (!$validShop) {
        flash("Invalid target shop.", 'danger');
    } elseif ($quantity < 1) {
        flash("Quantity must be at least 1.", 'danger');
    } else {
        // 【新增】使用带确认流程的调配方法
        // 创建调拨记录，需要仓库员工确认发货后才能完成
        $initiatedCount = DBProcedures::initiateWarehouseDispatch(
            $pdo,
            $warehouseId,
            $targetShopId,
            $releaseId,
            $conditionGrade,
            $quantity,
            $employeeId
        );

        if ($initiatedCount > 0) {
            // 获取目标店铺名称
            $targetShopName = '';
            foreach ($retailShops as $shop) {
                if ($shop['ShopID'] == $targetShopId) {
                    $targetShopName = $shop['Name'];
                    break;
                }
            }

            flash("Created dispatch request for $initiatedCount item(s) to $targetShopName. Waiting for warehouse staff to confirm shipment.", 'success');
        } else {
            flash("Dispatch failed: Insufficient stock or an error occurred.", 'danger');
        }
    }

    header("Location: warehouse_dispatch.php");
    exit();
}

// ========== 获取Warehouse库存数据 ==========
// 【架构重构Phase2】使用DBProcedures替换直接SQL
$warehouseStock = $warehouseId ? DBProcedures::getWarehouseStock($pdo, $warehouseId) : [];

// 获取专辑列表用于下拉菜单
$releaseList = DBProcedures::getReleaseList($pdo);

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<?php if (!$warehouseId): ?>
    <div class='alert alert-danger'>
        <i class="fa-solid fa-exclamation-triangle me-2"></i>
        Critical Configuration Error: 'Warehouse' shop type not found in database.
    </div>
<?php else: ?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="text-warning mb-1">
                <i class="fa-solid fa-warehouse me-2"></i>Warehouse Dispatch
            </h2>
            <p class="text-secondary mb-0">Dispatch stock from warehouse to retail shops</p>
        </div>
        <a href="procurement.php" class="btn btn-outline-info">
            <i class="fa-solid fa-arrow-left me-2"></i>Back to Procurement
        </a>
    </div>
</div>

<!-- Stock Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-dark border-warning">
            <div class="card-body text-center">
                <h3 class="text-warning mb-0"><?= count($warehouseStock) ?></h3>
                <small class="text-muted">Stock Varieties (Release/Condition)</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-info">
            <div class="card-body text-center">
                <h3 class="text-info mb-0"><?= array_sum(array_column($warehouseStock, 'Quantity')) ?></h3>
                <small class="text-muted">Total Stock Quantity</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-success">
            <div class="card-body text-center">
                <h3 class="text-success mb-0"><?= count($retailShops) ?></h3>
                <small class="text-muted">Retail Shops</small>
            </div>
        </div>
    </div>
</div>

<!-- Warehouse Stock Table -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <h5 class="card-title text-white mb-0">
            <i class="fa-solid fa-boxes-stacked me-2"></i>Warehouse Available Stock
        </h5>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Album</th>
                    <th>Artist</th>
                    <th>Condition</th>
                    <th class="text-center">Available Qty</th>
                    <th class="text-end">Price</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($warehouseStock)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fa-solid fa-inbox me-2"></i>No stock in warehouse
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($warehouseStock as $stock): ?>
                    <tr>
                        <td class="fw-bold"><?= h($stock['Title']) ?></td>
                        <td><?= h($stock['ArtistName']) ?></td>
                        <td><span class="badge bg-secondary"><?= h($stock['ConditionGrade']) ?></span></td>
                        <td class="text-center">
                            <span class="badge bg-info"><?= $stock['Quantity'] ?></span>
                        </td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($stock['UnitPrice']) ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-warning dispatch-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#dispatchModal"
                                    data-release-id="<?= $stock['ReleaseID'] ?>"
                                    data-release-title="<?= h($stock['Title']) ?>"
                                    data-condition="<?= h($stock['ConditionGrade']) ?>"
                                    data-max-qty="<?= $stock['Quantity'] ?>">
                                <i class="fa-solid fa-truck me-1"></i>Dispatch
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- Dispatch Modal -->
<div class="modal fade" id="dispatchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fa-solid fa-truck text-warning me-2"></i>Dispatch Stock to Shop
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="dispatch_stock" value="1">
                    <input type="hidden" name="release_id" id="dispatch_release_id">
                    <input type="hidden" name="condition_grade" id="dispatch_condition">

                    <div class="alert alert-info">
                        <strong>Album:</strong> <span id="dispatch_title"></span><br>
                        <strong>Condition:</strong> <span id="dispatch_condition_display"></span><br>
                        <strong>Available Qty:</strong> <span id="dispatch_max_qty"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Target Shop</label>
                        <select name="target_shop_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">Select shop...</option>
                            <?php foreach ($retailShops as $shop): ?>
                                <option value="<?= $shop['ShopID'] ?>"><?= h($shop['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" id="dispatch_quantity"
                               class="form-control bg-dark text-white border-secondary"
                               min="1" value="1" required>
                        <small class="text-muted">Maximum available: <span id="dispatch_max_qty_hint">-</span></small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <strong>Dispatch Process:</strong><br>
                        1. Admin creates dispatch request (current step)<br>
                        2. Warehouse staff confirms shipment<br>
                        3. Target shop staff confirms receipt<br>
                        Stock will be transferred to target shop after completion.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">
                        <i class="fa-solid fa-truck me-1"></i>Confirm Dispatch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.dispatch-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const releaseId = this.dataset.releaseId;
            const releaseTitle = this.dataset.releaseTitle;
            const condition = this.dataset.condition;
            const maxQty = parseInt(this.dataset.maxQty);

            document.getElementById('dispatch_release_id').value = releaseId;
            document.getElementById('dispatch_condition').value = condition;
            document.getElementById('dispatch_title').textContent = releaseTitle;
            document.getElementById('dispatch_condition_display').textContent = condition;
            document.getElementById('dispatch_max_qty').textContent = maxQty;
            document.getElementById('dispatch_max_qty_hint').textContent = maxQty;

            const qtyInput = document.getElementById('dispatch_quantity');
            qtyInput.max = maxQty;
            qtyInput.value = Math.min(1, maxQty);
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
