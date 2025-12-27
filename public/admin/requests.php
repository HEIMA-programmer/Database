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

    if ($requestId && in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'Approved' : 'Rejected';
        $result = DBProcedures::respondToRequest($pdo, $requestId, $status, $responseNote, $employeeId);

        if ($result) {
            flash("Request #$requestId has been $status.", $action === 'approve' ? 'success' : 'warning');
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
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="request_id" value="<?= $req['RequestID'] ?>">

                                        <div class="mb-3">
                                            <label class="form-label">Response Note (Optional)</label>
                                            <textarea name="response_note" class="form-control bg-dark text-white border-secondary"
                                                      rows="3" placeholder="Add any notes about your decision..."></textarea>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                                <i class="fa-solid fa-check me-1"></i>Approve Request
                                            </button>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
