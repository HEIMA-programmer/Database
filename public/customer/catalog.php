<?php
/**
 * 【架构重构】商品目录页面
 * 表现层 - 按专辑分组显示，点击查看详情和各条件库存
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Customer');

// ========== 数据准备 ==========
$search = $_GET['q'] ?? '';
$genre  = $_GET['genre'] ?? '';

$pageData = prepareCatalogPageData($pdo, $search, $genre);
$items = $pageData['items'];
$genres = $pageData['genres'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="row mb-5 align-items-end">
    <div class="col-md-6">
        <h1 class="display-5 text-warning fw-bold mb-0">Vinyl Catalog</h1>
        <p class="text-secondary lead">Explore our collection of timeless records.</p>
    </div>
    <div class="col-md-6">
        <form class="d-flex gap-2 justify-content-md-end" method="GET">
            <select name="genre" class="form-select w-auto">
                <option value="">All Genres</option>
                <?php foreach($genres as $g): ?>
                    <option value="<?= h($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= h($g) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control w-auto" type="search" name="q" placeholder="Artist or Title..." value="<?= h($search) ?>">
            <button class="btn btn-outline-warning" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if($search || $genre): ?>
                <a href="catalog.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
    <?php if (empty($items)): ?>
        <div class="col-12 text-center py-5">
            <div class="display-1 text-secondary mb-3"><i class="fa-solid fa-compact-disc"></i></div>
            <h3 class="text-white">No records found.</h3>
            <p class="text-muted">Try adjusting your search or filters.</p>
            <a href="catalog.php" class="btn btn-warning mt-3">Clear Filters</a>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="col">
                <a href="release.php?id=<?= $item['ReleaseID'] ?>" class="text-decoration-none">
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
