<?php
/**
 * Inventory Management Page with Search and Filter
 * Presentation layer - only responsible for data display and user interaction
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole(['Staff', 'Manager']);

// Verify employee shop association from database
$employeeId = $_SESSION['user_id'] ?? null;
if (!$employeeId) {
    flash('Session expired. Please re-login.', 'warning');
    header('Location: /login.php');
    exit;
}

// Get and verify employee info
$employee = DBProcedures::getEmployeeShopInfo($pdo, $employeeId);
if (!$employee) {
    flash('Employee information not found. Please contact administrator.', 'danger');
    header('Location: /login.php');
    exit;
}

// Use database-verified shop ID
$shopId = $employee['ShopID'];
$_SESSION['shop_id'] = $shopId;

// ========== Get Filter Parameters ==========
$viewMode = $_GET['view'] ?? 'summary';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$filters = [
    'search' => trim($_GET['q'] ?? ''),
    'batch'  => $_GET['batch'] ?? '',
    'sort'   => $_GET['sort'] ?? ''
];

// ========== Data Preparation ==========
$pageData = prepareInventoryPageDataPaginated($pdo, $shopId, $viewMode, $page, $perPage, $filters);
$inventory = $pageData['inventory'];
$totalItems = $pageData['total_items'];
$pagination = $pageData['pagination'];
$batches = $pageData['batches'] ?? [];

// Build query string for pagination links
$queryParams = array_filter([
    'view' => $viewMode,
    'q' => $filters['search'],
    'batch' => $filters['batch'],
    'sort' => $filters['sort']
]);

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== Presentation Layer ========== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-warning"><i class="fa-solid fa-boxes-stacked me-2"></i>Local Inventory</h2>
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

<!-- Search and Filter Bar -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="view" value="<?= h($viewMode) ?>">

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
                    <a href="?view=<?= $viewMode ?>" class="btn btn-outline-secondary" title="Clear filters">
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
                    <td>
                        <div class="fw-bold"><?= h($item['Title']) ?></div>
                        <div class="small text-muted"><?= h($item['ArtistName']) ?></div>
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
                    <td colspan="6" class="text-center text-muted py-4">
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
                    <td class="font-monospace text-info"><?= h($item['BatchNo']) ?></td>
                    <td>
                        <div class="fw-bold"><?= h($item['Title']) ?></div>
                        <div class="small text-muted"><?= h($item['ArtistName']) ?></div>
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
                    <td colspan="6" class="text-center text-muted py-4">
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
