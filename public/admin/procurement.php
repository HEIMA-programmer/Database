<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// [Fix: Robustness] 动态获取仓库ID并检查有效性
$warehouseId = getShopIdByType($pdo, 'Warehouse');
if (!$warehouseId) {
    echo "<div class='alert alert-danger'>Critical Configuration Error: 'Warehouse' shop type not found in database. Procurement functions disabled.</div>";
    // 停止渲染后续逻辑，或者禁用按钮
    $warehouseId = 0; // Prevent crash, logic will handle 0
}

// --- Action 1: Create New Supplier Order ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    try {
        $pdo->beginTransaction();

        $supplierId = $_POST['supplier_id'];
        $empId = $_SESSION['user_id'];
        $releaseId = $_POST['release_id'];
        $qty = $_POST['quantity'];
        $cost = $_POST['unit_cost'];

        // 使用存储过程创建供应商订单
        $stmt = $pdo->prepare("CALL sp_create_supplier_order(?, ?, ?, @order_id)");
        $stmt->execute([$supplierId, $empId, $warehouseId]);

        // 获取创建的订单ID
        $result = $pdo->query("SELECT @order_id AS order_id")->fetch();
        $orderId = $result['order_id'];

        if ($orderId > 0) {
            // 添加订单行项目
            $stmt = $pdo->prepare("CALL sp_add_supplier_order_line(?, ?, ?, ?)");
            $stmt->execute([$orderId, $releaseId, $qty, $cost]);

            $pdo->commit();
            flash("Supplier Order #$orderId created successfully.", 'success');
        } else {
            throw new Exception("Failed to create supplier order.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash("Error creating order: " . $e->getMessage(), 'danger');
    }
    header("Location: procurement.php");
    exit();
}

// --- Action 2: Receive Supplier Order (The "Put-away" Flow) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
    $orderId = $_POST['po_id'];

    if (!$warehouseId) {
        flash("Cannot receive items: Warehouse ID not configured.", 'danger');
        header("Location: procurement.php");
        exit();
    }

    try {
        // 使用存储过程接收供应商订单并生成库存
        // 【修复】markup_rate = 0.5 表示售价是成本的150%（售价 = 成本 × (1 + 0.5)）
        $batchNo = "BATCH-" . date('Ymd') . "-" . $orderId;
        $stmt = $pdo->prepare("CALL sp_receive_supplier_order(?, ?, ?, ?)");
        $stmt->execute([$orderId, $batchNo, 'New', 0.5]); // 加价率0.5 = 售价为成本的1.5倍

        flash("Supplier Order #$orderId received. Items added to Warehouse inventory.", 'success');
    } catch (Exception $e) {
        flash("Error receiving order: " . $e->getMessage(), 'danger');
    }
    header("Location: procurement.php");
    exit();
}

// --- Data Queries ---
$suppliers = $pdo->query("SELECT * FROM Supplier ORDER BY Name")->fetchAll();
$releases = $pdo->query("SELECT ReleaseID, Title, ArtistName FROM ReleaseAlbum ORDER BY Title")->fetchAll();

// 使用视图查询待处理的供应商订单
$pendingPOs = $pdo->query("SELECT * FROM vw_admin_supplier_orders WHERE Status = 'Pending' ORDER BY OrderDate DESC")->fetchAll();

?>

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