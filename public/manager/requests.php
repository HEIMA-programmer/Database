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

// 【修复】兼容多种session结构
$shopId = $_SESSION['user']['ShopID'] ?? $_SESSION['shop_id'] ?? null;
$employeeId = $_SESSION['user_id'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';

if (!$shopId || !$employeeId) {
    flash('Session expired. Please re-login.', 'warning');
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
        // 提交调货申请 - 【修复】to_shop_id可以为0，表示由Admin决定从哪个店调货
        $releaseId = (int)$_POST['release_id'];
        $conditionGrade = $_POST['condition_grade'];
        $quantity = (int)$_POST['quantity'];
        $toShopId = (int)$_POST['to_shop_id']; // 0表示未指定，由Admin决定
        $reason = trim($_POST['reason']);

if ($quantity <= 0) {

            $error = 'Quantity must be greater than 0.';

        } else {

            // 【修复】参数顺序：fromShopId 是 Manager 的店铺（请求方），toShopId 是源店铺（由 Admin 决定，传 NULL）

            // 数据库约束：FromShopID NOT NULL, ToShopID 可为 NULL

            $result = DBProcedures::createTransferRequest(

                $pdo, $employeeId, $shopId, null, $releaseId, $conditionGrade, $quantity, $reason
            );
            if ($result) {
                $message = 'Transfer request submitted successfully! Admin will assign a source store.';
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

// 【架构重构】使用DBProcedures获取当前店铺的库存信息
$shopInventory = DBProcedures::getShopInventoryGrouped($pdo, $shopId);

// 构建库存价格映射（用于JS自动填充）
$inventoryPriceMap = [];
foreach ($shopInventory as $inv) {
    $key = $inv['ReleaseID'] . '_' . $inv['ConditionGrade'];
    $inventoryPriceMap[$key] = [
        'price' => $inv['UnitPrice'],
        'quantity' => $inv['Quantity']
    ];
}

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
        <!-- 新建调价申请表单 - 【修复】基于当前店铺库存，自动填充价格和数量 -->
        <div class="card bg-dark border-info">
            <div class="card-header border-info">
                <h5 class="mb-0"><i class="fa-solid fa-tag me-2"></i>Submit Price Adjustment Request</h5>
            </div>
            <div class="card-body">
                <?php if (empty($shopInventory)): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i>No available inventory in your store to adjust prices.
                </div>
                <?php else: ?>
                <form method="POST" id="priceAdjustForm">
                    <input type="hidden" name="action" value="submit_price">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Album <small class="text-muted">(from your store's inventory)</small></label>
                            <select name="release_id" id="priceReleaseSelect" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">-- Select Album --</option>
                                <?php
                                $uniqueReleases = [];
                                foreach ($shopInventory as $inv) {
                                    if (!isset($uniqueReleases[$inv['ReleaseID']])) {
                                        $uniqueReleases[$inv['ReleaseID']] = $inv;
                                    }
                                }
                                foreach ($uniqueReleases as $r):
                                ?>
                                <option value="<?= $r['ReleaseID'] ?>" <?= $prefillReleaseId == $r['ReleaseID'] ? 'selected' : '' ?>>
                                    <?= h($r['Title']) ?> - <?= h($r['ArtistName']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Condition <small class="text-muted">(based on album selection)</small></label>
                            <select name="condition_grade" id="priceConditionSelect" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">-- Select Album First --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Quantity <small class="text-muted">(all units will be adjusted)</small></label>
                            <input type="number" name="quantity" id="priceQuantity" class="form-control bg-dark text-light border-secondary"
                                   value="<?= $prefillQty ?>" min="1" readonly required>
                            <small class="text-info">Auto-filled based on available stock</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Current Price <small class="text-muted">(from inventory)</small></label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary">¥</span>
                                <input type="number" step="0.01" name="current_price" id="priceCurrentPrice"
                                       class="form-control bg-dark text-light border-secondary"
                                       value="<?= $prefillPrice ?>" min="0" readonly required>
                            </div>
                            <small class="text-info">Auto-filled from inventory</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requested Price</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">¥</span>
                                <input type="number" step="0.01" name="requested_price" id="priceRequestedPrice"
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

                <script>
                // 库存价格映射数据
                const inventoryPriceMap = <?= json_encode($inventoryPriceMap) ?>;
                const shopInventory = <?= json_encode($shopInventory) ?>;
                const prefillReleaseId = <?= $prefillReleaseId ?>;
                const prefillCondition = "<?= $prefillCondition ?>";

                document.addEventListener('DOMContentLoaded', function() {
                    const releaseSelect = document.getElementById('priceReleaseSelect');
                    const conditionSelect = document.getElementById('priceConditionSelect');
                    const quantityInput = document.getElementById('priceQuantity');
                    const currentPriceInput = document.getElementById('priceCurrentPrice');

                    function updateConditionOptions() {
                        const releaseId = releaseSelect.value;
                        conditionSelect.innerHTML = '<option value="">-- Select Condition --</option>';

                        if (!releaseId) {
                            quantityInput.value = '';
                            currentPriceInput.value = '';
                            return;
                        }

                        // 获取该专辑在当前店铺可用的condition
                        const conditions = shopInventory.filter(inv => inv.ReleaseID == releaseId);
                        conditions.forEach(inv => {
                            const option = document.createElement('option');
                            option.value = inv.ConditionGrade;
                            option.textContent = inv.ConditionGrade + ' (x' + inv.Quantity + ')';
                            if (inv.ConditionGrade === prefillCondition) {
                                option.selected = true;
                            }
                            conditionSelect.appendChild(option);
                        });

                        // 如果有预填的condition，触发更新
                        if (prefillCondition) {
                            updatePriceAndQuantity();
                        }
                    }

                    function updatePriceAndQuantity() {
                        const releaseId = releaseSelect.value;
                        const condition = conditionSelect.value;

                        if (!releaseId || !condition) {
                            return;
                        }

                        const key = releaseId + '_' + condition;
                        if (inventoryPriceMap[key]) {
                            currentPriceInput.value = inventoryPriceMap[key].price;
                            quantityInput.value = inventoryPriceMap[key].quantity;
                        }
                    }

                    releaseSelect.addEventListener('change', updateConditionOptions);
                    conditionSelect.addEventListener('change', updatePriceAndQuantity);

                    // 初始化
                    if (prefillReleaseId) {
                        updateConditionOptions();
                    }
                });
                </script>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($action === 'transfer'): ?>
        <!-- 新建调货申请表单 - 【修复】移除店铺选择，由Admin决定从哪个店调货 -->
        <div class="card bg-dark border-primary">
            <div class="card-header border-primary">
                <h5 class="mb-0"><i class="fa-solid fa-truck me-2"></i>Submit Transfer Request</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <strong>Note:</strong> Admin will determine which store has available inventory and fulfill your request.
                    Items will be transferred TO your store (<?= h($_SESSION['shop_name']) ?>).
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="submit_transfer">
                    <!-- 【修复】设置to_shop_id为0，表示由Admin决定 -->
                    <input type="hidden" name="to_shop_id" value="0">

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
                            <label class="form-label">Destination</label>
                            <input type="text" class="form-control bg-dark text-light border-secondary"
                                   value="<?= h($_SESSION['shop_name']) ?>" readonly>
                            <small class="text-muted">Your store - this cannot be changed</small>
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
