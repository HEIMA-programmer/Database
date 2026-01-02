/**
 * Admin Products Page JavaScript
 * 【修复】使用预加载数据替代AJAX，解决loading一直显示的问题
 * 【修复】使用onclick直接调用渲染函数，与pos.php的Detail按钮处理方式完全一致
 */

// 辅助函数 - 全局可用
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// ========== Price Modal ==========
// 【修复】从模态框元素内部查找子元素，避免DOM查询问题
function renderPriceData(releaseId, releaseTitle, modalElement) {
    if (!releaseId) {
        console.error('ReleaseId is empty');
        return;
    }

    // 如果没有传入modalElement，尝试获取
    const modal = modalElement || document.getElementById('priceModal');
    if (!modal) {
        console.error('Price modal not found');
        return;
    }

    // 从模态框内部查找元素，确保找到正确的元素
    const titleEl = modal.querySelector('#priceModalTitle');
    const releaseIdEl = modal.querySelector('#price_release_id');
    const loadingEl = modal.querySelector('#priceLoading');
    const contentEl = modal.querySelector('#priceContent');
    const emptyEl = modal.querySelector('#priceEmpty');
    const containerEl = modal.querySelector('#priceCardsContainer');

    if (!titleEl || !releaseIdEl || !contentEl || !emptyEl || !containerEl) {
        console.error('Price modal elements not found inside modal');
        return;
    }

    titleEl.textContent = releaseTitle || '';
    releaseIdEl.value = releaseId;

    // 先隐藏所有状态
    if (loadingEl) loadingEl.classList.add('d-none');
    contentEl.classList.add('d-none');
    emptyEl.classList.add('d-none');

    // 从预加载数据获取（支持数字和字符串类型的键）
    let data = window.preloadedStockPrices && window.preloadedStockPrices[releaseId];
    // 如果用字符串找不到，尝试用数字
    if (!data && window.preloadedStockPrices) {
        data = window.preloadedStockPrices[parseInt(releaseId)];
    }

    if (Array.isArray(data) && data.length > 0) {
        // Group by condition
        const byCondition = {};
        data.forEach(row => {
            const cond = row.condition;
            if (!cond) return;

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
}

// 使用 DOMContentLoaded 确保DOM完全加载
document.addEventListener('DOMContentLoaded', function() {
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

    // 【修复】Price modal - 使用Bootstrap的show.bs.modal事件，避免onclick冲突
    const priceModal = document.getElementById('priceModal');
    if (priceModal) {
        priceModal.addEventListener('show.bs.modal', function(event) {
            // 获取触发按钮
            const button = event.relatedTarget;
            if (!button) return;

            const releaseId = button.getAttribute('data-release-id');
            const releaseTitle = button.getAttribute('data-release-title');

            // 调用渲染函数，传入模态框元素本身
            if (releaseId) {
                renderPriceData(releaseId, releaseTitle, this);
            }
        });
    }
});
