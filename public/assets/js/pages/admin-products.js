/**
 * Admin Products Page JavaScript
 * 处理编辑模态框和价格调整功能
 * 【修复】增强错误处理、超时和状态管理
 */
document.addEventListener('DOMContentLoaded', function() {
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    /**
     * 【修复】带超时的fetch函数
     */
    async function fetchWithTimeout(url, options, timeout = 10000) {
        const controller = new AbortController();
        const id = setTimeout(() => controller.abort(), timeout);

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });
            clearTimeout(id);
            return response;
        } catch (error) {
            clearTimeout(id);
            throw error;
        }
    }

    // Edit modal - 填充编辑表单
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const editId = document.getElementById('edit_id');
            const editTitle = document.getElementById('edit_title');
            const editArtist = document.getElementById('edit_artist');
            const editLabel = document.getElementById('edit_label');
            const editYear = document.getElementById('edit_year');
            const editGenre = document.getElementById('edit_genre');
            const editDesc = document.getElementById('edit_desc');

            if (editId) editId.value = this.dataset.id || '';
            if (editTitle) editTitle.value = this.dataset.title || '';
            if (editArtist) editArtist.value = this.dataset.artist || '';
            if (editLabel) editLabel.value = this.dataset.label || '';
            if (editYear) editYear.value = this.dataset.year || '';
            if (editGenre) editGenre.value = this.dataset.genre || '';
            if (editDesc) editDesc.value = this.dataset.desc || '';
        });
    });

    // ========== Price Modal ==========
    const priceModalEl = document.getElementById('priceModal');

    async function loadAndRenderPriceData(releaseId, releaseTitle) {
        const titleEl = document.getElementById('priceModalTitle');
        const releaseIdEl = document.getElementById('price_release_id');
        const loadingEl = document.getElementById('priceLoading');
        const contentEl = document.getElementById('priceContent');
        const emptyEl = document.getElementById('priceEmpty');
        const containerEl = document.getElementById('priceCardsContainer');

        // 检查所有必需元素是否存在
        if (!titleEl || !releaseIdEl || !loadingEl || !contentEl || !emptyEl || !containerEl) {
            console.error('Price modal elements not found');
            return;
        }

        titleEl.textContent = releaseTitle || '';
        releaseIdEl.value = releaseId;

        // 显示loading
        loadingEl.classList.remove('d-none');
        contentEl.classList.add('d-none');
        emptyEl.classList.add('d-none');

        try {
            const formData = new FormData();
            formData.append('release_id', releaseId);

            const response = await fetchWithTimeout('../api/admin/stock_prices.php', {
                method: 'POST',
                body: formData
            }, 10000);

            // 检查响应状态
            if (!response.ok) {
                // 尝试解析错误信息
                let errorMessage = `HTTP error ${response.status}`;
                try {
                    const errorResult = await response.json();
                    errorMessage = errorResult.message || errorMessage;
                } catch (e) {
                    // 忽略JSON解析错误
                }
                throw new Error(errorMessage);
            }

            const result = await response.json();

            // 隐藏loading
            loadingEl.classList.add('d-none');

            if (!result.success) {
                emptyEl.textContent = result.message || 'Failed to load data';
                emptyEl.classList.remove('d-none');
                return;
            }

            const data = result.data || [];

            if (Array.isArray(data) && data.length > 0) {
                // Group by condition
                const byCondition = {};
                data.forEach(row => {
                    const cond = row.condition;
                    if (!cond) return; // 跳过无效数据

                    if (!byCondition[cond]) {
                        byCondition[cond] = { price: row.price || 0, totalQty: 0, shops: [] };
                    }
                    byCondition[cond].totalQty += parseInt(row.qty) || 0;
                    byCondition[cond].shops.push({
                        name: row.shop || 'Unknown',
                        qty: row.qty || 0,
                        price: row.price || 0
                    });
                });

                // 检查是否有有效数据
                const conditionKeys = Object.keys(byCondition);
                if (conditionKeys.length === 0) {
                    emptyEl.textContent = 'No available stock found for this release.';
                    emptyEl.classList.remove('d-none');
                    return;
                }

                // 按condition顺序排序
                const condOrder = ['New', 'Mint', 'NM', 'VG+', 'VG', 'G+', 'G', 'F', 'P'];
                const sortedConditions = conditionKeys.sort((a, b) =>
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

                containerEl.innerHTML = html;
                contentEl.classList.remove('d-none');
            } else {
                emptyEl.textContent = 'No available stock found for this release.';
                emptyEl.classList.remove('d-none');
            }
        } catch (error) {
            console.error('Error loading stock prices:', error);
            loadingEl.classList.add('d-none');

            // 显示具体错误信息
            if (error.name === 'AbortError') {
                emptyEl.textContent = 'Request timeout. Please try again.';
            } else {
                emptyEl.textContent = error.message || 'Error loading data. Please try again.';
            }
            emptyEl.classList.remove('d-none');
        }
    }

    if (priceModalEl) {
        priceModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button && button.dataset.releaseId) {
                const releaseId = button.dataset.releaseId;
                const releaseTitle = button.dataset.releaseTitle || '';

                // 重置状态
                const loadingEl = document.getElementById('priceLoading');
                const contentEl = document.getElementById('priceContent');
                const emptyEl = document.getElementById('priceEmpty');
                const containerEl = document.getElementById('priceCardsContainer');

                if (loadingEl) loadingEl.classList.remove('d-none');
                if (contentEl) contentEl.classList.add('d-none');
                if (emptyEl) emptyEl.classList.add('d-none');
                if (containerEl) containerEl.innerHTML = '';

                // 加载数据
                loadAndRenderPriceData(releaseId, releaseTitle);
            }
        });

        // 模态框关闭时重置状态
        priceModalEl.addEventListener('hidden.bs.modal', function() {
            const contentEl = document.getElementById('priceContent');
            const emptyEl = document.getElementById('priceEmpty');
            const containerEl = document.getElementById('priceCardsContainer');
            const loadingEl = document.getElementById('priceLoading');

            if (contentEl) contentEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (containerEl) containerEl.innerHTML = '';
            if (loadingEl) loadingEl.classList.add('d-none');
        });
    }
});
