<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer');
require_once __DIR__ . '/../../includes/header.php';

// 处理筛选参数
$search = $_GET['q'] ?? '';
$genre  = $_GET['genre'] ?? '';
$params = [];

// 使用视图查询
$sql = "SELECT * FROM vw_customer_catalog WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (Title LIKE :search OR ArtistName LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($genre)) {
    $sql .= " AND Genre = :genre";
    $params[':genre'] = $genre;
}

$sql .= " ORDER BY ReleaseID DESC, ConditionGrade ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // 获取流派列表
    $genres = $pdo->query("SELECT DISTINCT Genre FROM ReleaseAlbum ORDER BY Genre")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $items = [];
}
?>

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
                <div class="card h-100 border-0">
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
                        
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <?php 
                                $condClass = match($item['ConditionGrade']) {
                                    'New', 'Mint' => 'bg-success text-white',
                                    'NM', 'VG+'   => 'bg-info text-dark',
                                    default       => 'bg-secondary text-light'
                                };
                            ?>
                            <span class="badge <?= $condClass ?> border border-dark rounded-pill">
                                <?= h($item['ConditionGrade']) ?>
                            </span>
                        </div>

                        <h4 class="fw-bold mb-3"><?= formatPrice($item['UnitPrice']) ?></h4>

                        <div class="d-grid">
                            <form action="cart_action.php" method="POST">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="stock_id" value="<?= $item['StockItemID'] ?>">
                                <button type="submit" class="btn btn-warning w-100 btn-sm">
                                    <i class="fa-solid fa-cart-plus me-1"></i> Add to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-location-dot me-1"></i><?= h($item['LocationName']) ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>