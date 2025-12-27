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

// 获取当前Manager的店铺ID
$shopId = $_SESSION['user']['ShopID'] ?? null;
$shopType = $_SESSION['user']['ShopType'] ?? 'Retail';

if (!$shopId) {
    die('Error: Shop ID not found in session.');
}

// 处理AJAX请求 - 获取明细数据
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'genre_detail' && isset($_GET['genre'])) {
        $genre = $_GET['genre'];
        $details = DBProcedures::getSalesByGenreDetail($pdo, $shopId, $genre);
        echo json_encode(['success' => true, 'data' => $details, 'genre' => $genre]);
        exit;
    }

    if ($_GET['ajax'] === 'month_detail' && isset($_GET['month'])) {
        $month = $_GET['month'];
        $details = DBProcedures::getMonthlySalesDetail($pdo, $shopId, $month);
        echo json_encode(['success' => true, 'data' => $details, 'month' => $month]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// 获取报表数据
$pageData = prepareReportsPageData($pdo, $shopId);
$turnoverStats = $pageData['turnover_stats'];
$salesTrend = $pageData['sales_trend'];

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
    // Genre Detail Modal - 使用getOrCreateInstance避免重复实例化
    const genreModalEl = document.getElementById('genreDetailModal');

    // Reset modal state when hidden
    genreModalEl.addEventListener('hidden.bs.modal', function() {
        document.getElementById('genreDetailLoading').classList.remove('d-none');
        document.getElementById('genreDetailContent').classList.add('d-none');
        document.getElementById('genreDetailEmpty').classList.add('d-none');
        document.getElementById('genreDetailBody').innerHTML = '';
        
        // 清理残留的 backdrop
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        
        // 销毁实例，下次点击时重新创建
        const instance = bootstrap.Modal.getInstance(genreModalEl);
        if (instance) {
            instance.dispose();
        }
    });


    document.querySelectorAll('.btn-genre-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            const genre = this.dataset.genre;
            document.getElementById('genreTitle').textContent = genre;
            document.getElementById('genreDetailLoading').classList.remove('d-none');
            document.getElementById('genreDetailContent').classList.add('d-none');
            document.getElementById('genreDetailEmpty').classList.add('d-none');

            // 使用getOrCreateInstance确保不重复创建实例
            const genreModal = bootstrap.Modal.getOrCreateInstance(genreModalEl);
            genreModal.show();

            fetch(`reports.php?ajax=genre_detail&genre=${encodeURIComponent(genre)}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('genreDetailLoading').classList.add('d-none');

                    if (data.success && data.data.length > 0) {
                        let html = '';
                        data.data.forEach(row => {
                            html += `<tr>
                                <td><span class="badge bg-info">#${row.OrderID}</span></td>
                                <td>${row.OrderDate}</td>
                                <td>${row.CustomerName || 'Guest'}</td>
                                <td>${escapeHtml(row.Title)}</td>
                                <td><small class="text-muted">${escapeHtml(row.ArtistName)}</small></td>
                                <td><span class="badge bg-secondary">${row.ConditionGrade}</span></td>
                                <td class="text-end text-success">¥${parseFloat(row.PriceAtSale).toFixed(2)}</td>
                            </tr>`;
                        });
                        document.getElementById('genreDetailBody').innerHTML = html;
                        document.getElementById('genreDetailContent').classList.remove('d-none');
                    } else {
                        document.getElementById('genreDetailEmpty').classList.remove('d-none');
                    }
                })
                .catch(err => {
                    document.getElementById('genreDetailLoading').classList.add('d-none');
                    document.getElementById('genreDetailEmpty').textContent = 'Error loading data.';
                    document.getElementById('genreDetailEmpty').classList.remove('d-none');
                });
        });
    });

    // Month Detail Modal - 使用getOrCreateInstance避免重复实例化
    const monthModalEl = document.getElementById('monthDetailModal');

    // Reset modal state when hidden
    monthModalEl.addEventListener('hidden.bs.modal', function() {
        document.getElementById('monthDetailLoading').classList.remove('d-none');
        document.getElementById('monthDetailContent').classList.add('d-none');
        document.getElementById('monthDetailEmpty').classList.add('d-none');
        document.getElementById('monthDetailBody').innerHTML = '';
        
        // 清理残留的 backdrop
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        
        // 销毁实例
        const instance = bootstrap.Modal.getInstance(monthModalEl);
        if (instance) {
            instance.dispose();
        }
    });


    document.querySelectorAll('.btn-month-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            const month = this.dataset.month;
            document.getElementById('monthTitle').textContent = month;
            document.getElementById('monthDetailLoading').classList.remove('d-none');
            document.getElementById('monthDetailContent').classList.add('d-none');
            document.getElementById('monthDetailEmpty').classList.add('d-none');

            // 使用getOrCreateInstance确保不重复创建实例
            const monthModal = bootstrap.Modal.getOrCreateInstance(monthModalEl);
            monthModal.show();

            fetch(`reports.php?ajax=month_detail&month=${encodeURIComponent(month)}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('monthDetailLoading').classList.add('d-none');

                    if (data.success && data.data.length > 0) {
                        let html = '';
                        const typeBadges = {
                            'POS': '<span class="badge bg-warning text-dark">POS</span>',
                            'OnlinePickup': '<span class="badge bg-info">Pickup</span>',
                            'OnlineSales': '<span class="badge bg-success">Shipping</span>'
                        };
                        data.data.forEach(row => {
                            const typeBadge = typeBadges[row.OrderCategory] || '<span class="badge bg-secondary">Other</span>';
                            html += `<tr>
                                <td><span class="badge bg-info">#${row.OrderID}</span></td>
                                <td>${row.OrderDate}</td>
                                <td>${typeBadge}</td>
                                <td>${row.CustomerName || 'Guest'}</td>
                                <td>${escapeHtml(row.Title)}</td>
                                <td><span class="badge bg-secondary">${row.ConditionGrade}</span></td>
                                <td class="text-end text-success">¥${parseFloat(row.PriceAtSale).toFixed(2)}</td>
                            </tr>`;
                        });
                        document.getElementById('monthDetailBody').innerHTML = html;
                        document.getElementById('monthDetailContent').classList.remove('d-none');
                    } else {
                        document.getElementById('monthDetailEmpty').classList.remove('d-none');
                    }
                })
                .catch(err => {
                    document.getElementById('monthDetailLoading').classList.add('d-none');
                    document.getElementById('monthDetailEmpty').textContent = 'Error loading data.';
                    document.getElementById('monthDetailEmpty').classList.remove('d-none');
                });
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
