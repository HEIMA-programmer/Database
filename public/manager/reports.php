<?php
/**
 * 【架构重构】报表页面 - Manager版
 * 显示本店的销售统计，支持点击查看明细
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Manager');

// 【Session安全修复】检查 $_SESSION['user'] 存在性，兼容多种session结构
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

// 获取报表数据
$pageData = prepareReportsPageData($pdo, $shopId);
$turnoverStats = $pageData['turnover_stats'];
$salesTrend = $pageData['sales_trend'];

// 【修复】预加载销售详情数据 - 解决AJAX loading一直显示的问题
// 将数据预先加载到页面，避免异步加载的兼容性问题
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

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="row mb-4">
    <div class="col-12">
        <h2 class="text-info display-6 fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Performance Reports</h2>
        <p class="text-secondary">Sales analytics for <span class="text-info fw-bold"><?= h($_SESSION['shop_name'] ?? 'Your Store') ?></span></p>
    </div>
</div>

<div class="card bg-dark border-secondary mb-5">
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
                            <!-- 【修复】使用onclick直接调用渲染函数，与pos.php的Detail按钮处理方式完全一致 -->
                            <button type="button" class="btn btn-sm btn-outline-info"
                                    data-bs-toggle="modal" data-bs-target="#genreDetailModal"
                                    onclick="renderGenreDetail(<?= htmlspecialchars(json_encode($stat['Genre']), ENT_QUOTES) ?>)" title="View Orders">
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

<div class="card bg-dark border-secondary">
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
                        <!-- 【修复】使用onclick直接调用渲染函数，与pos.php的Detail按钮处理方式完全一致 -->
                        <button type="button" class="btn btn-sm btn-outline-info"
                                data-bs-toggle="modal" data-bs-target="#monthDetailModal"
                                onclick="renderMonthDetail(<?= htmlspecialchars(json_encode($trend['SalesMonth']), ENT_QUOTES) ?>)" title="View Orders">
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
                <!-- 【修复】添加d-none初始状态，由JS控制显示 -->
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
                <!-- 【修复】添加d-none初始状态，由JS控制显示 -->
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

<!-- 预加载数据 -->
<?php
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$genreJson = json_encode($genreDetails ?: [], $jsonFlags);
$monthJson = json_encode($monthDetails ?: [], $jsonFlags);
if ($genreJson === false) $genreJson = '{}';
if ($monthJson === false) $monthJson = '{}';
?>
<script>
window.preloadedGenreDetails = <?= $genreJson ?>;
window.preloadedMonthDetails = <?= $monthJson ?>;
</script>
<script src="../assets/js/pages/manager-reports.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
