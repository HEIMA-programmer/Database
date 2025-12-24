<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/db_procedures.php';
require_once __DIR__ . '/../../includes/header.php';

// 获取所有店铺
$shops = $pdo->query("SELECT * FROM Shop")->fetchAll();

// 处理调拨发起
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_transfer'])) {
    $stockId = $_POST['stock_id'];
    $toShopId = $_POST['to_shop_id'];
    $empId = $_SESSION['user_id'];

    // 简单验证目标店铺是否存在
    $targetShopValid = false;
    foreach ($shops as $s) {
        if ($s['ShopID'] == $toShopId) {
            $targetShopValid = true;
            break;
        }
    }

    if (!$targetShopValid) {
        flash("Invalid destination shop.", 'danger');
    } else {
        // 获取当前库存信息，验证所有权
        $stmt = $pdo->prepare("SELECT ShopID FROM StockItem WHERE StockItemID = ? AND Status = 'Available'");
        $stmt->execute([$stockId]);
        $currentItem = $stmt->fetch();

        if ($currentItem && $currentItem['ShopID'] != $toShopId) {
            // 使用存储过程发起调拨
            $transferId = DBProcedures::initiateTransfer($pdo, $stockId, $currentItem['ShopID'], $toShopId, $empId);

            if ($transferId) {
                flash("Transfer #$transferId initiated. Item #$stockId is now InTransit. Awaiting receipt confirmation at destination.", 'success');
            } else {
                flash("Transfer failed. Please check item availability.", 'danger');
            }
        } else {
            flash("Invalid Item ID or Item already at destination.", 'danger');
        }
    }
}

// 处理接收确认
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receipt'])) {
    $transferId = $_POST['transfer_id'];
    $empId = $_SESSION['user_id'];

    // 使用存储过程完成调拨
    $success = DBProcedures::completeTransfer($pdo, $transferId, $empId);

    if ($success) {
        flash("Transfer #$transferId confirmed. Item received and added to local inventory.", 'success');
    } else {
        flash("Receipt confirmation failed. Please verify transfer status.", 'danger');
    }
}

// 使用视图查询待接收的转运
$pending = $pdo->query("SELECT * FROM vw_manager_pending_transfers ORDER BY TransferDate DESC")->fetchAll();
?>

<div class="row">
    <div class="col-md-6">
        <h2 class="text-warning mb-4"><i class="fa-solid fa-truck-ramp-box me-2"></i>Stock Transfer</h2>

        <div class="card bg-secondary text-light mb-4">
            <div class="card-header bg-dark border-info">Initiate New Transfer</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="initiate_transfer" value="1">
                    <div class="mb-3">
                        <label class="form-label">Stock Item ID (Available Only)</label>
                        <input type="number" name="stock_id" class="form-control bg-dark text-white border-secondary" placeholder="Scan or Enter ID" required>
                        <div class="form-text text-light-50">Enter the unique Stock ID found on the physical item tag.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Destination Location</label>
                        <select name="to_shop_id" class="form-select bg-dark text-white border-secondary" required>
                            <?php foreach ($shops as $s): ?>
                                <option value="<?= $s['ShopID'] ?>"><?= h($s['Name']) ?> (<?= $s['Type'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-info w-100 fw-bold text-dark">
                        <i class="fa-solid fa-paper-plane me-2"></i>Initiate Transfer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <h2 class="text-info mb-4"><i class="fa-solid fa-inbox me-2"></i>Pending Receipts</h2>

        <?php if (empty($pending)): ?>
            <div class="alert alert-secondary">No transfers awaiting receipt confirmation.</div>
        <?php else: ?>
            <?php foreach ($pending as $t): ?>
            <div class="card bg-dark border-warning mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-warning mb-1"><?= h($t['Title']) ?></h6>
                            <small class="text-muted">
                                Item #<?= $t['StockItemID'] ?> | <?= h($t['BatchNo']) ?> | <?= h($t['ConditionGrade']) ?>
                            </small>
                            <div class="mt-2">
                                <span class="badge bg-secondary"><?= h($t['FromShopName']) ?></span>
                                <i class="fa-solid fa-arrow-right mx-2 text-info"></i>
                                <span class="badge bg-info text-dark"><?= h($t['ToShopName']) ?></span>
                            </div>
                            <small class="text-muted d-block mt-1">Initiated: <?= $t['TransferDate'] ?></small>
                        </div>
                        <form method="POST" class="ms-3">
                            <input type="hidden" name="confirm_receipt" value="1">
                            <input type="hidden" name="transfer_id" value="<?= $t['TransferID'] ?>">
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Confirm receipt of this item?');">
                                <i class="fa-solid fa-check me-1"></i>Receive
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>