<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Staff');
require_once __DIR__ . '/../../includes/header.php';

$shopId = $_SESSION['shop_id'] ?? 1;

// 处理回购提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $releaseId = $_POST['release_id'];
        $condition = $_POST['condition'];
        $buyPrice = $_POST['buy_price'];
        $resalePrice = $_POST['resale_price'];
        $customerId = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
        $empId = $_SESSION['user_id'];

        // 使用存储过程处理回购（一站式处理）
        $stmt = $pdo->prepare("CALL sp_process_buyback(?, ?, ?, ?, 1, ?, ?, ?, @buyback_id)");
        $stmt->execute([
            $customerId,
            $empId,
            $shopId,
            $releaseId,
            $buyPrice,      // 回购单价（支付给客户）
            $condition,     // 品相
            $resalePrice    // 转售价格
        ]);

        // 获取创建的回购订单ID
        $result = $pdo->query("SELECT @buyback_id AS buyback_id")->fetch();
        $buybackId = $result['buyback_id'];

        if ($buybackId > 0) {
            flash("Buyback processed successfully. Buyback Order #$buybackId created. Item added to inventory.", 'success');
        } else {
            throw new Exception("Failed to process buyback.");
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        flash("Error processing buyback: " . $e->getMessage(), 'danger');
    }
    header("Location: buyback.php");
    exit();
}

$releases = $pdo->query("SELECT ReleaseID, Title, ArtistName FROM ReleaseAlbum ORDER BY Title")->fetchAll();
$customers = $pdo->query("SELECT CustomerID, Name, Email FROM Customer ORDER BY Name")->fetchAll();

// 【新增】使用 vw_buyback_orders 视图获取本店回购历史
$recentBuybacks = $pdo->prepare("SELECT * FROM vw_buyback_orders WHERE ShopName = (SELECT Name FROM Shop WHERE ShopID = ?) ORDER BY BuybackDate DESC LIMIT 10");
$recentBuybacks->execute([$shopId]);
$recentBuybacks = $recentBuybacks->fetchAll();
?>

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

    <!-- 【新增】回购历史列表，使用 vw_buyback_orders 视图 -->
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
