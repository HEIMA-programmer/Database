<?php
/**
 * Buyback回购页面
 *
 * 【修复】回购完成后自动计算并赠送积分
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
// 【架构重构Phase2】使用DBProcedures替换直接SQL
$employee = DBProcedures::getEmployeeShopInfo($pdo, $employeeId);

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

// 【架构重构Phase2】获取Release列表
// 【安全修复】不再传递BaseUnitCost到前端，定价逻辑已移至后端API (api_calculate_price.php)
$releases = DBProcedures::getReleaseListWithCost($pdo);

// 【架构重构Phase2】获取客户列表（含积分）
$customers = DBProcedures::getCustomerListWithPoints($pdo);

// 条件等级选项 - 【修复】只保留前5个标准条件
$conditions = ['New', 'Mint', 'NM', 'VG+', 'VG'];

// 【架构重构Phase2】获取当前库存价格映射
// 【修复】传入当前店铺ID，确保只获取本店铺的价格（解决多店铺价格调整后缓存不一致问题）
$priceMap = DBProcedures::getStockPriceMap($pdo, $shopId);

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
                // 【架构重构Phase2】使用DBProcedures替换直接SQL
                $recentBuybacks = DBProcedures::getRecentBuybacksDetail($pdo, $shopId, 15);
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
    const resalePriceInput = document.getElementById('resale_price');
    const totalDisplay = document.getElementById('total-payment');
    const pointsDisplay = document.getElementById('points-preview');
    const customerSelect = document.getElementById('customer_id');
    const releaseSelect = document.getElementById('release_id');
    const conditionSelect = document.querySelector('select[name="condition"]');

    // 【安全修复】移除前端定价逻辑暴露
    // 条件系数和利润率计算已移至后端API (api_calculate_price.php)

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

    // 【安全修复】通过AJAX从后端获取建议价格，不在前端暴露定价逻辑
    async function updateResalePrice() {
        const releaseId = releaseSelect.value;
        const condition = conditionSelect.value;

        if (!releaseId || !condition) {
            resalePriceInput.value = '';
            resalePriceInput.classList.remove('border-success', 'border-warning');
            resalePriceInput.title = '';
            return;
        }

        // 显示加载状态
        resalePriceInput.placeholder = 'Loading...';
        resalePriceInput.disabled = true;

        try {
            const formData = new FormData();
            formData.append('release_id', releaseId);
            formData.append('condition', condition);

            const response = await fetch('api_calculate_price.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                resalePriceInput.value = data.price.toFixed(2);
                if (data.source === 'existing') {
                    resalePriceInput.classList.add('border-success');
                    resalePriceInput.classList.remove('border-warning');
                    resalePriceInput.title = '已自动填充当前库存售价';
                } else {
                    resalePriceInput.classList.add('border-warning');
                    resalePriceInput.classList.remove('border-success');
                    resalePriceInput.title = '建议售价（系统计算）';
                }
            } else {
                resalePriceInput.value = '';
                resalePriceInput.classList.remove('border-success', 'border-warning');
                resalePriceInput.title = '请手动设置转售价格';
            }
        } catch (error) {
            console.error('Error fetching price:', error);
            resalePriceInput.value = '';
            resalePriceInput.classList.remove('border-success', 'border-warning');
            resalePriceInput.title = '请手动设置转售价格';
        } finally {
            resalePriceInput.placeholder = 'List price';
            resalePriceInput.disabled = false;
        }
    }

    quantityInput.addEventListener('input', updateTotal);
    priceInput.addEventListener('input', updateTotal);
    customerSelect.addEventListener('change', updateTotal);

    // 【新增】监听Release和Condition变化，通过AJAX获取建议价格
    releaseSelect.addEventListener('change', updateResalePrice);
    conditionSelect.addEventListener('change', updateResalePrice);

    updateTotal();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>