<?php
/**
 * Performance Reports Page - Manager Version
 * Shows sales statistics, artist profit analysis, and batch sales analysis
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Manager');

// Get shop info from session
$shopId = (isset($_SESSION['user']) && isset($_SESSION['user']['ShopID']))
    ? $_SESSION['user']['ShopID']
    : ($_SESSION['shop_id'] ?? null);
$shopType = (isset($_SESSION['user']) && isset($_SESSION['user']['ShopType']))
    ? $_SESSION['user']['ShopType']
    : 'Retail';

if (!$shopId) {
    flash('Shop ID not found in session. Please re-login.', 'warning');
    header('Location: dashboard.php');
    exit;
}

// Get report data
$pageData = prepareReportsPageData($pdo, $shopId);
$turnoverStats = $pageData['turnover_stats'];
$salesTrend = $pageData['sales_trend'];
$artistProfit = $pageData['artist_profit'] ?? [];
$batchSales = $pageData['batch_sales'] ?? [];

// Preload detail data
$genreDetails = [];
foreach ($turnoverStats as $stat) {
    $genre = $stat['Genre'];
    $genreDetails[$genre] = DBProcedures::getSalesByGenreDetail($pdo, $shopId, $genre);
}

$monthDetails = [];
foreach ($salesTrend as $trend) {
    $month = $trend['SalesMonth'];
    $monthDetails[$month] = DBProcedures::getMonthlySalesDetail($pdo, $shopId, $month);
}

$artistDetails = [];
foreach ($artistProfit as $artist) {
    $name = $artist['ArtistName'];
    $artistDetails[$name] = DBProcedures::getArtistSalesDetail($pdo, $shopId, $name);
}

$batchDetails = [];
foreach ($batchSales as $batch) {
    $batchNo = $batch['BatchNo'];
    $batchDetails[$batchNo] = DBProcedures::getBatchSalesDetail($pdo, $shopId, $batchNo);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <h2 class="text-info display-6 fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Performance Reports</h2>
        <p class="text-secondary">Sales analytics for <span class="text-info fw-bold"><?= h($_SESSION['shop_name'] ?? 'Your Store') ?></span></p>
    </div>
</div>

<!-- Genre Turnover Section -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-guitar me-2"></i>Inventory Turnover by Genre</h5>
        <small class="report-section-subtitle">How fast are we selling different types of music?</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Genre</th>
                        <th>Items Sold</th>
                        <th>Avg. Days to Sell</th>
                        <th>Turnover Speed</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($turnoverStats)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No sales data available yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($turnoverStats as $stat): ?>
                    <?php
                        $days = round($stat['AvgDaysToSell'], 1);
                        $speedClass = $days < 30 ? 'text-success' : ($days < 90 ? 'text-warning' : 'text-danger');
                        $speedLabel = $days < 30 ? 'Fast' : ($days < 90 ? 'Moderate' : 'Slow');
                    ?>
                    <tr>
                        <td class="fw-bold"><?= h($stat['Genre']) ?></td>
                        <td><?= $stat['ItemsSold'] ?></td>
                        <td><?= $days ?> days</td>
                        <td class="<?= $speedClass ?> fw-bold"><?= $speedLabel ?></td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($stat['TotalRevenue']) ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-info"
                                    data-bs-toggle="modal" data-bs-target="#genreDetailModal"
                                    data-genre="<?= h($stat['Genre']) ?>" title="View Orders">
                                <i class="fa-solid fa-list"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Artist Profit Analysis Section -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-microphone-lines me-2"></i>Artist Profit Analysis</h5>
        <small class="report-section-subtitle">Which artists are generating the most profit?</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Artist</th>
                        <th>Items Sold</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Gross Profit</th>
                        <th class="text-center">Margin</th>
                        <th class="text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($artistProfit)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No artist profit data available yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($artistProfit as $artist): ?>
                    <?php
                        $margin = $artist['ProfitMargin'] ?? 0;
                        $marginClass = $margin >= 40 ? 'text-success' : ($margin >= 20 ? 'text-warning' : 'text-danger');
                    ?>
                    <tr>
                        <td class="fw-bold"><?= h($artist['ArtistName']) ?></td>
                        <td><?= $artist['ItemsSold'] ?></td>
                        <td class="text-end text-info"><?= formatPrice($artist['TotalRevenue']) ?></td>
                        <td class="text-end text-muted"><?= formatPrice($artist['TotalCost']) ?></td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($artist['GrossProfit']) ?></td>
                        <td class="text-center <?= $marginClass ?> fw-bold"><?= $margin ?>%</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-info"
                                    data-bs-toggle="modal" data-bs-target="#artistDetailModal"
                                    data-artist="<?= h($artist['ArtistName']) ?>" title="View Sales">
                                <i class="fa-solid fa-list"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Batch Sales Analysis Section -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-boxes-stacked me-2"></i>Batch Sales Analysis</h5>
        <small class="report-section-subtitle">Performance breakdown by procurement batch</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle">
                <thead>
                    <tr>
                        <th>Batch No</th>
                        <th>Acquired</th>
                        <th class="text-center">Total Items</th>
                        <th class="text-center">Sold</th>
                        <th class="text-center">Available</th>
                        <th>Sell-Through</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batchSales)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">No batch data available.</td></tr>
                    <?php else: ?>
                    <?php foreach ($batchSales as $batch): ?>
                    <?php
                        $sellThrough = $batch['TotalItems'] > 0
                            ? round(($batch['SoldItems'] / $batch['TotalItems']) * 100, 1)
                            : 0;
                        $stClass = $sellThrough >= 70 ? 'text-success' : ($sellThrough >= 30 ? 'text-warning' : 'text-danger');
                    ?>
                    <tr>
                        <td class="font-monospace text-info fw-bold"><?= h($batch['BatchNo']) ?></td>
                        <td><?= date('Y-m-d', strtotime($batch['AcquiredDate'])) ?></td>
                        <td class="text-center"><?= $batch['TotalItems'] ?></td>
                        <td class="text-center text-success"><?= $batch['SoldItems'] ?></td>
                        <td class="text-center text-warning"><?= $batch['AvailableItems'] ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress bg-secondary flex-grow-1 me-2" style="height: 8px; width: 60px;">
                                    <div class="progress-bar <?= $sellThrough >= 70 ? 'bg-success' : ($sellThrough >= 30 ? 'bg-warning' : 'bg-danger') ?>"
                                         role="progressbar" style="width: <?= $sellThrough ?>%"></div>
                                </div>
                                <span class="<?= $stClass ?> small fw-bold"><?= $sellThrough ?>%</span>
                            </div>
                        </td>
                        <td class="text-end text-success fw-bold"><?= formatPrice($batch['TotalRevenue']) ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-info"
                                    data-bs-toggle="modal" data-bs-target="#batchDetailModal"
                                    data-batch="<?= h($batch['BatchNo']) ?>" title="View Items">
                                <i class="fa-solid fa-list"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Monthly Sales Trend Section -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-chart-line me-2"></i>Monthly Sales Trend</h5>
        <small class="report-section-subtitle">Revenue and order count by month</small>
    </div>
    <div class="card-body">
        <table class="table table-dark table-sm">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Orders</th>
                    <th class="text-end">Revenue</th>
                    <th style="width: 30%;">Visualization</th>
                    <th class="text-center">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($salesTrend)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No sales data available yet.</td></tr>
                <?php else: ?>
                <?php
                $maxRev = 1;
                foreach($salesTrend as $t) $maxRev = max($maxRev, $t['MonthlyRevenue'] ?? 0);

                foreach ($salesTrend as $trend):
                    $percent = ($trend['MonthlyRevenue'] / $maxRev) * 100;
                ?>
                <tr>
                    <td class="fw-bold"><?= $trend['SalesMonth'] ?></td>
                    <td><?= $trend['OrderCount'] ?></td>
                    <td class="text-end text-success fw-bold"><?= formatPrice($trend['MonthlyRevenue']) ?></td>
                    <td>
                        <div class="progress bg-secondary" style="height: 10px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?= $percent ?>%"></div>
                        </div>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-info"
                                data-bs-toggle="modal" data-bs-target="#monthDetailModal"
                                data-month="<?= h($trend['SalesMonth']) ?>" title="View Orders">
                            <i class="fa-solid fa-list"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<!-- Genre Detail Modal -->
<div class="modal fade" id="genreDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-guitar me-2"></i>Genre Sales Detail: <span id="genreTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="genreDetailLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-info"></div>
                    <p class="mt-2">Loading...</p>
                </div>
                <div id="genreDetailContent" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Album</th>
                                    <th>Artist</th>
                                    <th>Condition</th>
                                    <th class="text-end">Price</th>
                                </tr>
                            </thead>
                            <tbody id="genreDetailBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="genreDetailEmpty" class="d-none alert alert-info">No order details found for this genre.</div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Month Detail Modal -->
<div class="modal fade" id="monthDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-calendar me-2"></i>Monthly Sales Detail: <span id="monthTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="monthDetailLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-info"></div>
                    <p class="mt-2">Loading...</p>
                </div>
                <div id="monthDetailContent" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Customer</th>
                                    <th>Album</th>
                                    <th>Condition</th>
                                    <th class="text-end">Price</th>
                                </tr>
                            </thead>
                            <tbody id="monthDetailBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="monthDetailEmpty" class="d-none alert alert-info">No order details found for this month.</div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Artist Detail Modal -->
<div class="modal fade" id="artistDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-microphone-lines me-2"></i>Artist Sales Detail: <span id="artistTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="artistDetailContent">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Album</th>
                                    <th>Condition</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Cost</th>
                                    <th class="text-end">Profit</th>
                                </tr>
                            </thead>
                            <tbody id="artistDetailBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="artistDetailEmpty" class="d-none alert alert-info">No sales details found for this artist.</div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch Detail Modal -->
<div class="modal fade" id="batchDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-boxes-stacked me-2"></i>Batch Detail: <span id="batchTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="batchDetailContent">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Album</th>
                                    <th>Artist</th>
                                    <th>Condition</th>
                                    <th>Status</th>
                                    <th class="text-end">Price</th>
                                    <th>Sold Date</th>
                                    <th>Customer</th>
                                </tr>
                            </thead>
                            <tbody id="batchDetailBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="batchDetailEmpty" class="d-none alert alert-info">No items found in this batch.</div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Preload data -->
<?php
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$genreJson = json_encode($genreDetails ?: [], $jsonFlags);
$monthJson = json_encode($monthDetails ?: [], $jsonFlags);
$artistJson = json_encode($artistDetails ?: [], $jsonFlags);
$batchJson = json_encode($batchDetails ?: [], $jsonFlags);
if ($genreJson === false) $genreJson = '{}';
if ($monthJson === false) $monthJson = '{}';
if ($artistJson === false) $artistJson = '{}';
if ($batchJson === false) $batchJson = '{}';
?>
<script>
window.preloadedGenreDetails = <?= $genreJson ?>;
window.preloadedMonthDetails = <?= $monthJson ?>;
window.preloadedArtistDetails = <?= $artistJson ?>;
window.preloadedBatchDetails = <?= $batchJson ?>;
</script>
<script src="../assets/js/pages/manager-reports.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
