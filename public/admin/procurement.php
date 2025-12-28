<?php
/**
 * 【架构重构】采购管理页面 - Admin版
 * 【修改】Unit cost固定在代码中，sale price按比率上浮给出建议
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

// ========== 【新增】固定Unit Cost配置 ==========
// 每种专辑的固定采购成本（元）
// 根据专辑稀有度、热门程度等因素定义
$UNIT_COST_CONFIG = [
    // ReleaseID => unit_cost
    // 默认按专辑类型或者统一设定一个基础成本
    // 实际项目中可以根据专辑属性动态计算
];

// 默认Unit Cost（如果专辑没有配置，则使用此默认值）
$DEFAULT_UNIT_COSTS = [
    'vinyl_standard' => 25.00,  // 标准黑胶
    'vinyl_rare'     => 50.00,  // 稀有版本
    'vinyl_import'   => 80.00,  // 进口版
    'cd_standard'    => 15.00,  // 标准CD
];

/**
 * 根据Unit Cost计算建议售价
 * 低价产品：低比率上浮（薄利多销）
 * 高价产品：高比率上浮（单品利润大）
 */
function getSuggestedSalePrice($unitCost) {
    if ($unitCost <= 20) {
        // 低价产品：上浮50%（薄利多销）
        return $unitCost * 1.50;
    } elseif ($unitCost <= 50) {
        // 中低价产品：上浮60%
        return $unitCost * 1.60;
    } elseif ($unitCost <= 100) {
        // 中价产品：上浮70%
        return $unitCost * 1.70;
    } else {
        // 高价产品：上浮80%（单品利润大，但回本慢）
        return $unitCost * 1.80;
    }
}

// 为每个专辑获取unit cost配置（这里使用简单的默认值）
function getUnitCostForRelease($releaseId, $config) {
    if (isset($config[$releaseId])) {
        return $config[$releaseId];
    }
    // 默认使用标准黑胶成本
    return 25.00;
}

// ========== 数据准备 ==========
$pageData = prepareProcurementPageData($pdo);
$warehouseId = $pageData['warehouse_id'];
$suppliers = $pageData['suppliers'];
$releases = $pageData['releases'];
$pendingPOs = $pageData['pending_orders'];

// 为每个专辑添加unit cost和建议售价
$releasePriceConfig = [];
foreach ($releases as $r) {
    $unitCost = getUnitCostForRelease($r['ReleaseID'], $UNIT_COST_CONFIG);
    $suggestedPrice = getSuggestedSalePrice($unitCost);
    $releasePriceConfig[$r['ReleaseID']] = [
        'unit_cost' => $unitCost,
        'suggested_price' => $suggestedPrice
    ];
}

// 可用成色选项
$conditionOptions = ['New', 'Mint', 'NM', 'VG+', 'VG'];

// ========== POST 请求处理 ==========
// Action 1: Create New Supplier Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    $releaseId = (int)$_POST['release_id'];
    $salePrice = (float)$_POST['sale_price'];

    // 【修改】使用固定的unit cost而不是从售价计算
    $unitCost = getUnitCostForRelease($releaseId, $UNIT_COST_CONFIG);

    $data = [
        'supplier_id'  => (int)$_POST['supplier_id'],
        'employee_id'  => $_SESSION['user_id'],
        'release_id'   => $releaseId,
        'quantity'     => (int)$_POST['quantity'],
        'unit_cost'    => $unitCost,
        'condition'    => $_POST['condition'] ?? 'New',
        'sale_price'   => $salePrice
    ];

    // 验证成色
    if (!in_array($data['condition'], $conditionOptions)) {
        flash("Invalid condition grade.", 'danger');
        header("Location: procurement.php");
        exit();
    }

    $result = handleProcurementCreatePO($pdo, $data, $warehouseId);
    flash($result['message'], $result['success'] ? 'success' : 'danger');

    header("Location: procurement.php");
    exit();
}

