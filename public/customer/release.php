<?php
/**
 * 【新增】专辑详情页面
 * 显示专辑信息和各条件的库存，支持选择数量加入购物车
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Customer');

// ========== 数据准备 ==========
// ========== 数据准备 ==========
$releaseId = $_GET['id'] ?? 0;
// 新增：获取 shop_id
$shopId = $_GET['shop_id'] ?? ($_SESSION['selected_shop_id'] ?? 0);


$pageData = prepareReleaseDetailData($pdo, $releaseId, $shopId);

if (!$pageData['found']) { 
    flash("Album not found.", 'danger');
    header("Location: catalog.php");
    exit();
}
$release = $pageData['release'];
$stockItems = $pageData['stockItems'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="row g-5">
    <div class="col-md-5">
        <div class="card bg-dark border-secondary p-4">
            <div class="vinyl-placeholder w-100" style="aspect-ratio: 1/1;">
                <div class="vinyl-center" style="width: 35%; height: 35%;">
                    <div><?= h($release['ReleaseYear'] ?? 'Classic') ?><br><small><?= h($release['Genre']) ?></small></div>
                </div>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="catalog.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Catalog
            </a>
        </div>
    </div>

    <div class="col-md-7">
        <div class="mb-2">
            <span class="badge bg-warning text-dark me-2"><?= h($release['Genre']) ?></span>
            <span class="text-secondary"><?= h($release['Format'] ?? 'Vinyl') ?>, <?= $release['ReleaseYear'] ?? 'N/A' ?></span>
        </div>

        <h1 class="display-4 text-white fw-bold mb-2"><?= h($release['Title']) ?></h1>
        <h3 class="text-warning mb-4"><?= h($release['ArtistName']) ?></h3>

        <?php if ($release['Description']): ?>
        <div class="mb-4">
            <p class="text-muted"><?= nl2br(h($release['Description'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="text-white mb-0"><i class="fa-solid fa-box-open me-2"></i>Available Stock</h5>
                <span class="badge bg-success"><?= $release['TotalAvailable'] ?> total</span>
            </div>
        </div>

        <?php if (empty($stockItems)): ?>
            <div class="alert alert-secondary">
                <i class="fa-solid fa-exclamation-circle me-2"></i>
                No stock available at this time.
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($stockItems as $stock): ?>
                    <?php
                        $condClass = match($stock['ConditionGrade']) {
                            'New', 'Mint' => 'bg-success text-white',
                            'NM', 'VG+'   => 'bg-info text-dark',
                            default       => 'bg-secondary text-light'
                        };
                    ?>
                    <div class="list-group-item bg-dark border-secondary">
                        <form action="cart_action.php" method="POST" class="row align-items-center">
                            <input type="hidden" name="action" value="add_multiple">
                            <input type="hidden" name="release_id" value="<?= $release['ReleaseID'] ?>">
                            <input type="hidden" name="condition" value="<?= h($stock['ConditionGrade']) ?>">

                            <div class="col-md-3">
                                <span class="badge <?= $condClass ?> fs-6 px-3 py-2">
                                    <?= h($stock['ConditionGrade']) ?>
                                </span>
                            </div>

                            <div class="col-md-3">
                                <div class="text-warning fw-bold fs-5">
                                    <?= formatPrice($stock['UnitPrice']) ?>
                                </div>
                                <small class="text-muted">per item</small>
                            </div>

                            <div class="col-md-3">
                                <div class="text-light">
                                    <i class="fa-solid fa-cubes me-1"></i>
                                    <strong><?= $stock['AvailableQuantity'] ?></strong> available
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-secondary border-secondary text-light">Qty</span>
                                    <select name="quantity" class="form-select form-select-sm bg-dark text-light border-secondary">
                                        <?php for ($i = 1; $i <= min($stock['AvailableQuantity'], 10); $i++): ?>
                                            <option value="<?= $i ?>"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fa-solid fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 价格摘要 -->
        <div class="card bg-secondary border-0 mt-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="small text-muted">Price Range</div>
                        <?php if ($release['MinPrice'] == $release['MaxPrice']): ?>
                            <div class="fs-4 fw-bold text-white"><?= formatPrice($release['MinPrice']) ?></div>
                        <?php else: ?>
                            <div class="fs-4 fw-bold text-white">
                                <?= formatPrice($release['MinPrice']) ?> - <?= formatPrice($release['MaxPrice']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-6 text-end">
                        <div class="small text-muted">Conditions Available</div>
                        <div class="text-light"><?= h($release['AvailableConditions']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
