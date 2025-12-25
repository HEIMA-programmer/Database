<?php
/**
 * 【架构重构】回购页面
 * 表现层 - 仅负责数据展示和用户交互
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Staff');

$shopId = $_SESSION['shop_id'] ?? 1;
$empId = $_SESSION['user_id'];

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'customer_id'   => !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null,
        'employee_id'   => $empId,
        'shop_id'       => $shopId,
        'release_id'    => (int)$_POST['release_id'],
        'condition'     => $_POST['condition'],
        'buy_price'     => (float)$_POST['buy_price'],
        'resale_price'  => (float)$_POST['resale_price']
    ];

    $result = handleBuybackSubmission($pdo, $data);
    flash($result['message'], $result['success'] ? 'success' : 'danger');

    header("Location: buyback.php");
    exit();
}

// ========== 数据准备 ==========
$pageData = prepareBuybackPageData($pdo, $shopId);
$releases = $pageData['releases'];
$customers = $pageData['customers'];
$recentBuybacks = $pageData['recent_buybacks'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="row">
    <div class="col-lg-6">
        <div class="text-center mb-4">
            <h2 class="text-warning"><i class="fa-solid fa-recycle me-2"></i>Used Record Buyback</h2>
            <p class="text-muted">Process customer trade-ins and add to local inventory.</p>
        </div>

        <div class="card bg-dark border-secondary shadow-lg">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label text-info">Seller (Customer)</label>
                        <select name="customer_id" class="form-select">
                            <option value="">-- Walk-in / Anonymous --</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?= $c['CustomerID'] ?>"><?= h($c['Name']) ?> (<?= h($c['Email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Select member to track buyback source. Leave empty for walk-in customers.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-warning">Select Release Album</label>
                        <select name="release_id" class="form-select" required>
                            <option value="">-- Choose Album --</option>
                            <?php foreach($releases as $r): ?>
                                <option value="<?= $r['ReleaseID'] ?>"><?= h($r['Title']) ?> (<?= h($r['ArtistName']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">If album not in DB, create it in Admin > Products first.</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Condition</label>
                            <select name="condition" class="form-select" required>
                                <option value="VG+">Very Good Plus (Standard)</option>
                                <option value="Mint">Mint (Perfect)</option>
                                <option value="NM">Near Mint</option>
                                <option value="VG">Very Good</option>
                                <option value="G">Good</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payout Amount (Cost)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-secondary text-light">¥</span>
                                <input type="number" name="buy_price" class="form-control" placeholder="Paid to customer" step="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-info">Target Resale Price</label>
                        <div class="input-group">
                            <span class="input-group-text bg-secondary border-secondary text-light">¥</span>
                            <input type="number" name="resale_price" class="form-control" placeholder="Price on sticker" step="1" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2">
                        <i class="fa-solid fa-check me-2"></i>Authorize Payout & Print Label
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 回购历史列表 -->
    <div class="col-lg-6">
        <div class="text-center mb-4">
            <h2 class="text-info"><i class="fa-solid fa-history me-2"></i>Recent Buybacks</h2>
            <p class="text-muted">Recent buyback transactions at this location.</p>
        </div>

        <div class="card bg-dark border-secondary">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Payout</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentBuybacks)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No buyback records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentBuybacks as $bb): ?>
                            <tr>
                                <td>#<?= $bb['BuybackOrderID'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= h($bb['CustomerName']) ?></div>
                                    <small class="text-muted"><?= h($bb['CustomerEmail']) ?></small>
                                </td>
                                <td><?= formatDate($bb['BuybackDate']) ?></td>
                                <td><span class="badge bg-secondary"><?= $bb['ItemTypes'] ?> types</span></td>
                                <td class="text-warning fw-bold"><?= formatPrice($bb['TotalPayment']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = match($bb['Status']) {
                                        'Completed' => 'bg-success',
                                        'Pending' => 'bg-warning text-dark',
                                        'Cancelled' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= h($bb['Status']) ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
