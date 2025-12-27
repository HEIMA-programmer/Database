<?php
/**
 * 【架构重构】采购管理页面 - Admin版
 * 支持选择成色，采购成本固定为售价的50%
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

// ========== 数据准备 ==========
$pageData = prepareProcurementPageData($pdo);
$warehouseId = $pageData['warehouse_id'];
$suppliers = $pageData['suppliers'];
$releases = $pageData['releases'];
$pendingPOs = $pageData['pending_orders'];

// 可用成色选项
$conditionOptions = ['New', 'Mint', 'NM', 'VG+', 'VG'];

// ========== POST 请求处理 ==========
// Action 1: Create New Supplier Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    $salePrice = (float)$_POST['sale_price'];
    $unitCost = $salePrice * 0.5; // 固定50%采购成本

    $data = [
        'supplier_id'  => (int)$_POST['supplier_id'],
        'employee_id'  => $_SESSION['user_id'],
        'release_id'   => (int)$_POST['release_id'],
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
    $orderId = (int)$_POST['po_id'];
    $condition = $_POST['condition'] ?? 'New';

    // 验证成色
    if (!in_array($condition, $conditionOptions)) {
        flash("Invalid condition grade.", 'danger');
        header("Location: procurement.php");
        exit();
    }

    $result = handleProcurementReceivePOWithCondition($pdo, $orderId, $warehouseId, $condition);
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
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#newPOModal" <?= !$warehouseId ? 'disabled' : '' ?>>
            <i class="fa-solid fa-plus me-2"></i>New Purchase Order
        </button>
    </div>
</div>

<!-- 采购成本说明 -->
<div class="alert alert-info mb-4">
    <i class="fa-solid fa-info-circle me-2"></i>
    <strong>Procurement Cost Policy:</strong> All items are procured at 50% of the retail sale price.
    Set the desired sale price and the system will automatically calculate the procurement cost.
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
                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control bg-dark text-white border-secondary"
                                   min="1" value="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sale Price (¥)</label>
                            <input type="number" name="sale_price" id="salePrice"
                                   class="form-control bg-dark text-white border-secondary"
                                   step="0.01" min="1" value="100.00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit Cost (50%)</label>
                            <input type="text" id="unitCostDisplay"
                                   class="form-control bg-secondary text-warning border-secondary fw-bold"
                                   value="¥50.00" readonly>
                        </div>
                    </div>

                    <div class="alert alert-secondary">
                        <div class="d-flex justify-content-between">
                            <span>Procurement Cost:</span>
                            <span id="totalCostDisplay" class="fw-bold text-warning">¥500.00</span>
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

<!-- Receive PO Modal -->
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

                    <div class="mb-3">
                        <label class="form-label">Condition Grade</label>
                        <select name="condition" class="form-select bg-dark text-white border-secondary" required>
                            <?php foreach($conditionOptions as $cond): ?>
                                <option value="<?= $cond ?>"><?= $cond ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select the condition grade for the received items.</small>
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
    // 自动计算采购成本
    const salePriceInput = document.getElementById('salePrice');
    const quantityInput = document.querySelector('input[name="quantity"]');
    const unitCostDisplay = document.getElementById('unitCostDisplay');
    const totalCostDisplay = document.getElementById('totalCostDisplay');

    function updateCosts() {
        const salePrice = parseFloat(salePriceInput.value) || 0;
        const quantity = parseInt(quantityInput.value) || 0;
        const unitCost = salePrice * 0.5;
        const totalCost = unitCost * quantity;

        unitCostDisplay.value = '¥' + unitCost.toFixed(2);
        totalCostDisplay.textContent = '¥' + totalCost.toFixed(2);
    }

    salePriceInput.addEventListener('input', updateCosts);
    quantityInput.addEventListener('input', updateCosts);

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
