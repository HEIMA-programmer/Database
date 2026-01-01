/**
 * Admin Products Page JavaScript
 * 处理编辑模态框和价格调整功能
 */
document.addEventListener('DOMContentLoaded', function() {
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Edit modal - 填充编辑表单
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

    // ========== Price Modal（AJAX按需加载）==========
    const priceModalEl = document.getElementById('priceModal');
    let currentReleaseId = null;
    let currentReleaseTitle = null;

    async function loadAndRenderPriceData(releaseId, releaseTitle) {
        document.getElementById('priceModalTitle').textContent = releaseTitle || '';
        document.getElementById('price_release_id').value = releaseId;

        // 显示loading
        document.getElementById('priceLoading').classList.remove('d-none');
        document.getElementById('priceContent').classList.add('d-none');
        document.getElementById('priceEmpty').classList.add('d-none');

        try {
            const formData = new FormData();
            formData.append('release_id', releaseId);

            const response = await fetch('../api/admin/stock_prices.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            // 隐藏loading
            document.getElementById('priceLoading').classList.add('d-none');

            if (!result.success) {
                document.getElementById('priceEmpty').textContent = result.message || 'Failed to load data';
                document.getElementById('priceEmpty').classList.remove('d-none');
                return;
            }

            const data = result.data || [];

            if (data.length > 0) {
                // Group by condition
                const byCondition = {};
                data.forEach(row => {
                    const cond = row.condition;
                    if (!byCondition[cond]) {
                        byCondition[cond] = { price: row.price, totalQty: 0, shops: [] };
                    }
                    byCondition[cond].totalQty += parseInt(row.qty);
                    byCondition[cond].shops.push({
                        name: row.shop,
                        qty: row.qty,
                        price: row.price
                    });
                });

                // 按condition顺序排序
                const condOrder = ['New', 'Mint', 'NM', 'VG+', 'VG', 'G+', 'G', 'F', 'P'];
                const sortedConditions = Object.keys(byCondition).sort((a, b) =>
                    condOrder.indexOf(a) - condOrder.indexOf(b)
                );

                // Render cards
                let html = '<div class="row g-3">';
                sortedConditions.forEach(cond => {
                    const info = byCondition[cond];
                    const shopList = info.shops.map(s =>
                        `<div class="d-flex justify-content-between small">
                            <span class="text-muted">${escapeHtml(s.name)}</span>
                            <span><span class="badge bg-info me-1">x${s.qty}</span>¥${parseFloat(s.price).toFixed(2)}</span>
                        </div>`
                    ).join('');

                    html += `
                    <div class="col-md-6">
                        <div class="card bg-secondary bg-opacity-25 border-secondary">
                            <div class="card-header border-secondary d-flex justify-content-between align-items-center py-2">
                                <span class="badge bg-secondary fs-6">${escapeHtml(cond)}</span>
                                <span class="badge bg-warning text-dark">Total: ${info.totalQty} units</span>
                            </div>
                            <div class="card-body py-2">
                                <div class="mb-2" style="max-height: 100px; overflow-y: auto;">
                                    ${shopList}
                                </div>
                                <div class="row align-items-center">
                                    <div class="col-5">
                                        <small class="text-muted">Current:</small>
                                        <div class="text-success fw-bold">¥${parseFloat(info.price).toFixed(2)}</div>
                                    </div>
                                    <div class="col-7">
                                        <label class="small text-muted">New Price</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-dark border-secondary text-light">¥</span>
                                            <input type="number" step="0.01" min="0" name="prices[${escapeHtml(cond)}]"
                                                   class="form-control bg-dark text-white border-secondary"
                                                   placeholder="${parseFloat(info.price).toFixed(2)}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });
                html += '</div>';

                document.getElementById('priceCardsContainer').innerHTML = html;
                document.getElementById('priceContent').classList.remove('d-none');
            } else {
                document.getElementById('priceEmpty').textContent = 'No available stock found for this release.';
                document.getElementById('priceEmpty').classList.remove('d-none');
            }
        } catch (error) {
            console.error('Error loading stock prices:', error);
            document.getElementById('priceLoading').classList.add('d-none');
            document.getElementById('priceEmpty').textContent = 'Network error. Please try again.';
            document.getElementById('priceEmpty').classList.remove('d-none');
        }
    }

    // 使用 show.bs.modal 事件在模态框显示时加载数据
    priceModalEl.addEventListener('show.bs.modal', function() {
        if (currentReleaseId) {
            loadAndRenderPriceData(currentReleaseId, currentReleaseTitle);
        }
    });

    // 在按钮点击时设置数据并打开模态框
    document.querySelectorAll('.price-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentReleaseId = this.dataset.releaseId;
            currentReleaseTitle = this.dataset.releaseTitle;
            const modal = bootstrap.Modal.getOrCreateInstance(priceModalEl);
            modal.show();
        });
    });

    // 模态框关闭时重置状态
    priceModalEl.addEventListener('hidden.bs.modal', function() {
        document.getElementById('priceContent').classList.add('d-none');
        document.getElementById('priceEmpty').classList.add('d-none');
        document.getElementById('priceCardsContainer').innerHTML = '';
        currentReleaseId = null;
        currentReleaseTitle = null;
    });
});
