<?php
/**
 * 【架构重构】Admin申请处理页面
 * 审批Manager提交的调价申请和调货申请
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

$employeeId = $_SESSION['user_id'];

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $responseNote = trim($_POST['response_note'] ?? '');
    $sourceShopId = (int)($_POST['source_shop_id'] ?? 0);

    if ($requestId && in_array($action, ['approve', 'reject'])) {
        $approved = ($action === 'approve');

        // 【新增】对于调货申请的批准，需要先验证并设置源店铺
        if ($approved && $sourceShopId > 0) {
            // 获取申请的详细信息
            $reqStmt = $pdo->prepare("SELECT ReleaseID, ConditionGrade, Quantity, RequestType FROM ManagerRequest WHERE RequestID = ?");
            $reqStmt->execute([$requestId]);
            $reqInfo = $reqStmt->fetch(PDO::FETCH_ASSOC);

            if ($reqInfo && $reqInfo['RequestType'] === 'TransferRequest') {
                // 【边界检查】验证源店铺的可用库存数量是否足够
                $stockStmt = $pdo->prepare("
                    SELECT COUNT(*) as available
                    FROM StockItem
                    WHERE ShopID = ? AND ReleaseID = ? AND ConditionGrade = ? AND Status = 'Available'
                ");
                $stockStmt->execute([$sourceShopId, $reqInfo['ReleaseID'], $reqInfo['ConditionGrade']]);
                $stockCount = $stockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$stockCount || $stockCount['available'] < $reqInfo['Quantity']) {
                    flash("Cannot approve: Source shop only has " . ($stockCount['available'] ?? 0) . " available item(s), but " . $reqInfo['Quantity'] . " requested.", 'danger');
                    header("Location: requests.php");
                    exit();
                }
            }

            $updateStmt = $pdo->prepare("UPDATE ManagerRequest SET ToShopID = ? WHERE RequestID = ? AND RequestType = 'TransferRequest'");
            $updateStmt->execute([$sourceShopId, $requestId]);
        }

        $result = DBProcedures::respondToRequest($pdo, $requestId, $employeeId, $approved, $responseNote);

        if ($result) {
            $statusText = $approved ? 'Approved' : 'Rejected';
            flash("Request #$requestId has been $statusText.", $approved ? 'success' : 'warning');
        } else {
            flash("Failed to process request #$requestId.", 'danger');
        }
    } else {
        flash("Invalid request.", 'danger');
    }

    header("Location: requests.php");
    exit();
}

// ========== 数据准备 ==========
$filter = $_GET['filter'] ?? 'pending';
$validFilters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $validFilters)) {
    $filter = 'pending';
}

// 获取请求列表
$pendingRequests = DBProcedures::getAdminPendingRequests($pdo);
$allRequests = DBProcedures::getAdminAllRequests($pdo);

// 【新增】获取所有店铺信息（用于调货申请的源店铺选择）
$shops = DBProcedures::getShopList($pdo);

// 【新增】为每个调货申请查询其他店铺的库存情况
function getOtherShopsInventory($pdo, $releaseId, $conditionGrade, $excludeShopId) {
    $stmt = $pdo->prepare("
        SELECT si.ShopID, s.Name as ShopName, s.Type as ShopType,
               COUNT(*) as AvailableQuantity, MIN(si.UnitPrice) as UnitPrice
        FROM StockItem si
        JOIN Shop s ON si.ShopID = s.ShopID
        WHERE si.ReleaseID = ? AND si.ConditionGrade = ? AND si.Status = 'Available'
        AND si.ShopID != ?
        GROUP BY si.ShopID, s.Name, s.Type
        HAVING AvailableQuantity > 0
        ORDER BY AvailableQuantity DESC
    ");
    $stmt->execute([$releaseId, $conditionGrade, $excludeShopId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 根据过滤条件筛选
$displayRequests = [];
switch ($filter) {
    case 'pending':
        $displayRequests = $pendingRequests;
        break;
    case 'approved':
        $displayRequests = array_filter($allRequests, fn($r) => $r['Status'] === 'Approved');
        break;
    case 'rejected':
        $displayRequests = array_filter($allRequests, fn($r) => $r['Status'] === 'Rejected');
        break;
    case 'all':
    default:
        $displayRequests = $allRequests;
        break;
}

// 统计
$countPending = count($pendingRequests);
$countApproved = count(array_filter($allRequests, fn($r) => $r['Status'] === 'Approved'));
$countRejected = count(array_filter($allRequests, fn($r) => $r['Status'] === 'Rejected'));

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="row mb-4">
    <div class="col-12">
        <h2 class="text-warning display-6 fw-bold">
            <i class="fa-solid fa-clipboard-check me-2"></i>Request Approval Center
        </h2>
        <p class="text-secondary">Review and process price adjustment and transfer requests from store managers</p>
    </div>
</div>

<!-- 统计卡片 -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <a href="?filter=pending" class="text-decoration-none">
            <div class="card bg-dark border-warning h-100 <?= $filter === 'pending' ? 'border-3' : '' ?>">
                <div class="card-body text-center">
                    <h4 class="text-warning mb-0"><?= $countPending ?></h4>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?filter=approved" class="text-decoration-none">
            <div class="card bg-dark border-success h-100 <?= $filter === 'approved' ? 'border-3' : '' ?>">
                <div class="card-body text-center">
                    <h4 class="text-success mb-0"><?= $countApproved ?></h4>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?filter=rejected" class="text-decoration-none">
            <div class="card bg-dark border-danger h-100 <?= $filter === 'rejected' ? 'border-3' : '' ?>">
                <div class="card-body text-center">
                    <h4 class="text-danger mb-0"><?= $countRejected ?></h4>
                    <small class="text-muted">Rejected</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="?filter=all" class="text-decoration-none">
            <div class="card bg-dark border-info h-100 <?= $filter === 'all' ? 'border-3' : '' ?>">
                <div class="card-body text-center">
                    <h4 class="text-info mb-0"><?= count($allRequests) ?></h4>
                    <small class="text-muted">All Requests</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- 请求列表 -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary bg-transparent">
        <h5 class="card-title text-white mb-0">
            <?php
            $filterLabels = [
                'pending' => '<i class="fa-solid fa-clock text-warning me-2"></i>Pending Requests',
                'approved' => '<i class="fa-solid fa-check-circle text-success me-2"></i>Approved Requests',
                'rejected' => '<i class="fa-solid fa-times-circle text-danger me-2"></i>Rejected Requests',
                'all' => '<i class="fa-solid fa-list text-info me-2"></i>All Requests'
            ];
            echo $filterLabels[$filter];
            ?>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($displayRequests)): ?>
            <div class="text-center text-muted py-5">
                <i class="fa-solid fa-inbox fa-3x mb-3"></i>
                <p>No requests found.</p>
            </div>
        <?php else: ?>
            <div class="accordion" id="requestsAccordion">
                <?php foreach ($displayRequests as $idx => $req): ?>
                <?php
                    $isPending = ($req['Status'] === 'Pending');
                    $isPrice = ($req['RequestType'] === 'PriceAdjustment');
                    $borderColor = $isPending ? 'warning' : ($req['Status'] === 'Approved' ? 'success' : 'danger');
                    $typeIcon = $isPrice ? 'fa-tag' : 'fa-truck';
                    $typeLabel = $isPrice ? 'Price Adjustment' : 'Transfer Request';
                    $typeBadge = $isPrice ? 'bg-warning text-dark' : 'bg-primary';
                ?>
                <div class="accordion-item bg-dark border-<?= $borderColor ?>">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-dark text-light" type="button"
                                data-bs-toggle="collapse" data-bs-target="#req<?= $req['RequestID'] ?>">
                            <div class="d-flex align-items-center justify-content-between w-100 me-3">
                                <div>
                                    <span class="badge <?= $typeBadge ?> me-2">
                                        <i class="fa-solid <?= $typeIcon ?> me-1"></i><?= $typeLabel ?>
                                    </span>
                                    <strong>#<?= $req['RequestID'] ?></strong>
                                    <span class="text-muted ms-2"><?= h($req['Title']) ?></span>
                                </div>
                                <div>
                                    <span class="badge bg-secondary me-2"><?= h($req['FromShopName']) ?></span>
                                    <?php if ($isPending): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($req['Status'] === 'Approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="req<?= $req['RequestID'] ?>" class="accordion-collapse collapse" data-bs-parent="#requestsAccordion">
                        <div class="accordion-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-info">Request Details</h6>
                                    <table class="table table-sm table-dark">
                                        <tr>
                                            <td class="text-muted" style="width:40%">Submitted By</td>
                                            <td><?= h($req['RequestedByName']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Shop</td>
                                            <td><?= h($req['FromShopName']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Album</td>
                                            <td>
                                                <strong><?= h($req['Title']) ?></strong><br>
                                                <small class="text-muted"><?= h($req['ArtistName']) ?></small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Condition</td>
                                            <td><span class="badge bg-secondary"><?= h($req['ConditionGrade']) ?></span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Quantity</td>
                                            <td><?= $req['Quantity'] ?></td>
                                        </tr>
                                        <?php if ($isPrice): ?>
                                        <tr>
                                            <td class="text-muted">Current Price</td>
                                            <td class="text-success"><?= formatPrice($req['CurrentPrice']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Requested Price</td>
                                            <td class="text-warning fw-bold"><?= formatPrice($req['RequestedPrice']) ?></td>
                                        </tr>
                                        <?php else: ?>
                                        <tr>
                                            <td class="text-muted">Destination</td>
                                            <td><?= h($req['ToShopName'] ?? 'Warehouse') ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td class="text-muted">Submitted</td>
                                            <td><?= formatDate($req['CreatedAt']) ?></td>
                                        </tr>
                                    </table>

                                    <?php if (!empty($req['Reason'])): ?>
                                    <div class="alert alert-secondary mt-2">
                                        <strong>Reason:</strong> <?= h($req['Reason']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6">
                                    <?php if ($isPending): ?>
                                    <h6 class="text-warning">Take Action</h6>

                                    <?php
                                    // 【重构】对于调货申请，显示库存容器（实时AJAX加载）
                                    if (!$isPrice):
                                    ?>
                                    <div class="mb-3">
                                        <label class="form-label text-info">
                                            <i class="fa-solid fa-warehouse me-1"></i>Available Stock in Other Shops
                                            <small class="text-muted ms-2">(Real-time)</small>
                                        </label>
                                        <!-- 【重构】使用data属性存储参数，AJAX动态加载库存 -->
                                        <div class="stock-inventory-container"
                                             data-release-id="<?= $req['ReleaseID'] ?>"
                                             data-condition="<?= h($req['ConditionGrade']) ?>"
                                             data-from-shop-id="<?= $req['FromShopID'] ?>"
                                             data-quantity="<?= $req['Quantity'] ?>">
                                            <div class="text-center py-3 text-muted">
                                                <i class="fa-solid fa-mouse-pointer me-1"></i>
                                                Click to expand and load real-time inventory data
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="request_id" value="<?= $req['RequestID'] ?>">

                                        <?php
                                        // 【重构】调货申请需要选择源店铺（AJAX动态加载）
                                        if (!$isPrice):
                                        ?>
                                        <div class="mb-3 source-shop-select-container">
                                            <!-- 源店铺选择将通过AJAX动态加载 -->
                                            <div class="text-muted small">
                                                <i class="fa-solid fa-info-circle me-1"></i>
                                                Source shop selection will appear after inventory loads
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <label class="form-label">Response Note (Optional)</label>
                                            <textarea name="response_note" class="form-control bg-dark text-white border-secondary"
                                                      rows="3" placeholder="Add any notes about your decision..."></textarea>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <?php if (!$isPrice): ?>
                                                <!-- 【重构】调货申请：初始禁用，等待AJAX加载后启用 -->
                                                <button type="submit" name="action" value="approve" class="btn btn-secondary" disabled>
                                                    <i class="fa-solid fa-spinner fa-spin me-1"></i>Loading inventory...
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                                    <i class="fa-solid fa-check me-1"></i>Approve Request
                                                </button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
                                                <i class="fa-solid fa-times me-1"></i>Reject Request
                                            </button>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                    <h6 class="text-<?= $req['Status'] === 'Approved' ? 'success' : 'danger' ?>">
                                        Decision: <?= $req['Status'] ?>
                                    </h6>
                                    <table class="table table-sm table-dark">
                                        <tr>
                                            <td class="text-muted" style="width:40%">Processed By</td>
                                            <td><?= h($req['RespondedByName'] ?? 'N/A') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Processed At</td>
                                            <td><?= formatDate($req['UpdatedAt']) ?></td>
                                        </tr>
                                        <?php if (!empty($req['AdminResponseNote'])): ?>
                                        <tr>
                                            <td class="text-muted">Admin Note</td>
                                            <td><?= h($req['AdminResponseNote']) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 【新增】AJAX实时获取库存信息的脚本 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 监听accordion展开事件
    document.querySelectorAll('.accordion-collapse').forEach(function(collapse) {
        collapse.addEventListener('show.bs.collapse', function() {
            const requestId = this.id.replace('req', '');
            const stockContainer = this.querySelector('.stock-inventory-container');
            // 【修复】从accordion-body查找form，而不是从stockContainer查找
            const accordionBody = this.querySelector('.accordion-body');

            if (stockContainer) {
                // 获取申请参数
                const releaseId = stockContainer.dataset.releaseId;
                const condition = stockContainer.dataset.condition;
                const fromShopId = stockContainer.dataset.fromShopId;
                const quantity = stockContainer.dataset.quantity;

                // 显示加载状态
                stockContainer.innerHTML = '<div class="text-center py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading real-time inventory...</div>';

                // 发送AJAX请求获取最新库存
                fetch('requests_api.php?action=get_inventory&release_id=' + releaseId + '&condition=' + encodeURIComponent(condition) + '&exclude_shop=' + fromShopId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateInventoryDisplay(stockContainer, accordionBody, data.inventory, quantity, requestId);
                        } else {
                            stockContainer.innerHTML = '<div class="alert alert-danger py-2"><i class="fa-solid fa-exclamation-circle me-1"></i>' + (data.message || 'Failed to load inventory') + '</div>';
                        }
                    })
                    .catch(error => {
                        stockContainer.innerHTML = '<div class="alert alert-warning py-2"><i class="fa-solid fa-exclamation-triangle me-1"></i>Error loading inventory. <a href="requests.php" class="alert-link">Refresh page</a></div>';
                    });
            }
        });
    });

    // 【修复】更新库存显示 - 添加accordionBody参数来正确查找form
    function updateInventoryDisplay(container, accordionBody, inventory, requiredQty, requestId) {
        // 【修复】从accordionBody查找form，而不是从container查找（因为container在form外面）
        const form = accordionBody ? accordionBody.querySelector('form') : null;

        if (!inventory || inventory.length === 0) {
            container.innerHTML = `
                <div class="alert alert-warning py-2">
                    <i class="fa-solid fa-exclamation-triangle me-1"></i>
                    No matching stock found in other shops for this album/condition.
                </div>`;

            // 禁用approve按钮
            if (form) {
                const approveBtn = form.querySelector('button[value="approve"]');
                if (approveBtn) {
                    approveBtn.disabled = true;
                    approveBtn.className = 'btn btn-secondary';
                    approveBtn.innerHTML = '<i class="fa-solid fa-ban me-1"></i>Cannot Approve (No Stock Available)';
                }
            }
        } else {
            let tableHtml = `
                <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                    <table class="table table-sm table-dark mb-0">
                        <thead class="sticky-top bg-dark">
                            <tr><th>Shop</th><th class="text-center">Qty</th><th class="text-end">Price</th></tr>
                        </thead>
                        <tbody>`;

            inventory.forEach(inv => {
                const whBadge = inv.ShopType === 'Warehouse' ? '<span class="badge bg-secondary ms-1">WH</span>' : '';
                const qtyClass = inv.AvailableQuantity >= requiredQty ? 'bg-success' : 'bg-warning text-dark';
                tableHtml += `<tr>
                    <td>${inv.ShopName}${whBadge}</td>
                    <td class="text-center"><span class="badge ${qtyClass}">${inv.AvailableQuantity}</span></td>
                    <td class="text-end text-warning">¥${parseFloat(inv.UnitPrice).toFixed(2)}</td>
                </tr>`;
            });

            tableHtml += '</tbody></table></div>';
            container.innerHTML = tableHtml;

            // 【修复】更新源店铺选择下拉框和按钮
            if (form) {
                const selectContainer = form.querySelector('.source-shop-select-container');
                if (selectContainer) {
                    let selectHtml = `
                        <label class="form-label">Select Source Shop <span class="text-danger">*</span></label>
                        <select name="source_shop_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">-- Choose source shop --</option>`;

                    inventory.forEach(inv => {
                        selectHtml += `<option value="${inv.ShopID}">${inv.ShopName} (${inv.AvailableQuantity} available @ ¥${parseFloat(inv.UnitPrice).toFixed(2)})</option>`;
                    });

                    selectHtml += '</select><small class="text-muted">Stock will be transferred from the selected shop</small>';
                    selectContainer.innerHTML = selectHtml;
                }

                // 启用approve按钮
                const approveBtn = form.querySelector('button[value="approve"]');
                if (approveBtn) {
                    approveBtn.disabled = false;
                    approveBtn.className = 'btn btn-success';
                    approveBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Approve Request';
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
