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

// 【修复】兼容多种session结构
$shopId = $_SESSION['user']['ShopID'] ?? $_SESSION['shop_id'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';

if (!$shopId) {
    flash('Shop ID not found in session. Please re-login.', 'warning');
    header('Location: dashboard.php');
    exit;
}

// 获取报表数据
$pageData = prepareReportsPageData($pdo, $shopId);
$turnoverStats = $pageData['turnover_stats'];
$salesTrend = $pageData['sales_trend'];

// 预加载所有 genre 和 month 的详情数据
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
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-warning">Dashboard</a></li>
                <li class="breadcrumb-item active text-light">Performance Reports</li>
            </ol>
        </nav>
        <h2 class="text-info display-6 fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Performance Reports</h2>
        <p class="text-secondary">Sales analytics for <span class="text-info fw-bold"><?= h($_SESSION['shop_name'] ?? 'Your Store') ?></span></p>
    </div>
</div>

<div class="card bg-dark border-secondary mb-5">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-guitar me-2"></i>Inventory Turnover by Genre</h5>
        <small class="text-muted">How fast are we selling different types of music?</small>
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
                            <button type="button" class="btn btn-sm btn-outline-info btn-genre-detail"
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

<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary">
        <h5 class="card-title text-white mb-0"><i class="fa-solid fa-chart-line me-2"></i>Monthly Sales Trend</h5>
        <small class="text-muted">Revenue and order count by month</small>
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
                        <button type="button" class="btn btn-sm btn-outline-info btn-month-detail"
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
                <div id="genreDetailLoading" class="text-center py-4">
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
                <div id="monthDetailLoading" class="text-center py-4">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========== 预加载数据 ==========
    const genreDetailsData = <?= json_encode($genreDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const monthDetailsData = <?= json_encode($monthDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    // ========== 辅助函数 ==========
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // ========== Genre Detail Modal（预加载方式）==========
    const genreModalEl = document.getElementById('genreDetailModal');
    let currentGenre = null;

    function renderGenreDetail(genre) {
        document.getElementById('genreTitle').textContent = genre;
        // 隐藏loading（预加载不需要）
        document.getElementById('genreDetailLoading').classList.add('d-none');

        const data = genreDetailsData[genre] || [];
        if (data.length > 0) {
            const html = data.map(row => `<tr>
                <td><span class="badge bg-info">#${row.OrderID}</span></td>
                <td>${row.OrderDate}</td>
                <td>${row.CustomerName || 'Guest'}</td>
                <td>${escapeHtml(row.Title)}</td>
                <td><small class="text-muted">${escapeHtml(row.ArtistName)}</small></td>
                <td><span class="badge bg-secondary">${row.ConditionGrade}</span></td>
                <td class="text-end text-success">¥${parseFloat(row.PriceAtSale).toFixed(2)}</td>
            </tr>`).join('');
            document.getElementById('genreDetailBody').innerHTML = html;
            document.getElementById('genreDetailContent').classList.remove('d-none');
            document.getElementById('genreDetailEmpty').classList.add('d-none');
        } else {
            document.getElementById('genreDetailContent').classList.add('d-none');
            document.getElementById('genreDetailEmpty').textContent = 'No order details found for this genre.';
            document.getElementById('genreDetailEmpty').classList.remove('d-none');
        }
    }

    // 使用 show.bs.modal 事件在模态框显示前渲染数据
    genreModalEl.addEventListener('show.bs.modal', function() {
        if (currentGenre) {
            renderGenreDetail(currentGenre);
        }
    });

    document.querySelectorAll('.btn-genre-detail').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentGenre = this.dataset.genre;
            const modal = bootstrap.Modal.getOrCreateInstance(genreModalEl);
            modal.show();
        });
    });

    genreModalEl.addEventListener('hidden.bs.modal', function() {
        document.getElementById('genreDetailContent').classList.add('d-none');
        document.getElementById('genreDetailEmpty').classList.add('d-none');
        document.getElementById('genreDetailBody').innerHTML = '';
        currentGenre = null;
    });

    // ========== Month Detail Modal（预加载方式）==========
    const monthModalEl = document.getElementById('monthDetailModal');
    let currentMonth = null;

    const typeBadges = {
        'POS': '<span class="badge bg-warning text-dark">POS</span>',
        'OnlinePickup': '<span class="badge bg-info">Pickup</span>',
        'OnlineSales': '<span class="badge bg-success">Shipping</span>'
    };

    function renderMonthDetail(month) {
        document.getElementById('monthTitle').textContent = month;
        // 隐藏loading（预加载不需要）
        document.getElementById('monthDetailLoading').classList.add('d-none');

        const data = monthDetailsData[month] || [];
        if (data.length > 0) {
            const html = data.map(row => {
                const typeBadge = typeBadges[row.OrderCategory] || '<span class="badge bg-secondary">Other</span>';
                return `<tr>
                    <td><span class="badge bg-info">#${row.OrderID}</span></td>
                    <td>${row.OrderDate}</td>
                    <td>${typeBadge}</td>
                    <td>${row.CustomerName || 'Guest'}</td>
                    <td>${escapeHtml(row.Title)}</td>
                    <td><span class="badge bg-secondary">${row.ConditionGrade}</span></td>
                    <td class="text-end text-success">¥${parseFloat(row.PriceAtSale).toFixed(2)}</td>
                </tr>`;
            }).join('');
            document.getElementById('monthDetailBody').innerHTML = html;
            document.getElementById('monthDetailContent').classList.remove('d-none');
            document.getElementById('monthDetailEmpty').classList.add('d-none');
        } else {
            document.getElementById('monthDetailContent').classList.add('d-none');
            document.getElementById('monthDetailEmpty').textContent = 'No order details found for this month.';
            document.getElementById('monthDetailEmpty').classList.remove('d-none');
        }
    }

    // 使用 show.bs.modal 事件在模态框显示前渲染数据
    monthModalEl.addEventListener('show.bs.modal', function() {
        if (currentMonth) {
            renderMonthDetail(currentMonth);
        }
    });

    document.querySelectorAll('.btn-month-detail').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentMonth = this.dataset.month;
            const modal = bootstrap.Modal.getOrCreateInstance(monthModalEl);
            modal.show();
        });
    });

    monthModalEl.addEventListener('hidden.bs.modal', function() {
        document.getElementById('monthDetailContent').classList.add('d-none');
        document.getElementById('monthDetailEmpty').classList.add('d-none');
        document.getElementById('monthDetailBody').innerHTML = '';
        currentMonth = null;
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
