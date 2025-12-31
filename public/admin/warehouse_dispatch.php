<?php
/**
 * 【新增】Warehouse库存调配页面 - Admin版
 * 用于将warehouse中的库存调配到长沙店和上海店
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

            flash("已创建 $initiatedCount 件商品到 $targetShopName 的调拨请求。请等待仓库员工确认发货。", 'success');
        } else {
            flash("调配失败：库存不足或发生错误。", 'danger');
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
            <p class="text-secondary mb-0">从仓库调配库存到各零售店铺（长沙店/上海店）</p>
        </div>
        <a href="procurement.php" class="btn btn-outline-info">
            <i class="fa-solid fa-arrow-left me-2"></i>返回采购管理
        </a>
    </div>
</div>

<!-- 库存统计卡片 -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-dark border-warning">
            <div class="card-body text-center">
                <h3 class="text-warning mb-0"><?= count($warehouseStock) ?></h3>
                <small class="text-muted">库存种类（Release/Condition）</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-info">
            <div class="card-body text-center">
                <h3 class="text-info mb-0"><?= array_sum(array_column($warehouseStock, 'Quantity')) ?></h3>
                <small class="text-muted">总库存数量</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-dark border-success">
            <div class="card-body text-center">
                <h3 class="text-success mb-0"><?= count($retailShops) ?></h3>
                <small class="text-muted">零售店铺数量</small>
            </div>
        </div>
    </div>
</div>

<!-- Warehouse库存表格 -->
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
                    <th>专辑</th>
                    <th>艺术家</th>
                    <th>成色</th>
                    <th class="text-center">可用数量</th>
                    <th class="text-end">售价</th>
                    <th class="text-center">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($warehouseStock)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fa-solid fa-inbox me-2"></i>仓库暂无库存
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
                                <i class="fa-solid fa-truck me-1"></i>调配
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
                    <i class="fa-solid fa-truck text-warning me-2"></i>调配库存到店铺
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="dispatch_stock" value="1">
                    <input type="hidden" name="release_id" id="dispatch_release_id">
                    <input type="hidden" name="condition_grade" id="dispatch_condition">

                    <div class="alert alert-info">
                        <strong>专辑:</strong> <span id="dispatch_title"></span><br>
                        <strong>成色:</strong> <span id="dispatch_condition_display"></span><br>
                        <strong>可用数量:</strong> <span id="dispatch_max_qty"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">目标店铺</label>
                        <select name="target_shop_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">选择店铺...</option>
                            <?php foreach ($retailShops as $shop): ?>
                                <option value="<?= $shop['ShopID'] ?>"><?= h($shop['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">调配数量</label>
                        <input type="number" name="quantity" id="dispatch_quantity"
                               class="form-control bg-dark text-white border-secondary"
                               min="1" value="1" required>
                        <small class="text-muted">最大可调配: <span id="dispatch_max_qty_hint">-</span></small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <strong>调配流程说明：</strong><br>
                        1. Admin创建调配请求（当前步骤）<br>
                        2. 仓库员工确认发货<br>
                        3. 目标店铺员工确认收货<br>
                        调拨完成后库存才会正式转移到目标店铺。
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-warning fw-bold">
                        <i class="fa-solid fa-truck me-1"></i>确认调配
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
