<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// --- Action: Create PO (创建采购单) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. Create PO Header
        $stmt = $pdo->prepare("INSERT INTO PurchaseOrder (SupplierID, OrderDate, Status) VALUES (?, CURDATE(), 'Pending')");
        $stmt->execute([$_POST['supplier_id']]);
        $poId = $pdo->lastInsertId();

        // 2. Create PO Line
        $stmt = $pdo->prepare("INSERT INTO PurchaseOrderLine (PO_ID, ReleaseID, Quantity, UnitCost) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $poId, 
            $_POST['release_id'], 
            $_POST['quantity'], 
            $_POST['cost']
        ]);

        $pdo->commit();
        flash("Purchase Order #$poId created.", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Error creating PO: " . $e->getMessage(), 'danger');
    }
    header("Location: procurement.php");
    exit();
}

// --- Action: Receive PO (核心修复：收货入库逻辑) ---
if (isset($_POST['receive_po'])) {
    $poId = $_POST['po_id'];
    try {
        $pdo->beginTransaction();

        // 1. Get PO Lines
        $lines = $pdo->query("SELECT * FROM PurchaseOrderLine WHERE PO_ID = $poId")->fetchAll();
        
        // 2. Insert into StockItem (实例化库存)
        $insStock = $pdo->prepare("INSERT INTO StockItem (ReleaseID, ShopID, BatchNo, AcquiredDate, ConditionGrade, UnitPrice, Status) 
                                   VALUES (:rid, :shop, :batch, CURDATE(), 'New', :price, 'Available')");
        
        foreach ($lines as $line) {
            // 默认利润率 1.5倍
            $sellPrice = $line['UnitCost'] * 1.5; 
            // 默认入库到 Online Warehouse (ID=3)
            $warehouseId = 3; 
            
            for ($i = 0; $i < $line['Quantity']; $i++) {
                $insStock->execute([
                    ':rid' => $line['ReleaseID'],
                    ':shop' => $warehouseId,
                    ':batch' => "PO-$poId", // 追踪批次号
                    ':price' => $sellPrice
                ]);
            }
        }

        // 3. Update PO Status
        $pdo->prepare("UPDATE PurchaseOrder SET Status = 'Received' WHERE PO_ID = ?")->execute([$poId]);

        $pdo->commit();
        flash("PO #$poId received. Items added to inventory.", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Error receiving PO: " . $e->getMessage(), 'danger');
    }
    header("Location: procurement.php");
    exit();
}

// --- Queries ---
$suppliers = $pdo->query("SELECT * FROM Supplier")->fetchAll();
$releases = $pdo->query("SELECT ReleaseID, Title, ArtistName FROM ReleaseAlbum ORDER BY Title")->fetchAll();
$pendingPOs = $pdo->query("
    SELECT po.*, s.Name as SupplierName, 
           (SELECT COUNT(*) FROM PurchaseOrderLine WHERE PO_ID = po.PO_ID) as LineCount,
           (SELECT SUM(Quantity * UnitCost) FROM PurchaseOrderLine WHERE PO_ID = po.PO_ID) as TotalCost
    FROM PurchaseOrder po
    JOIN Supplier s ON po.SupplierID = s.SupplierID
    WHERE po.Status = 'Pending'
    ORDER BY po.PO_ID DESC
")->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <h3 class="text-warning mb-3">Create Purchase Order</h3>
        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="create_po" value="1">
                    <div class="mb-3">
                        <label>Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?= $s['SupplierID'] ?>"><?= h($s['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Release (Product)</label>
                        <select name="release_id" class="form-select" required>
                            <?php foreach($releases as $r): ?>
                                <option value="<?= $r['ReleaseID'] ?>"><?= h($r['Title']) ?> - <?= h($r['ArtistName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="10" required>
                        </div>
                        <div class="col">
                            <label>Unit Cost</label>
                            <input type="number" name="cost" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Draft PO</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <h3 class="text-white mb-3">Pending Orders (Inbound)</h3>
        <div class="card bg-secondary text-light">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total Cost</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pendingPOs as $po): ?>
                        <tr>
                            <td><?= $po['PO_ID'] ?></td>
                            <td><?= h($po['SupplierName']) ?></td>
                            <td><?= $po['OrderDate'] ?></td>
                            <td><?= $po['LineCount'] ?> Lines</td>
                            <td class="text-info"><?= formatPrice($po['TotalCost']) ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="po_id" value="<?= $po['PO_ID'] ?>">
                                    <button type="submit" name="receive_po" class="btn btn-sm btn-success">
                                        <i class="fa-solid fa-box-open me-1"></i> Receive Stock
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if(empty($pendingPOs)): ?>
                <div class="p-3 text-center text-muted">No pending orders.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>