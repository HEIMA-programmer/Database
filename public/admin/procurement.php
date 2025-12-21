<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// [Fix: Hardcoding] 动态获取仓库ID
$warehouseId = getShopIdByType($pdo, 'Warehouse');

// --- Action 1: Create New Purchase Order ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    try {
        $pdo->beginTransaction();
        
        $supplierId = $_POST['supplier_id'];
        // 创建 PO 头
        $stmt = $pdo->prepare("INSERT INTO PurchaseOrder (SupplierID, OrderDate, Status, SourceType) VALUES (?, CURDATE(), 'Pending', 'Supplier')");
        $stmt->execute([$supplierId]);
        $poId = $pdo->lastInsertId();
        
        // 创建 PO Lines (支持多选简化为单选演示，实际可扩展)
        $releaseId = $_POST['release_id'];
        $qty = $_POST['quantity'];
        $cost = $_POST['unit_cost'];
        
        $lineSql = "INSERT INTO PurchaseOrderLine (PO_ID, ReleaseID, Quantity, UnitCost) VALUES (?, ?, ?, ?)";
        $pdo->prepare($lineSql)->execute([$poId, $releaseId, $qty, $cost]);
        
        $pdo->commit();
        flash("Purchase Order #$poId created.", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Error creating PO: " . $e->getMessage(), 'danger');
    }
    header("Location: procurement.php");
    exit();
}

// --- Action 2: Receive PO (The "Put-away" Flow) ---
// 这直接解决了 Assignment 1 反馈中 "Purchase orders will not convert into traceable stock items" 的问题
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
    $poId = $_POST['po_id'];
    
    try {
        $pdo->beginTransaction();
        
        // 1. 获取该 PO 的所有行
        $lines = $pdo->prepare("SELECT * FROM PurchaseOrderLine WHERE PO_ID = ?");
        $lines->execute([$poId]);
        $poLines = $lines->fetchAll();
        
        if (!$poLines) throw new Exception("PO not found or empty.");
        
        // 2. 遍历每一行，将数量转化为具体的 StockItems
        foreach ($poLines as $line) {
            $batchNo = "BATCH-" . date('ymd') . "-" . $poId; // 生成批次号
            
            // 循环 Quantity 次，插入 StockItem
            for ($i = 0; $i < $line['Quantity']; $i++) {
                $insertStock = "INSERT INTO StockItem (ReleaseID, LocationID, Status, BatchNo, SourcePO_ID, DateAdded) 
                                VALUES (?, ?, 'InStock', ?, ?, NOW())";
                $pdo->prepare($insertStock)->execute([
                    $line['ReleaseID'],
                    $warehouseId, // 使用动态获取的 ID
                    $batchNo,
                    $poId
                ]);
            }
        }
        
        // 3. 更新 PO 状态为 Completed
        $pdo->prepare("UPDATE PurchaseOrder SET Status = 'Completed' WHERE PO_ID = ?")->execute([$poId]);
        
        $pdo->commit();
        flash("PO #$poId received. Items added to Warehouse inventory.", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Error receiving PO: " . $e->getMessage(), 'danger');
    }
    header("Location: procurement.php");
    exit();
}

// --- Data Queries ---
$suppliers = $pdo->query("SELECT * FROM Supplier")->fetchAll();
$releases = $pdo->query("SELECT ReleaseID, Title, ArtistName FROM ReleaseAlbum ORDER BY Title")->fetchAll();
$pendingPOs = $pdo->query("SELECT po.*, s.Name as SupplierName, 
    (SELECT SUM(Quantity) FROM PurchaseOrderLine WHERE PO_ID = po.PO_ID) as TotalItems,
    (SELECT SUM(Quantity * UnitCost) FROM PurchaseOrderLine WHERE PO_ID = po.PO_ID) as EstTotalCost
    FROM PurchaseOrder po 
    JOIN Supplier s ON po.SupplierID = s.SupplierID 
    WHERE po.Status = 'Pending'")->fetchAll();

?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h2 class="text-warning"><i class="fa-solid fa-boxes-packing me-2"></i>Procurement & Receiving</h2>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#newPOModal">
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
                    <td><?= $po['PO_ID'] ?></td>
                    <td><?= h($po['SupplierName']) ?></td>
                    <td><?= $po['OrderDate'] ?></td>
                    <td><?= $po['TotalItems'] ?> units</td>
                    <td><?= formatPrice($po['EstTotalCost']) ?></td>
                    <td><span class="badge bg-warning text-dark">Pending Arrival</span></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Confirm receipt of goods? This will generate Stock Items.');">
                            <input type="hidden" name="receive_po" value="1">
                            <input type="hidden" name="po_id" value="<?= $po['PO_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-success">
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