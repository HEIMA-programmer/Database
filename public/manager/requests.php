<?php
/**
 * Manager请求系统 - 邮箱布局
 * 提交调价申请和调货申请，查看已发出申请的状态
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Manager');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';

$shopId = $_SESSION['user']['ShopID'] ?? null;
$employeeId = $_SESSION['user_id'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';

if (!$shopId || !$employeeId) {
    header('Location: dashboard.php');
    exit;
}

$action = $_GET['action'] ?? 'inbox';
$message = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'submit_price') {
        // 提交调价申请
        $releaseId = (int)$_POST['release_id'];
        $conditionGrade = $_POST['condition_grade'];
        $quantity = (int)$_POST['quantity'];
        $currentPrice = (float)$_POST['current_price'];
        $requestedPrice = (float)$_POST['requested_price'];
        $reason = trim($_POST['reason']);

        if ($requestedPrice <= 0) {
            $error = 'Requested price must be greater than 0.';
        } else {
            $result = DBProcedures::createPriceAdjustmentRequest(
                $pdo, $employeeId, $shopId, $releaseId, $conditionGrade,
                $quantity, $currentPrice, $requestedPrice, $reason
            );
            if ($result) {
                $message = 'Price adjustment request submitted successfully!';
                $action = 'sent';
            } else {
                $error = 'Failed to submit request. Please try again.';
            }
        }
    } elseif ($postAction === 'submit_transfer') {
        // 提交调货申请
        $releaseId = (int)$_POST['release_id'];
        $conditionGrade = $_POST['condition_grade'];
        $quantity = (int)$_POST['quantity'];
        $toShopId = (int)$_POST['to_shop_id'];
        $reason = trim($_POST['reason']);

        if ($toShopId === $shopId) {
            $error = 'Cannot request transfer to the same shop.';
        } elseif ($quantity <= 0) {
            $error = 'Quantity must be greater than 0.';
        } else {
            $result = DBProcedures::createTransferRequest(
                $pdo, $employeeId, $toShopId, $shopId, $releaseId, $conditionGrade, $quantity, $reason
            );
            if ($result) {
                $message = 'Transfer request submitted successfully!';
                $action = 'sent';
            } else {
                $error = 'Failed to submit request. Please try again.';
            }
        }
    }
}

// 获取已发出的申请
$sentRequests = DBProcedures::getManagerRequestsSent($pdo, $employeeId);
$pendingCount = count(array_filter($sentRequests, fn($r) => $r['Status'] === 'Pending'));
$approvedCount = count(array_filter($sentRequests, fn($r) => $r['Status'] === 'Approved'));
$rejectedCount = count(array_filter($sentRequests, fn($r) => $r['Status'] === 'Rejected'));

// 获取店铺列表（用于调货申请）
$shops = DBProcedures::getShopList($pdo);
$otherShops = array_filter($shops, fn($s) => $s['ShopID'] != $shopId);

// 获取专辑列表（用于新建申请）
$releases = DBProcedures::getReleaseList($pdo);

// 从URL参数预填表单
$prefillReleaseId = isset($_GET['release_id']) ? (int)$_GET['release_id'] : 0;
$prefillCondition = $_GET['condition'] ?? '';
$prefillQty = isset($_GET['qty']) ? (int)$_GET['qty'] : 1;
$prefillPrice = isset($_GET['price']) ? (float)$_GET['price'] : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="text-warning display-6 fw-bold">
            <i class="fa-solid fa-inbox me-2"></i>Request Management
        </h2>
        <p class="text-secondary">Submit and track price adjustments and transfer requests</p>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= h($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= h($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- 左侧菜单 -->
    <div class="col-md-3">
        <div class="list-group bg-dark">
            <a href="?action=inbox" class="list-group-item list-group-item-action bg-dark text-light border-secondary <?= $action === 'inbox' ? 'active' : '' ?>">
                <i class="fa-solid fa-inbox me-2"></i>All Requests
                <span class="badge bg-secondary float-end"><?= count($sentRequests) ?></span>
            </a>
            <a href="?action=pending" class="list-group-item list-group-item-action bg-dark text-light border-secondary <?= $action === 'pending' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock me-2 text-warning"></i>Pending
                <span class="badge bg-warning text-dark float-end"><?= $pendingCount ?></span>
            </a>
            <a href="?action=approved" class="list-group-item list-group-item-action bg-dark text-light border-secondary <?= $action === 'approved' ? 'active' : '' ?>">
                <i class="fa-solid fa-check me-2 text-success"></i>Approved
                <span class="badge bg-success float-end"><?= $approvedCount ?></span>
            </a>
            <a href="?action=rejected" class="list-group-item list-group-item-action bg-dark text-light border-secondary <?= $action === 'rejected' ? 'active' : '' ?>">
                <i class="fa-solid fa-times me-2 text-danger"></i>Rejected
                <span class="badge bg-danger float-end"><?= $rejectedCount ?></span>
            </a>
            <hr class="border-secondary my-2">
            <a href="?action=price" class="list-group-item list-group-item-action bg-dark text-light border-secondary <?= $action === 'price' ? 'active' : '' ?>">
                <i class="fa-solid fa-tag me-2 text-info"></i>New Price Request
            </a>
            <a href="?action=transfer" class="list-group-item list-group-item-action bg-dark text-light border-secondary <?= $action === 'transfer' ? 'active' : '' ?>">
                <i class="fa-solid fa-truck me-2 text-primary"></i>New Transfer Request
            </a>
        </div>
    </div>

    <!-- 右侧内容 -->
    <div class="col-md-9">
        <?php if ($action === 'price'): ?>
        <!-- 新建调价申请表单 -->
        <div class="card bg-dark border-info">
            <div class="card-header border-info">
                <h5 class="mb-0"><i class="fa-solid fa-tag me-2"></i>Submit Price Adjustment Request</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="submit_price">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Album</label>
                            <select name="release_id" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">-- Select Album --</option>
                                <?php foreach ($releases as $r): ?>
                                <option value="<?= $r['ReleaseID'] ?>" <?= $prefillReleaseId == $r['ReleaseID'] ? 'selected' : '' ?>>
                                    <?= h($r['Title']) ?> - <?= h($r['ArtistName']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Condition</label>
                            <select name="condition_grade" class="form-select bg-dark text-light border-secondary" required>
                                <?php foreach (['New', 'Mint', 'NM', 'VG+', 'VG'] as $cond): ?>
                                <option value="<?= $cond ?>" <?= $prefillCondition === $cond ? 'selected' : '' ?>><?= $cond ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control bg-dark text-light border-secondary"
                                   value="<?= $prefillQty ?>" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Current Price</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary">$</span>
                                <input type="number" step="0.01" name="current_price"
                                       class="form-control bg-dark text-light border-secondary"
                                       value="<?= $prefillPrice ?>" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requested Price</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">$</span>
                                <input type="number" step="0.01" name="requested_price"
                                       class="form-control bg-dark text-light border-secondary" min="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control bg-dark text-light border-secondary" rows="3"
                                  placeholder="Explain why this price adjustment is needed..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-info">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit Request
                    </button>
                    <a href="?action=inbox" class="btn btn-outline-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>

        <?php elseif ($action === 'transfer'): ?>
        <!-- 新建调货申请表单 -->
        <div class="card bg-dark border-primary">
            <div class="card-header border-primary">
                <h5 class="mb-0"><i class="fa-solid fa-truck me-2"></i>Submit Transfer Request</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="submit_transfer">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Album</label>
                            <select name="release_id" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">-- Select Album --</option>
                                <?php foreach ($releases as $r): ?>
                                <option value="<?= $r['ReleaseID'] ?>" <?= $prefillReleaseId == $r['ReleaseID'] ? 'selected' : '' ?>>
                                    <?= h($r['Title']) ?> - <?= h($r['ArtistName']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Condition</label>
                            <select name="condition_grade" class="form-select bg-dark text-light border-secondary" required>
                                <?php foreach (['New', 'Mint', 'NM', 'VG+', 'VG'] as $cond): ?>
                                <option value="<?= $cond ?>" <?= $prefillCondition === $cond ? 'selected' : '' ?>><?= $cond ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Quantity Needed</label>
                            <input type="number" name="quantity" class="form-control bg-dark text-light border-secondary"
                                   value="<?= $prefillQty ?>" min="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Request From Shop</label>
                            <select name="to_shop_id" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">-- Select Source Shop --</option>
                                <?php foreach ($otherShops as $s): ?>
                                <option value="<?= $s['ShopID'] ?>">
                                    <?= h($s['Name']) ?> (<?= $s['Type'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Items will be transferred TO your store (<?= h($_SESSION['shop_name']) ?>)</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control bg-dark text-light border-secondary" rows="3"
                                  placeholder="Explain why this transfer is needed..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit Request
                    </button>
                    <a href="?action=inbox" class="btn btn-outline-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- 申请列表 -->
        <?php
        $filteredRequests = $sentRequests;
        if ($action === 'pending') {
            $filteredRequests = array_filter($sentRequests, fn($r) => $r['Status'] === 'Pending');
        } elseif ($action === 'approved') {
            $filteredRequests = array_filter($sentRequests, fn($r) => $r['Status'] === 'Approved');
        } elseif ($action === 'rejected') {
            $filteredRequests = array_filter($sentRequests, fn($r) => $r['Status'] === 'Rejected');
        }
        ?>

        <?php if (empty($filteredRequests)): ?>
        <div class="alert alert-info">No requests found.</div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($filteredRequests as $req): ?>
            <div class="list-group-item bg-dark border-secondary mb-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">
                            <?php if ($req['RequestType'] === 'PriceAdjustment'): ?>
                            <span class="badge bg-info me-2"><i class="fa-solid fa-tag me-1"></i>Price</span>
                            <?php else: ?>
                            <span class="badge bg-primary me-2"><i class="fa-solid fa-truck me-1"></i>Transfer</span>
                            <?php endif; ?>
                            <?= h($req['Title']) ?> - <?= h($req['ArtistName']) ?>
                        </h6>
                        <p class="mb-1 text-muted small">
                            <span class="badge bg-secondary"><?= h($req['ConditionGrade']) ?></span>
                            x<?= $req['Quantity'] ?>
                            <?php if ($req['RequestType'] === 'PriceAdjustment'): ?>
                            | <?= formatPrice($req['CurrentPrice']) ?> → <span class="text-success"><?= formatPrice($req['RequestedPrice']) ?></span>
                            <?php else: ?>
                            | From: <?= h($req['ToShopName'] ?? 'N/A') ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($req['Reason']): ?>
                        <p class="mb-1 small"><i class="fa-solid fa-comment me-1"></i><?= h($req['Reason']) ?></p>
                        <?php endif; ?>
                        <?php if ($req['AdminResponseNote']): ?>
                        <p class="mb-0 small text-warning">
                            <i class="fa-solid fa-reply me-1"></i>Admin: <?= h($req['AdminResponseNote']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <?php
                        $statusBadge = [
                            'Pending' => 'warning',
                            'Approved' => 'success',
                            'Rejected' => 'danger'
                        ];
                        ?>
                        <span class="badge bg-<?= $statusBadge[$req['Status']] ?> mb-2"><?= $req['Status'] ?></span>
                        <br>
                        <small class="text-muted"><?= formatDate($req['CreatedAt']) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