// Action 2: Receive Supplier Order
// 【修改】使用订单中已保存的condition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
    $orderId = (int)$_POST['po_id'];

    // 直接调用存储过程接收订单，存储过程会使用订单行中保存的ConditionGrade和SalePrice
    $result = handleProcurementReceivePOWithCondition($pdo, $orderId, $warehouseId, 'New');
    flash($result['message'], $result['success'] ? 'success' : 'danger');

    header("Location: procurement.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<?php if (!$warehouseId): ?>
    <div class='alert alert-danger'>Critical Configuration Error: 'Warehouse' shop type not found in database. Procurement functions disabled.</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="text-warning mb-1"><i class="fa-solid fa-boxes-packing me-2"></i>Procurement & Receiving</h2>
            <p class="text-secondary mb-0">Create purchase orders and receive goods into warehouse inventory</p>
        </div>
        <div class="d-flex gap-2">
            <a href="warehouse_dispatch.php" class="btn btn-info" <?= !$warehouseId ? 'disabled' : '' ?>>
                <i class="fa-solid fa-truck me-2"></i>Warehouse Dispatch
            </a>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#newPOModal" <?= !$warehouseId ? 'disabled' : '' ?>>
                <i class="fa-solid fa-plus me-2"></i>New Purchase Order
            </button>
        </div>
    </div>
</div>

<!-- 采购成本说明 -->
<div class="alert alert-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    <strong>Procurement Cost Policy:</strong> Each album has a fixed unit cost. Sale price is suggested based on cost level:
    <ul class="mb-0 mt-2">
        <li>Low cost (≤¥20): +50% markup (薄利多销)</li>
        <li>Medium cost (¥21-50): +60% markup</li>
        <li>Higher cost (¥51-100): +70% markup</li>
        <li>Premium cost (>¥100): +80% markup (single item profit)</li>
    </ul>
</div>

<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-clock me-2"></i>Pending Shipments</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Items</th>
                    <th>Est. Cost</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingPOs as $po): ?>
                <tr>
                    <td><span class="badge bg-info">#<?= $po['SupplierOrderID'] ?></span></td>
                    <td><?= h($po['SupplierName']) ?></td>
                    <td><?= date('Y-m-d', strtotime($po['OrderDate'])) ?></td>
                    <td><?= $po['TotalItems'] ?? 0 ?> units</td>
                    <td class="text-success fw-bold"><?= formatPrice($po['TotalCost'] ?? 0) ?></td>
                    <td><span class="badge bg-warning text-dark">Pending Arrival</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-success receive-btn"
                                data-po-id="<?= $po['SupplierOrderID'] ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#receiveModal"
                                <?= !$warehouseId ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-check-to-slot me-1"></i> Receive
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pendingPOs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No pending orders. Inventory is up to date.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New PO Modal -->
<div class="modal fade" id="newPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-file-invoice me-2 text-warning"></i>Create Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="poForm">
                <div class="modal-body">
                    <input type="hidden" name="create_po" value="1">

                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">Select Supplier...</option>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['SupplierID'] ?>"><?= h($s['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="border-secondary">
                    <h6 class="text-warning mb-3"><i class="fa-solid fa-compact-disc me-2"></i>Order Items</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Release Album</label>
                            <select name="release_id" class="form-select bg-dark text-white border-secondary" required>
                                <option value="">Select Album...</option>
                                <?php foreach($releases as $r): ?>
                                    <option value="<?= $r['ReleaseID'] ?>"><?= h($r['Title']) ?> - <?= h($r['ArtistName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Condition Grade</label>
                            <select name="condition" class="form-select bg-dark text-white border-secondary" required>
                                <?php foreach($conditionOptions as $cond): ?>
                                    <option value="<?= $cond ?>"><?= $cond ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" id="poQuantity" class="form-control bg-dark text-white border-secondary"
                                   min="1" value="10" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit Cost (Fixed)</label>
                            <input type="text" id="unitCostDisplay"
                                   class="form-control bg-secondary text-warning border-secondary fw-bold"
                                   value="¥25.00" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Suggested Price</label>
                            <input type="text" id="suggestedPriceDisplay"
                                   class="form-control bg-secondary text-info border-secondary"
                                   value="¥40.00" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sale Price (¥)</label>
                            <input type="number" name="sale_price" id="salePrice"
                                   class="form-control bg-dark text-white border-secondary"
                                   step="0.01" min="1" value="40.00" required>
                            <small class="text-muted">You decide final price</small>
                        </div>
                    </div>

                    <div class="alert alert-secondary">
                        <div class="row">
                            <div class="col-6">
                                <div class="d-flex justify-content-between">
                                    <span>Total Procurement Cost:</span>
                                    <span id="totalCostDisplay" class="fw-bold text-danger">¥250.00</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between">
                                    <span>Expected Revenue:</span>
                                    <span id="expectedRevenueDisplay" class="fw-bold text-success">¥400.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-2 border-top border-secondary">
                            <span>Expected Profit:</span>
                            <span id="expectedProfitDisplay" class="fw-bold text-warning">¥150.00 (37.5%)</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">
                        <i class="fa-solid fa-paper-plane me-1"></i>Issue PO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Receive PO Modal - 【修改】移除condition选择，使用订单中已保存的condition -->
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-truck-ramp-box me-2 text-success"></i>Receive Goods</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="receive_po" value="1">
                    <input type="hidden" name="po_id" id="receivePOId">

                    <p>Confirm receipt of goods for <strong>PO #<span id="receivePOIdDisplay"></span></strong>?</p>

                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        The condition grade specified when creating this order will be used automatically.
                    </div>

                    <div class="alert alert-warning">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i>
                        This will generate Stock Items in the Warehouse inventory.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check me-1"></i>Confirm Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 【修改】专辑价格配置数据
    const releasePriceConfig = <?= json_encode($releasePriceConfig) ?>;

    // DOM元素
    const releaseSelect = document.querySelector('select[name="release_id"]');
    const salePriceInput = document.getElementById('salePrice');
    const quantityInput = document.getElementById('poQuantity');
    const unitCostDisplay = document.getElementById('unitCostDisplay');
    const suggestedPriceDisplay = document.getElementById('suggestedPriceDisplay');
    const totalCostDisplay = document.getElementById('totalCostDisplay');
    const expectedRevenueDisplay = document.getElementById('expectedRevenueDisplay');
    const expectedProfitDisplay = document.getElementById('expectedProfitDisplay');

    let currentUnitCost = 25.00;

    // 更新专辑选择时的价格信息
    function updateReleasePrice() {
        const releaseId = releaseSelect.value;
        if (releaseId && releasePriceConfig[releaseId]) {
            currentUnitCost = releasePriceConfig[releaseId].unit_cost;
            const suggestedPrice = releasePriceConfig[releaseId].suggested_price;

            unitCostDisplay.value = '¥' + currentUnitCost.toFixed(2);
            suggestedPriceDisplay.value = '¥' + suggestedPrice.toFixed(2);
            salePriceInput.value = suggestedPrice.toFixed(2);
        } else {
            currentUnitCost = 25.00;
            unitCostDisplay.value = '¥25.00';
            suggestedPriceDisplay.value = '¥40.00';
            salePriceInput.value = '40.00';
        }
        updateCosts();
    }

    // 更新成本和利润计算
    function updateCosts() {
        const salePrice = parseFloat(salePriceInput.value) || 0;
        const quantity = parseInt(quantityInput.value) || 0;

        const totalCost = currentUnitCost * quantity;
        const expectedRevenue = salePrice * quantity;
        const profit = expectedRevenue - totalCost;
        const profitPercent = expectedRevenue > 0 ? (profit / expectedRevenue * 100) : 0;

        totalCostDisplay.textContent = '¥' + totalCost.toFixed(2);
        expectedRevenueDisplay.textContent = '¥' + expectedRevenue.toFixed(2);
        expectedProfitDisplay.textContent = '¥' + profit.toFixed(2) + ' (' + profitPercent.toFixed(1) + '%)';

        // 根据利润率设置颜色
        if (profitPercent < 20) {
            expectedProfitDisplay.className = 'fw-bold text-danger';
        } else if (profitPercent < 30) {
            expectedProfitDisplay.className = 'fw-bold text-warning';
        } else {
            expectedProfitDisplay.className = 'fw-bold text-success';
        }
    }

    releaseSelect.addEventListener('change', updateReleasePrice);
    salePriceInput.addEventListener('input', updateCosts);
    quantityInput.addEventListener('input', updateCosts);

    // 初始化
    updateCosts();

    // Receive modal
    document.querySelectorAll('.receive-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const poId = this.dataset.poId;
            document.getElementById('receivePOId').value = poId;
            document.getElementById('receivePOIdDisplay').textContent = poId;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
