<?php
/**
 * 【架构重构】产品管理页面 - Admin版
 * 支持编辑专辑信息和按成色修改售价
 */
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_guard.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_procedures.php';
requireRole('Admin');

// 处理AJAX请求 - 获取库存价格数据
if (isset($_GET['ajax']) && $_GET['ajax'] === 'stock_prices' && isset($_GET['release_id'])) {
    header('Content-Type: application/json');
    $releaseId = (int)$_GET['release_id'];
    $prices = DBProcedures::getStockPriceByCondition($pdo, $releaseId);
    echo json_encode(['success' => true, 'data' => $prices]);
    exit;
}

// ========== POST 请求处理 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理价格更新
    if (isset($_POST['update_prices'])) {
        $releaseId = (int)$_POST['release_id'];
        $prices = $_POST['prices'] ?? [];
        $updatedCount = 0;

        // 获取该release所有店铺的库存信息
        $stockData = DBProcedures::getStockPriceByCondition($pdo, $releaseId);

        // 按condition分组找出所有需要更新的shopId
        $shopsByCondition = [];
        foreach ($stockData as $row) {
            $cond = $row['ConditionGrade'];
            if (!isset($shopsByCondition[$cond])) {
                $shopsByCondition[$cond] = [];
            }
            $shopsByCondition[$cond][$row['ShopID']] = true;
        }

        foreach ($prices as $condition => $newPrice) {
            if ($newPrice !== '' && is_numeric($newPrice)) {
                // 更新所有有该condition库存的店铺
                $shopsToUpdate = $shopsByCondition[$condition] ?? [];
                foreach (array_keys($shopsToUpdate) as $shopId) {
                    $result = DBProcedures::updateStockPrice($pdo, $shopId, $releaseId, $condition, (float)$newPrice);
                    if ($result) $updatedCount++;
                }
            }
        }

        if ($updatedCount > 0) {
            flash("Updated prices for $updatedCount shop/condition combination(s) successfully.", 'success');
        } else {
            flash("No prices were updated.", 'warning');
        }
        header("Location: products.php");
        exit();
    }

    // 处理专辑信息更新
    $action = isset($_POST['add_release']) ? 'add' :
             (isset($_POST['edit_release']) ? 'edit' : '');

    if ($action) {
        $data = [
            'release_id' => $_POST['release_id'] ?? null,
            'title'      => trim($_POST['title'] ?? ''),
            'artist'     => trim($_POST['artist'] ?? ''),
            'label'      => trim($_POST['label'] ?? ''),
            'year'       => (int)($_POST['year'] ?? 0),
            'genre'      => trim($_POST['genre'] ?? ''),
            'desc'       => trim($_POST['desc'] ?? '')
        ];

        $result = handleReleaseAction($pdo, $action, $data);
        flash($result['message'], $result['success'] ? 'success' : 'danger');

        header("Location: products.php");
        exit();
    }
}

