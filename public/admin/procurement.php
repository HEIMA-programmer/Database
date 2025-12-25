<?php
/**
 * 【架构重构】采购管理页面
 * 表现层 - 仅负责数据展示和用户交互
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Admin');

// ========== 数据准备 ==========
$pageData = prepareProcurementPageData($pdo);
$warehouseId = $pageData['warehouse_id'];
$suppliers = $pageData['suppliers'];
$releases = $pageData['releases'];
$pendingPOs = $pageData['pending_orders'];

// ========== POST 请求处理 ==========
// Action 1: Create New Supplier Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    $data = [
        'supplier_id'  => (int)$_POST['supplier_id'],
        'employee_id'  => $_SESSION['user_id'],
        'release_id'   => (int)$_POST['release_id'],
        'quantity'     => (int)$_POST['quantity'],
        'unit_cost'    => (float)$_POST['unit_cost']
    ];

    $result = handleProcurementCreatePO($pdo, $data, $warehouseId);
    flash($result['message'], $result['success'] ? 'success' : 'danger');

    header("Location: procurement.php");
    exit();
}

// Action 2: Receive Supplier Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
    $orderId = (int)$_POST['po_id'];

    $result = handleProcurementReceivePO($pdo, $orderId, $warehouseId);
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
        <h2 class="text-warning"><i class="fa-solid fa-boxes-packing me-2"></i>Procurement & Receiving</h2>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#newPOModal" <?= !$warehouseId ? 'disabled' : '' ?>>
            <i class="fa-solid fa-plus me-2"></i>New Purchase Order
        </button>
    </div>
</div>

<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0">Pending Shipments</h5>
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
                    <td><?= $po['SupplierOrderID'] ?></td>
                    <td><?= h($po['SupplierName']) ?></td>
                    <td><?= date('Y-m-d', strtotime($po['OrderDate'])) ?></td>
                    <td><?= $po['TotalItems'] ?? 0 ?> units</td>
                    <td><?= formatPrice($po['TotalCost'] ?? 0) ?></td>
                    <td><span class="badge bg-warning text-dark">Pending Arrival</span></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Confirm receipt of goods? This will generate Stock Items.');">
                            <input type="hidden" name="receive_po" value="1">
                            <input type="hidden" name="po_id" value="<?= $po['SupplierOrderID'] ?>">
                            <button type="submit" class="btn btn-sm btn-success" <?= !$warehouseId ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-check-to-slot me-1"></i> Receive Goods
                            </button>
                        </form>
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

<div class="modal fade" id="newPOModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Create Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="create_po" value="1">
                    <div class="mb-3">
                        <label>Supplier</label>
                        <select name="supplier_id" class="form-select bg-dark text-white border-secondary" required>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['SupplierID'] ?>"><?= h($s['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="border-secondary">
                    <h6 class="text-warning">Order Items</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Release Album</label>
                            <select name="release_id" class="form-select bg-dark text-white border-secondary" required>
                                <?php foreach($releases as $r): ?>
                                    <option value="<?= $r['ReleaseID'] ?>"><?= h($r['Title']) ?> (<?= h($r['ArtistName']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Quantity</label>
                            <input type="number" name="quantity" class="form-control bg-dark text-white border-secondary" min="1" value="10" required>
                        </div>
                        <div class="col-md-3">
                            <label>Unit Cost (¥)</label>
                            <input type="number" name="unit_cost" class="form-control bg-dark text-white border-secondary" step="0.01" value="50.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-warning fw-bold">Issue PO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
