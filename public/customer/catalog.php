<?php
/**
 * 【架构重构】商品目录页面
 * 表现层 - 按专辑分组显示，支持选择店铺浏览
 * 
 * 【修复】添加店铺选择功能：
 * - 用户可以选择长沙店、上海店或仓库浏览商品
 * - 根据选择的店铺显示对应库存
 * - 店铺选择保存到Session，影响后续购物车
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Customer');

// ========== 店铺选择处理 ==========
$shops = DBProcedures::getShopList($pdo);

// 处理店铺切换
if (isset($_GET['shop_id'])) {
    $requestedShopId = (int)$_GET['shop_id'];
    // 验证店铺存在
    $validShop = false;
    foreach ($shops as $shop) {
        if ($shop['ShopID'] == $requestedShopId) {
            $validShop = true;
            break;
        }
    }
    if ($validShop) {
        // 如果切换了店铺，清空购物车
        if (isset($_SESSION['selected_shop_id']) && $_SESSION['selected_shop_id'] != $requestedShopId) {
            if (!empty($_SESSION['cart'])) {
                flash('You changed store location. Your cart has been cleared.', 'warning');
                $_SESSION['cart'] = [];
            }
        }
        $_SESSION['selected_shop_id'] = $requestedShopId;
    }
}

// 默认选择仓库（如果没有选择过）
if (!isset($_SESSION['selected_shop_id'])) {
    // 找到仓库的ShopID
    foreach ($shops as $shop) {
        if ($shop['Type'] == 'Warehouse') {
            $_SESSION['selected_shop_id'] = $shop['ShopID'];
            break;
        }
    }
}

$selectedShopId = $_SESSION['selected_shop_id'];

// 获取选中店铺信息
$selectedShop = null;
foreach ($shops as $shop) {
    if ($shop['ShopID'] == $selectedShopId) {
        $selectedShop = $shop;
        break;
    }
}

// ========== 数据准备 ==========
$search = $_GET['q'] ?? '';
$genre  = $_GET['genre'] ?? '';

// 使用修改后的函数，传入店铺ID
$pageData = prepareCatalogPageDataByShop($pdo, $selectedShopId, $search, $genre);
$items = $pageData['items'];
$genres = $pageData['genres'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h1 class="display-5 text-warning fw-bold mb-0">Vinyl Catalog</h1>
        <p class="text-secondary lead">Explore our collection of timeless records.</p>
    </div>
<div class="col-md-6">
        <div class="d-flex flex-column align-items-md-end">
            <span class="text-secondary small mb-2 text-uppercase" style="letter-spacing: 1px;">
                <i class="fa-solid fa-location-dot me-1"></i>Select Location
            </span>
            
            <div class="btn-group shadow" role="group">
                <?php foreach ($shops as $shop): ?>
                    <?php 
                    $isSelected = ($shop['ShopID'] == $selectedShopId);
                    // 优化样式：选中为醒目黄，未选中为深灰背景+灰色文字（去除杂乱边框）
                    $btnClass = $isSelected 
                        ? 'btn-warning text-dark fw-bold' 
                        : 'btn-dark text-secondary border-secondary';
                    $icon = $shop['Type'] == 'Warehouse' ? 'fa-warehouse' : 'fa-store';
                    ?>
                    <a href="?shop_id=<?= $shop['ShopID'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $genre ? '&genre='.urlencode($genre) : '' ?>" 
                       class="btn btn-sm <?= $btnClass ?> py-2"
                       style="min-width: 120px;"> <i class="fa-solid <?= $icon ?> me-2"></i><?= h($shop['Name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 店铺信息提示 -->
<div class="alert alert-info bg-dark border-info mb-4">
    <div class="d-flex align-items-center">
        <i class="fa-solid fa-circle-info me-2 text-info"></i>
        <div>
            <strong class="text-info"><?= h($selectedShop['Name']) ?></strong>
            <?php if ($selectedShop['Type'] == 'Warehouse'): ?>
                <span class="text-muted ms-2">| Online shipping only</span>
            <?php else: ?>
                <span class="text-muted ms-2">| Pick up in store or online shipping available</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <form class="d-flex gap-2 justify-content-end" method="GET">
            <input type="hidden" name="shop_id" value="<?= $selectedShopId ?>">
            <select name="genre" class="form-select w-auto">
                <option value="">All Genres</option>
                <?php foreach($genres as $g): ?>
                    <option value="<?= h($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= h($g) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control w-auto" type="search" name="q" placeholder="Artist or Title..." value="<?= h($search) ?>">
            <button class="btn btn-outline-warning" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if($search || $genre): ?>
                <a href="catalog.php?shop_id=<?= $selectedShopId ?>" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
    <?php if (empty($items)): ?>
        <div class="col-12 text-center py-5">
            <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-compact-disc"></i></div>
            <h3 class="text-white">No records found at this location.</h3>
            <p class="text-muted">Try a different store or adjust your search filters.</p>
            <a href="catalog.php?shop_id=<?= $selectedShopId ?>" class="btn btn-warning mt-3">Clear Filters</a>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="col">
                <a href="release.php?id=<?= $item['ReleaseID'] ?>&shop_id=<?= $selectedShopId ?>" class="text-decoration-none">
                    <div class="card h-100 border-0 catalog-card">
                        <div class="position-relative pt-3">
                            <div class="vinyl-placeholder">
                                <div class="vinyl-center">
                                    <div>Retro<br>Echo</div>
                                </div>
                            </div>
                            <span class="position-absolute top-0 end-0 m-2 badge bg-dark border border-secondary text-warning">
                                <?= h($item['Genre']) ?>
                            </span>
                        </div>

                        <div class="card-body text-center">
                            <h5 class="card-title text-white mb-1 text-truncate" title="<?= h($item['Title']) ?>">
                                <?= h($item['Title']) ?>
                            </h5>
                            <p class="card-text text-warning small mb-3"><?= h($item['ArtistName']) ?></p>

                            <div class="d-flex justify-content-center gap-1 mb-3 flex-wrap">
                                <?php
                                    $conditions = explode(', ', $item['AvailableConditions']);
                                    foreach ($conditions as $cond):
                                        $condClass = match($cond) {
                                            'New', 'Mint' => 'bg-success text-white',
                                            'NM', 'VG+'   => 'bg-info text-dark',
                                            default       => 'bg-secondary text-light'
                                        };
                                ?>
                                    <span class="badge <?= $condClass ?> border border-dark rounded-pill" style="font-size: 0.65rem;">
                                        <?= h($cond) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($item['MinPrice'] == $item['MaxPrice']): ?>
                                <h4 class="fw-bold mb-2"><?= formatPrice($item['MinPrice']) ?></h4>
                            <?php else: ?>
                                <h4 class="fw-bold mb-2"><?= formatPrice($item['MinPrice']) ?> - <?= formatPrice($item['MaxPrice']) ?></h4>
                            <?php endif; ?>

                            <div class="text-muted small mb-2">
                                <i class="fa-solid fa-box me-1"></i><?= $item['TotalAvailable'] ?> in stock
                            </div>

                            <div class="d-grid">
                                <span class="btn btn-outline-warning btn-sm">
                                    <i class="fa-solid fa-eye me-1"></i> View Details
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.catalog-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.catalog-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(255, 193, 7, 0.15);
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>