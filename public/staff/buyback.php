<?php
/**
 * Buyback回购页面
 * 
 * 【修复】使用修改后的sp_process_buyback，自动计算并赠送积分
 * 【限制】只有门店员工可以访问，仓库员工无此功能
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Staff');

// 【修复】使用正确的Session结构
$employeeId = $_SESSION['user_id'];
$shopId = $_SESSION['shop_id'];
$stmt = $pdo->prepare("
    SELECT e.*, s.Name as ShopName, s.Type as ShopType
    FROM Employee e
    JOIN Shop s ON e.ShopID = s.ShopID
    WHERE e.EmployeeID = ?
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// 仓库员工不能进行回购
if ($employee['ShopType'] == 'Warehouse') {
    flash('Buyback is only available at retail locations.', 'warning');
    header('Location: fulfillment.php');
    exit;
}

$errors = [];
$success = false;

// 处理回购提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $releaseId = (int)($_POST['release_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unitPrice = (float)($_POST['unit_price'] ?? 0);
    $condition = $_POST['condition'] ?? 'VG';
    $resalePrice = (float)($_POST['resale_price'] ?? 0);
    
    // 验证
    if ($releaseId <= 0) {
        $errors[] = 'Please select a release.';
    }
    if ($quantity <= 0) {
        $errors[] = 'Quantity must be at least 1.';
    }
    if ($unitPrice <= 0) {
        $errors[] = 'Purchase price must be greater than 0.';
    }
    if ($resalePrice <= 0) {
        $errors[] = 'Resale price must be greater than 0.';
    }
    
    if (empty($errors)) {
        try {
            // 【修复】调用修改后的存储过程，会自动计算积分
            $buybackId = DBProcedures::processBuyback(
                $pdo,
                $customerId ?: null,
                $employeeId,
                $shopId,
                $releaseId,
                $quantity,
                $unitPrice,
                $condition,
                $resalePrice
            );
            
            if ($buybackId > 0) {
                $totalPayment = $quantity * $unitPrice;
                $pointsEarned = floor($totalPayment * 0.5);
                
                $message = "Buyback completed! Order #$buybackId";
                if ($customerId && $pointsEarned > 0) {
                    $message .= " - Customer earned $pointsEarned points.";
                }
                
                flash($message, 'success');
                header('Location: buyback.php');
                exit;
            } else {
                $errors[] = 'Buyback processing failed.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// 获取Release列表
$stmt = $pdo->query("
    SELECT r.ReleaseID, r.Title, r.ArtistName, r.Genre
    FROM ReleaseAlbum r
    ORDER BY r.Title
");
$releases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 【修复】获取客户列表（移除不存在的Phone字段）
$stmt = $pdo->query("SELECT CustomerID, Name, Email, Points FROM Customer ORDER BY Name");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 条件等级选项 - 【修复】只保留前5个标准条件
$conditions = ['New', 'Mint', 'NM', 'VG+', 'VG'];

require_once __DIR__ . '/../../includes/header.php';
// 【修复】移除staff_nav.php，因为header.php已包含员工导航菜单
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="display-5 text-warning fw-bold">
            <i class="fa-solid fa-rotate-left me-2"></i>Buyback
        </h1>
        <p class="text-muted">
            <i class="fa-solid fa-store me-1"></i><?= h($employee['ShopName']) ?>
            <span class="ms-3"><i class="fa-solid fa-coins me-1"></i>Customers earn 0.5 points per ¥1</span>
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card bg-dark border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fa-solid fa-file-invoice me-2"></i>New Buyback</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="buyback-form">
                    <div class="row">
                        <!-- 客户选择 -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-warning">Customer (Optional)</label>
                            <select name="customer_id" id="customer_id" class="form-select bg-dark text-white border-secondary">
                                <option value="">Walk-in (No points)</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['CustomerID'] ?>" 
                                            data-points="<?= $customer['Points'] ?>">
                                        <?= h($customer['Name']) ?> - <?= h($customer['Email']) ?> 
                                        (<?= number_format($customer['Points']) ?> pts)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Link to customer to award points</small>
                        </div>
                        
                        <!-- Release选择 -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-warning">Release *</label>
                            <select name="release_id" id="release_id" class="form-select bg-dark text-white border-secondary" required>
                                <option value="">-- Select Release --</option>
                                <?php foreach ($releases as $release): ?>
                                    <option value="<?= $release['ReleaseID'] ?>">
                                        <?= h($release['Title']) ?> - <?= h($release['ArtistName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- 数量 -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-warning">Quantity *</label>
                            <input type="number" name="quantity" id="quantity" class="form-control bg-dark text-white border-secondary" 
                                   value="1" min="1" required>
                        </div>
                        
                        <!-- 条件 -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-warning">Condition *</label>
                            <select name="condition" class="form-select bg-dark text-white border-secondary" required>
                                <?php foreach ($conditions as $cond): ?>
                                    <option value="<?= $cond ?>" <?= $cond == 'VG' ? 'selected' : '' ?>>
                                        <?= $cond ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- 收购价 -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-warning">Purchase Price (¥) *</label>
                            <input type="number" name="unit_price" id="unit_price" class="form-control bg-dark text-white border-secondary" 
                                   step="0.01" min="0.01" required placeholder="We pay">
                        </div>
                        
                        <!-- 转售价 -->
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-warning">Resale Price (¥) *</label>
                            <input type="number" name="resale_price" id="resale_price" class="form-control bg-dark text-white border-secondary" 
                                   step="0.01" min="0.01" required placeholder="List price">
                        </div>
                    </div>
                    
                    <hr class="border-secondary">
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">Total Payment:</span>
                            <span class="fs-4 text-warning fw-bold ms-2" id="total-payment">¥0.00</span>
                            <span class="text-muted ms-3" id="points-preview"></span>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fa-solid fa-check me-1"></i> Process Buyback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 右侧：最近回购记录 -->
    <div class="col-lg-4">
        <div class="card bg-dark border-secondary">
            <div class="card-header bg-dark border-secondary">
                <h5 class="mb-0 text-warning"><i class="fa-solid fa-history me-2"></i>Recent Buybacks</h5>
            </div>
            <div class="card-body p-0">
                <?php
                // 【修复】获取所有buyback详细信息
                $stmt = $pdo->prepare("
                    SELECT bo.BuybackOrderID, bo.BuybackDate, bo.TotalPayment, bo.Status,
                           c.Name as CustomerName, c.Email as CustomerEmail,
                           r.Title, r.ArtistName,
                           bol.Quantity, bol.UnitPrice, bol.ConditionGrade,
                           (bol.Quantity * bol.UnitPrice) as LineTotal,
                           e.Name as ProcessedByName
                    FROM BuybackOrder bo
                    LEFT JOIN Customer c ON bo.CustomerID = c.CustomerID
                    JOIN BuybackOrderLine bol ON bo.BuybackOrderID = bol.BuybackOrderID
                    JOIN ReleaseAlbum r ON bol.ReleaseID = r.ReleaseID
                    JOIN Employee e ON bo.ProcessedByEmployeeID = e.EmployeeID
                    WHERE bo.ShopID = ?
                    ORDER BY bo.BuybackDate DESC
                    LIMIT 15
                ");
                $stmt->execute([$shopId]);
                $recentBuybacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if (empty($recentBuybacks)): ?>
                    <p class="text-muted text-center py-4">No recent buybacks</p>
                <?php else: ?>
                    <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                        <?php foreach ($recentBuybacks as $bb): ?>
                            <div class="list-group-item bg-dark border-secondary">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-warning">#<?= $bb['BuybackOrderID'] ?></strong>
                                    <small class="text-muted"><?= date('M d, H:i', strtotime($bb['BuybackDate'])) ?></small>
                                </div>
                                <div class="text-white"><?= h($bb['Title']) ?></div>
                                <div class="text-muted small"><?= h($bb['ArtistName']) ?></div>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <span class="badge bg-secondary"><?= h($bb['ConditionGrade']) ?></span>
                                    <span class="badge bg-info text-dark">x<?= $bb['Quantity'] ?></span>
                                    <span class="badge bg-dark border border-secondary">Buy: <?= formatPrice($bb['UnitPrice']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mt-2 pt-2 border-top border-secondary">
                                    <small class="text-muted">
                                        <i class="fa-solid fa-user me-1"></i><?= $bb['CustomerName'] ? h($bb['CustomerName']) : 'Walk-in' ?>
                                        <?php if ($bb['CustomerEmail']): ?>
                                            <br><small class="text-muted ps-3"><?= h($bb['CustomerEmail']) ?></small>
                                        <?php endif; ?>
                                    </small>
                                    <span class="text-success fw-bold"><?= formatPrice($bb['TotalPayment']) ?></span>
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="fa-solid fa-user-check me-1"></i>By: <?= h($bb['ProcessedByName']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const priceInput = document.getElementById('unit_price');
    const totalDisplay = document.getElementById('total-payment');
    const pointsDisplay = document.getElementById('points-preview');
    const customerSelect = document.getElementById('customer_id');
    
    function updateTotal() {
        const quantity = parseInt(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = quantity * price;
        
        totalDisplay.textContent = '¥' + total.toFixed(2);
        
        // 计算积分预览
        const customerId = customerSelect.value;
        if (customerId && total > 0) {
            const points = Math.floor(total * 0.5);
            pointsDisplay.innerHTML = '<i class="fa-solid fa-coins text-warning me-1"></i>+' + points + ' points';
        } else {
            pointsDisplay.textContent = '';
        }
    }
    
    quantityInput.addEventListener('input', updateTotal);
    priceInput.addEventListener('input', updateTotal);
    customerSelect.addEventListener('change', updateTotal);
    
    updateTotal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>