// ========== 数据准备 ==========
$pageData = prepareProductsPageData($pdo);
$releases = $pageData['releases'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ========== 表现层 ========== -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="text-warning mb-1"><i class="fa-solid fa-compact-disc me-2"></i>Global Product Catalog</h2>
        <p class="text-secondary mb-0">Manage releases and adjust prices by condition across all shops</p>
    </div>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fa-solid fa-plus me-2"></i>Add New Release
    </button>
</div>

<div class="card bg-dark border-secondary">
    <div class="table-responsive">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Label / Year</th>
                    <th>Genre</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($releases as $r): ?>
                <tr>
                    <td><?= $r['ReleaseID'] ?></td>
                    <td class="fw-bold"><?= h($r['Title']) ?></td>
                    <td><?= h($r['ArtistName']) ?></td>
                    <td><?= h($r['LabelName']) ?> <small class="text-muted">(<?= $r['ReleaseYear'] ?>)</small></td>
                    <td><span class="badge bg-secondary"><?= h($r['Genre']) ?></span></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-info edit-btn me-1"
                                data-bs-toggle="modal"
                                data-bs-target="#editModal"
                                data-id="<?= $r['ReleaseID'] ?>"
                                data-title="<?= h($r['Title']) ?>"
                                data-artist="<?= h($r['ArtistName']) ?>"
                                data-label="<?= h($r['LabelName']) ?>"
                                data-year="<?= h($r['ReleaseYear']) ?>"
                                data-genre="<?= h($r['Genre']) ?>"
                                data-desc="<?= h($r['Description']) ?>"
                                title="Edit Release">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning price-btn"
                                data-release-id="<?= $r['ReleaseID'] ?>"
                                data-release-title="<?= h($r['Title']) ?>"
                                title="Adjust Prices">
                            <i class="fa-solid fa-tag"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Add New Release</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_release" value="1">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label>Artist</label>
                            <input type="text" name="artist" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Label</label>
                            <input type="text" name="label" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Year</label>
                            <input type="number" name="year" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Genre</label>
                            <input type="text" name="genre" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="desc" class="form-control bg-dark text-white border-secondary" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-warning fw-bold">Save Release</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-light">
            <div class="modal-header border-dark">
                <h5 class="modal-title">Edit Release</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_release" value="1">
                    <input type="hidden" name="release_id" id="edit_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-6">
                            <label>Artist</label>
                            <input type="text" name="artist" id="edit_artist" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Label</label>
                            <input type="text" name="label" id="edit_label" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Year</label>
                            <input type="number" name="year" id="edit_year" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="col-md-4">
                            <label>Genre</label>
                            <input type="text" name="genre" id="edit_genre" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="desc" id="edit_desc" class="form-control bg-dark text-white border-secondary" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="submit" class="btn btn-info text-dark fw-bold">Update Release</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Price Adjustment Modal -->
<div class="modal fade" id="priceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">
                    <i class="fa-solid fa-tag text-warning me-2"></i>
                    Price Adjustment: <span id="priceModalTitle"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_prices" value="1">
                    <input type="hidden" name="release_id" id="price_release_id">

                    <div id="priceLoading" class="text-center py-4">
                        <div class="spinner-border text-warning"></div>
                        <p class="mt-2">Loading stock prices...</p>
                    </div>

                    <div id="priceContent" class="d-none">
                        <div class="alert alert-info mb-3">
                            <i class="fa-solid fa-info-circle me-1"></i>
                            Adjust prices by condition. Changes apply to all available stock across all shops.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped">
                                <thead>
                                    <tr>
                                        <th>Condition</th>
                                        <th>Shop</th>
                                        <th class="text-center">Available Qty</th>
                                        <th>Current Price</th>
                                        <th>New Price</th>
                                    </tr>
                                </thead>
                                <tbody id="priceTableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="priceEmpty" class="d-none alert alert-warning">
                        <i class="fa-solid fa-exclamation-triangle me-1"></i>
                        No available stock found for this release.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="priceSubmitBtn" class="btn btn-warning fw-bold" disabled>
                        <i class="fa-solid fa-save me-1"></i>Update Prices
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit modal
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_title').value = this.dataset.title;
            document.getElementById('edit_artist').value = this.dataset.artist;
            document.getElementById('edit_label').value = this.dataset.label;
            document.getElementById('edit_year').value = this.dataset.year;
            document.getElementById('edit_genre').value = this.dataset.genre;
            document.getElementById('edit_desc').value = this.dataset.desc;
        });
    });

    // Price modal
    const priceModal = new bootstrap.Modal(document.getElementById('priceModal'));

    document.querySelectorAll('.price-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const releaseId = this.dataset.releaseId;
            const releaseTitle = this.dataset.releaseTitle;

            document.getElementById('priceModalTitle').textContent = releaseTitle;
            document.getElementById('price_release_id').value = releaseId;
            document.getElementById('priceLoading').classList.remove('d-none');
            document.getElementById('priceContent').classList.add('d-none');
            document.getElementById('priceEmpty').classList.add('d-none');
            document.getElementById('priceSubmitBtn').disabled = true;

            priceModal.show();

            fetch(`products.php?ajax=stock_prices&release_id=${releaseId}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('priceLoading').classList.add('d-none');

                    if (data.success && data.data.length > 0) {
                        let html = '';
                        const conditionOrder = ['New', 'Mint', 'NM', 'VG+', 'VG', 'G+', 'G', 'F', 'P'];

                        // Group by condition for form input
                        const byCondition = {};
                        data.data.forEach(row => {
                            const cond = row.ConditionGrade;
                            if (!byCondition[cond]) {
                                byCondition[cond] = { price: row.MinPrice, rows: [] };
                            }
                            byCondition[cond].rows.push(row);
                        });

                        // Render rows
                        data.data.forEach((row, idx) => {
                            const cond = row.ConditionGrade;
                            const isFirst = byCondition[cond].rows[0] === row;
                            const rowCount = byCondition[cond].rows.length;

                            html += `<tr>`;
                            if (isFirst) {
                                html += `<td rowspan="${rowCount}" class="align-middle">
                                    <span class="badge bg-secondary fs-6">${cond}</span>
                                </td>`;
                            }
                            html += `
                                <td>${escapeHtml(row.ShopName)}</td>
                                <td class="text-center"><span class="badge bg-info">${row.Quantity}</span></td>
                                <td class="text-success">¥${parseFloat(row.MinPrice).toFixed(2)}</td>
                            `;
                            if (isFirst) {
                                html += `<td rowspan="${rowCount}" class="align-middle">
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-light">¥</span>
                                        <input type="number" step="0.01" min="0" name="prices[${cond}]"
                                               class="form-control bg-dark text-white border-secondary"
                                               placeholder="${parseFloat(row.MinPrice).toFixed(2)}">
                                    </div>
                                </td>`;
                            }
                            html += `</tr>`;
                        });

                        document.getElementById('priceTableBody').innerHTML = html;
                        document.getElementById('priceContent').classList.remove('d-none');
                        document.getElementById('priceSubmitBtn').disabled = false;
                    } else {
                        document.getElementById('priceEmpty').classList.remove('d-none');
                    }
                })
                .catch(err => {
                    document.getElementById('priceLoading').classList.add('d-none');
                    document.getElementById('priceEmpty').textContent = 'Error loading stock data.';
                    document.getElementById('priceEmpty').classList.remove('d-none');
                });
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
