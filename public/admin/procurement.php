<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Admin');
require_once __DIR__ . '/../../includes/header.php';

// 获取供应商和产品列表
$suppliers = $pdo->query("SELECT * FROM Supplier")->fetchAll();
$releases = $pdo->query("SELECT ReleaseID, Title FROM ReleaseAlbum ORDER BY Title")->fetchAll();

// 处理：创建新订单
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. PO Header
        $stmt = $pdo->prepare("INSERT INTO PurchaseOrder (SupplierID, CreatedByEmployeeID, OrderDate, Status, SourceType) VALUES (?, ?, NOW(), 'Pending', 'Supplier')");
        $stmt->execute([$_POST['supplier_id'], $_SESSION['user_id']]);
        $poId = $pdo->lastInsertId();

        // 2. PO Line (简化：每次只添加一种商品，实际可扩展)
        $stmt = $pdo->prepare("INSERT INTO PurchaseOrderLine (PO_ID, ReleaseID, Quantity, UnitCost) VALUES (?, ?, ?, ?)");
        $stmt->execute([$poId, $_POST['release_id'], $_POST['qty'], $_POST['cost']]);

        $pdo->commit();
        flash("Purchase Order #$poId created.", 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Error: " . $e->getMessage(), 'danger');
    }
}

// 处理：收货 (Receive) -> 核心加分项
if (isset($_POST['receive_po'])) {
    $poId = $_POST['po_id'];
    
    try {
        $pdo->beginTransaction();

        // 1. 获取 PO 详情
        $lines = $pdo->query("SELECT * FROM PurchaseOrderLine WHERE PO_ID = $poId")->fetchAll();
        
        // 2. 为每一件商品生成 StockItem
        $stmt = $pdo->prepare("INSERT INTO StockItem (ReleaseID, ShopID, SourcePO_ID, BatchNo, ConditionGrade, Status, UnitPrice, AcquiredDate) 
                               VALUES (:rid, 3, :poid, :batch, 'New', 'Available', :price, NOW())"); 
                               // ShopID 3 = Warehouse (默认入库到仓库)
        
        $batchNo = 'SUP-' . date('Ymd') . '-' . $poId;
        
        foreach ($lines as $line) {
            // 假设零售价是成本的 1.4 倍 (Assignment 1 提到的 40% markup)
            $retailPrice = $line['UnitCost'] * 1.4; 
            
            for ($i = 0; $i < $line['Quantity']; $i++) {
                $stmt->execute([
                    ':rid' => $line['ReleaseID'],
                    ':poid' => $poId,
                    ':batch' => $batchNo,
                    ':price' => $retailPrice
                ]);
            }
        }

        // 3. 更新 PO 状态
        $pdo->query("UPDATE PurchaseOrder SET Status = 'Received' WHERE PO_ID = $poId");
        
        $pdo->commit();
        flash("PO #$poId received! Inventory updated in Warehouse.", 'success');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        flash("Receive failed: " . $e->getMessage(), 'danger');
    }
}

// 获取待收货订单
$pendingPOs = $pdo->query("SELECT po.*, s.Name as SupplierName, pol.Quantity, r.Title 
                           FROM PurchaseOrder po 
                           JOIN Supplier s ON po.SupplierID = s.SupplierID 
                           JOIN PurchaseOrderLine pol ON po.PO_ID = pol.PO_ID
                           JOIN ReleaseAlbum r ON pol.ReleaseID = r.ReleaseID
                           WHERE po.Status = 'Pending'")->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card bg-secondary text-light">
            <div class="card-header bg-warning text-dark fw-bold">Create Purchase Order</div>
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
                        <label>Product</label>
                        <select name="release_id" class="form-select" required>
                            <?php foreach($releases as $r): ?>
                                <option value="<?= $r['ReleaseID'] ?>"><?= h($r['Title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>Quantity</label>
                            <input type="number" name="qty" class="form-control" required min="1">
                        </div>
                        <div class="col">
                            <label>Unit Cost</label>
                            <input type="number" name="cost" class="form-control" required step="0.01">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">Submit Order</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <h4 class="text-warning mb-3">Pending Receipts</h4>
        <?php if(empty($pendingPOs)): ?>
            <div class="alert alert-info">No pending orders from suppliers.</div>
        <?php else: ?>
            <div class="card bg-dark border-secondary">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead><tr><th>PO #</th><th>Supplier</th><th>Item</th><th>Qty</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($pendingPOs as $po): ?>
                        <tr>
                            <td><?= $po['PO_ID'] ?></td>
                            <td><?= h($po['SupplierName']) ?></td>
                            <td><?= h($po['Title']) ?></td>
                            <td class="fw-bold fs-5"><?= $po['Quantity'] ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="receive_po" value="1">
                                    <input type="hidden" name="po_id" value="<?= $po['PO_ID'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fa-solid fa-box-open me-1"></i>Receive
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>