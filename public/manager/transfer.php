<?php
/**
 * 【架构重构】库存调拨页面
 * 表现层 - 仅负责数据展示和用户交互
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Manager');

// ========== 数据准备（先获取用于验证） ==========
$pageData = prepareTransferPageData($pdo);
$shops = $pageData['shops'];
$pending = $pageData['pending'];

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['initiate_transfer'])) {
        $result = handleTransferInitiation(
            $pdo,
            $_POST['stock_id'],
            $_POST['to_shop_id'],
            $_SESSION['user_id'],
            $shops
        );
        flash($result['message'], $result['success'] ? 'success' : 'danger');
    } elseif (isset($_POST['confirm_receipt'])) {
        $result = handleTransferReceipt($pdo, $_POST['transfer_id'], $_SESSION['user_id']);
        flash($result['message'], $result['success'] ? 'success' : 'danger');
    }

    header("Location: transfer.php");
    exit();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
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
