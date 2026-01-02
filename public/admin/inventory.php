<?php
/**
 * Admin Inventory View with Search and Filter
 * Shows inventory from all shops with pagination
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

// Get list of shops for filter
$shops = DBProcedures::getShopList($pdo);
$selectedShopId = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;

// View mode and pagination
$viewMode = $_GET['view'] ?? 'summary';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Get filter parameters
$filters = [
    'search' => trim($_GET['q'] ?? ''),
    'batch'  => $_GET['batch'] ?? '',
    'sort'   => $_GET['sort'] ?? ''
];

// Get paginated inventory data
if ($selectedShopId > 0) {
    $pageData = prepareInventoryPageDataPaginated($pdo, $selectedShopId, $viewMode, $page, $perPage, $filters);
} else {
    // Get all inventory
    $pageData = prepareInventoryPageDataAllShopsPaginated($pdo, $viewMode, $page, $perPage, $filters);
}
$inventory = $pageData['inventory'];
$totalItems = $pageData['total_items'];
$pagination = $pageData['pagination'];
$batches = $pageData['batches'] ?? [];

// Build query string for pagination links
$queryParams = array_filter([
    'view' => $viewMode,
    'shop_id' => $selectedShopId ?: null,
    'q' => $filters['search'],
    'batch' => $filters['batch'],
    'sort' => $filters['sort']
]);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-warning"><i class="fa-solid fa-warehouse me-2"></i>Inventory Overview</h2>
        <p class="text-muted mb-0">View inventory across all locations</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-secondary fs-6"><?= $totalItems ?> Items</span>
        <div class="btn-group" role="group">
            <a href="?<?= http_build_query(array_merge($queryParams, ['view' => 'detail', 'page' => 1])) ?>"
               class="btn btn-sm <?= $viewMode === 'detail' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="fa-solid fa-list me-1"></i>Detail
            </a>
            <a href="?<?= http_build_query(array_merge($queryParams, ['view' => 'summary', 'page' => 1])) ?>"
               class="btn btn-sm <?= $viewMode === 'summary' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="fa-solid fa-chart-bar me-1"></i>Summary
            </a>
        </div>
    </div>
</div>

<!-- Shop Filter -->
<div class="card bg-dark border-secondary mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted me-2"><i class="fa-solid fa-store me-1"></i>Shop:</span>
            <a href="?<?= http_build_query(array_merge($queryParams, ['shop_id' => null, 'page' => 1])) ?>"
               class="btn btn-sm <?= $selectedShopId == 0 ? 'btn-warning' : 'btn-outline-warning' ?>">
                All Shops
            </a>
            <?php foreach ($shops as $shop): ?>
                <a href="?<?= http_build_query(array_merge($queryParams, ['shop_id' => $shop['ShopID'], 'page' => 1])) ?>"
                   class="btn btn-sm <?= $selectedShopId == $shop['ShopID'] ? 'btn-info' : 'btn-outline-info' ?>">
                    <i class="fa-solid <?= $shop['Type'] == 'Warehouse' ? 'fa-warehouse' : 'fa-store' ?> me-1"></i>
                    <?= h($shop['Name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="view" value="<?= h($viewMode) ?>">
            <?php if ($selectedShopId): ?>
            <input type="hidden" name="shop_id" value="<?= $selectedShopId ?>">
            <?php endif; ?>

            <!-- Search -->
            <div class="<?= $viewMode === 'detail' ? 'col-md-4' : 'col-md-5' ?>">
                <label class="form-label small text-muted">Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-secondary border-secondary"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control bg-dark text-white border-secondary"
                           placeholder="Title or Artist..." value="<?= h($filters['search']) ?>">
                </div>
            </div>

            <!-- Batch Filter (only shown in detail mode) -->
            <?php if ($viewMode === 'detail'): ?>
            <div class="col-md-3">
                <label class="form-label small text-muted">Batch</label>
                <select name="batch" class="form-select bg-dark text-white border-secondary">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= h($b) ?>" <?= $filters['batch'] === $b ? 'selected' : '' ?>><?= h($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Sort -->
            <div class="<?= $viewMode === 'detail' ? 'col-md-3' : 'col-md-5' ?>">
                <label class="form-label small text-muted">Sort By</label>
                <select name="sort" class="form-select bg-dark text-white border-secondary">
                    <option value="">Default (Title)</option>
                    <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    <?php if ($viewMode === 'detail'): ?>
                    <option value="days_asc" <?= $filters['sort'] === 'days_asc' ? 'selected' : '' ?>>Days in Stock: Low to High</option>
                    <option value="days_desc" <?= $filters['sort'] === 'days_desc' ? 'selected' : '' ?>>Days in Stock: High to Low</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Buttons -->
            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <a href="?view=<?= $viewMode ?><?= $selectedShopId ? '&shop_id='.$selectedShopId : '' ?>" class="btn btn-outline-secondary" title="Clear filters">
                        <i class="fa-solid fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($viewMode === 'summary'): ?>
<div class="card bg-dark border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <?php if ($selectedShopId == 0): ?><th>Shop</th><?php endif; ?>
                    <th>Album</th>
                    <th>Genre</th>
                    <th>Condition</th>
                    <th class="text-center">Quantity</th>
                    <th>Price Range</th>
                    <th>Avg Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): ?>
                <tr>
                    <?php if ($selectedShopId == 0): ?>
                    <td><span class="badge bg-secondary"><?= h($item['ShopName'] ?? 'N/A') ?></span></td>
                    <?php endif; ?>
                    <td>
                        <div class="fw-bold"><?= h($item['Title']) ?></div>
                        <div class="small text-warning"><?= h($item['ArtistName']) ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?= h($item['Genre']) ?></span></td>
                    <td><?= h($item['ConditionGrade']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $item['AvailableQuantity'] < 3 ? 'bg-danger' : 'bg-success' ?> fs-6">
                            <?= $item['AvailableQuantity'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($item['MinPrice'] == $item['MaxPrice']): ?>
                            <span class="text-warning"><?= formatPrice($item['MinPrice']) ?></span>
                        <?php else: ?>
                            <span class="text-warning"><?= formatPrice($item['MinPrice']) ?></span>
                            <span class="text-muted">~</span>
                            <span class="text-warning"><?= formatPrice($item['MaxPrice']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-info"><?= formatPrice($item['AvgPrice']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($inventory)): ?>
                <tr>
                    <td colspan="<?= $selectedShopId == 0 ? 7 : 6 ?>" class="text-center text-muted py-4">
                        <?php if (!empty($filters['search']) || !empty($filters['batch'])): ?>
                            No items match your search criteria.
                        <?php else: ?>
                            No inventory items found.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="card bg-dark border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <?php if ($selectedShopId == 0): ?><th>Shop</th><?php endif; ?>
                    <th>Batch No</th>
                    <th>Album</th>
                    <th>Condition</th>
                    <th>Price</th>
                    <th>Days in Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): ?>
                <tr>
                    <?php if ($selectedShopId == 0): ?>
                    <td><span class="badge bg-secondary"><?= h($item['ShopName'] ?? 'N/A') ?></span></td>
                    <?php endif; ?>
                    <td class="font-monospace text-info"><?= h($item['BatchNo']) ?></td>
                    <td>
                        <div class="fw-bold"><?= h($item['Title']) ?></div>
                        <div class="small text-warning"><?= h($item['ArtistName']) ?></div>
                    </td>
                    <td><?= h($item['ConditionGrade']) ?></td>
                    <td class="text-warning"><?= formatPrice($item['UnitPrice']) ?></td>
                    <td>
                        <?php
                        $days = $item['DaysInStock'] ?? floor((time() - strtotime($item['AcquiredDate'])) / (60 * 60 * 24));
                        echo $days;
                        if ($days > 60) echo ' <span class="badge bg-danger ms-1">Slow</span>';
                        ?>
                    </td>
                    <td><span class="badge bg-success">Available</span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($inventory)): ?>
                <tr>
                    <td colspan="<?= $selectedShopId == 0 ? 7 : 6 ?>" class="text-center text-muted py-4">
                        <?php if (!empty($filters['search']) || !empty($filters['batch'])): ?>
                            No items match your search criteria.
                        <?php else: ?>
                            No inventory items found.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
<nav aria-label="Inventory pagination" class="mt-4">
    <ul class="pagination justify-content-center">
        <!-- Previous button -->
        <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
            <a class="page-link bg-dark border-secondary text-light"
               href="?<?= http_build_query(array_merge($queryParams, ['page' => $pagination['prev_page']])) ?>">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
        </li>

        <!-- Page numbers -->
        <?php foreach ($pagination['pages'] as $p): ?>
            <?php if ($p === '...'): ?>
                <li class="page-item disabled">
                    <span class="page-link bg-dark border-secondary text-muted">...</span>
                </li>
            <?php else: ?>
                <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                    <a class="page-link <?= $p === $pagination['current_page'] ? 'bg-warning text-dark border-warning' : 'bg-dark border-secondary text-light' ?>"
                       href="?<?= http_build_query(array_merge($queryParams, ['page' => $p])) ?>">
                        <?= $p ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Next button -->
        <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
            <a class="page-link bg-dark border-secondary text-light"
               href="?<?= http_build_query(array_merge($queryParams, ['page' => $pagination['next_page']])) ?>">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        </li>
    </ul>
    <div class="text-center text-muted small">
        Showing <?= $pagination['offset'] + 1 ?>-<?= min($pagination['offset'] + $pagination['per_page'], $pagination['total_items']) ?>
        of <?= $pagination['total_items'] ?> items
    </div>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
