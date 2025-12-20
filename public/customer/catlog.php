<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
requireRole('Customer'); // 仅限顾客访问
require_once __DIR__ . '/../../includes/header.php';

// --- 处理搜索与筛选参数 ---
$search = $_GET['q'] ?? '';
$genre  = $_GET['genre'] ?? '';
$params = [];

// 构建查询 SQL (基于视图 vw_customer_catalog)
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

    // 获取所有流派用于筛选下拉框
    $genres = $pdo->query("SELECT DISTINCT Genre FROM ReleaseAlbum ORDER BY Genre")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $items = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-compact-disc me-2"></i>Vinyl Catalog</h2>
    
    <form class="d-flex gap-2" method="GET">
        <select name="genre" class="form-select bg-dark text-light border-secondary" style="width: 150px;">
            <option value="">All Genres</option>
            <?php foreach($genres as $g): ?>
                <option value="<?= h($g) ?>" <?= $genre === $g ? 'selected' : '' ?>><?= h($g) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="form-control bg-dark text-light border-secondary" type="search" name="q" placeholder="Artist or Title..." value="<?= h($search) ?>">
        <button class="btn btn-outline-warning" type="submit">Search</button>
        <?php if($search || $genre): ?>
            <a href="catalog.php" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
    <?php if (empty($items)): ?>
        <div class="col-12 text-center text-muted py-5">
            <h4>No records found matching your criteria.</h4>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="col">
                <div class="card h-100 bg-secondary text-light border-0 shadow-sm hover-effect">
                    <div class="ratio ratio-1x1 bg-dark d-flex align-items-center justify-content-center text-secondary">
                        <i class="fa-solid fa-music fa-3x"></i>
                    </div>
                    
                    <div class="card-body">
                        <h5 class="card-title text-truncate" title="<?= h($item['Title']) ?>"><?= h($item['Title']) ?></h5>
                        <h6 class="card-subtitle mb-2 text-warning"><?= h($item['ArtistName']) ?></h6>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="badge bg-black border border-secondary"><?= h($item['Genre']) ?></span>
                            <?php 
                                $badgeClass = match($item['ConditionGrade']) {
                                    'New', 'Mint' => 'bg-success',
                                    'NM', 'VG+'   => 'bg-info text-dark',
                                    default       => 'bg-warning text-dark'
                                };
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= h($item['ConditionGrade']) ?></span>
                        </div>
                        
                        <div class="mt-3 d-flex justify-content-between align-items-end">
                            <div class="text-start">
                                <small class="d-block text-light-50">Location: <?= h($item['LocationName']) ?></small>
                                <span class="h4 fw-bold text-white"><?= formatPrice($item['UnitPrice']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent border-top border-secondary">
                        <form action="cart_action.php" method="POST">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="stock_id" value="<?= $item['StockItemID'] ?>">
                            <button type="submit" class="btn btn-warning w-100 fw-bold">
                                <i class="fa-solid fa-cart-plus me-2"></i>Add to Cart
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>