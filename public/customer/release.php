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
$tracks = $pageData['tracks'] ?? [];

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
            <p class="text-light opacity-75"><?= nl2br(h($release['Description'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($tracks)): ?>
        <div class="mb-4">
            <?php $trackCount = count($tracks); ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-white mb-0">
                    <a class="text-decoration-none track-list-toggle" data-bs-toggle="collapse" href="#trackListCollapse" role="button" aria-expanded="false" aria-controls="trackListCollapse">
                        <i class="fa-solid fa-music me-2"></i>Track List
                        <span class="badge bg-secondary ms-2"><?= $trackCount ?> tracks</span>
                        <i class="fa-solid fa-chevron-down ms-2 toggle-icon"></i>
                    </a>
                </h5>
            </div>
            <div class="collapse" id="trackListCollapse">
                <div class="list-group list-group-flush">
                    <?php foreach ($tracks as $track): ?>
                    <div class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center py-2 px-3">
                        <div>
                            <span class="badge bg-secondary me-2"><?= $track['TrackNumber'] ?></span>
                            <span class="text-light"><?= h($track['Title']) ?></span>
                        </div>
                        <?php if ($track['Duration']): ?>
                        <span class="text-muted small"><?= h($track['Duration']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
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
                    <div class="list-group-item bg-dark border-secondary py-3">
                        <form action="../api/customer/cart.php" method="POST" class="d-flex align-items-center flex-wrap gap-3">
                            <input type="hidden" name="action" value="add_multiple">
                            <input type="hidden" name="release_id" value="<?= $release['ReleaseID'] ?>">
                            <input type="hidden" name="condition" value="<?= h($stock['ConditionGrade']) ?>">
                            <input type="hidden" name="shop_id" value="<?= $shopId ?>">

                            <div style="min-width: 80px;">
                                <span class="badge <?= $condClass ?> fs-6 px-3 py-2">
                                    <?= h($stock['ConditionGrade']) ?>
                                </span>
                            </div>

                            <div style="min-width: 100px;">
                                <div class="text-warning fw-bold fs-5 lh-1">
                                    <?= formatPrice($stock['UnitPrice']) ?>
                                </div>
                                <small class="text-muted">per item</small>
                            </div>

                            <div style="min-width: 120px;">
                                <div class="text-light d-flex align-items-center">
                                    <i class="fa-solid fa-cubes me-1"></i>
                                    <strong><?= $stock['AvailableQuantity'] ?></strong>&nbsp;available
                                </div>
                            </div>

                            <div class="ms-auto">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-secondary border-secondary text-light">Qty</span>
                                    <select name="quantity" class="form-select form-select-sm bg-dark text-light border-secondary">
                                        <?php for ($i = 1; $i <= $stock['AvailableQuantity']; $i++): ?>
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
