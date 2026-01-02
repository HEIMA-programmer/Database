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
// Support per-shop price adjustment when same album+condition exists in multiple shops
function renderPriceData(releaseId, releaseTitle, modalElement) {
    if (!releaseId) {
        console.error('ReleaseId is empty');
        return;
    }

    const modal = modalElement || document.getElementById('priceModal');
    if (!modal) {
        console.error('Price modal not found');
        return;
    }

    // Get essential elements
    const titleEl = modal.querySelector('#priceModalTitle');
    const releaseIdEl = modal.querySelector('#price_release_id');
    const contentEl = modal.querySelector('#priceContent');
    const containerEl = modal.querySelector('#priceCardsContainer');

    if (!titleEl || !releaseIdEl || !contentEl || !containerEl) {
        console.error('Price modal essential elements not found');
        return;
    }

    titleEl.textContent = releaseTitle || '';
    releaseIdEl.value = releaseId;

    // Get preloaded data
    let data = window.preloadedStockPrices && window.preloadedStockPrices[releaseId];
    if (!data && window.preloadedStockPrices) {
        data = window.preloadedStockPrices[parseInt(releaseId)];
    }

    if (Array.isArray(data) && data.length > 0) {
        // Group by condition, then by shop
        const byCondition = {};
        data.forEach(row => {
            const cond = row.condition;
            const shop = row.shop || 'Unknown';
            const shopId = row.shop_id || 0;
            if (!cond) return;

            if (!byCondition[cond]) {
                byCondition[cond] = { totalQty: 0, shops: {} };
            }
            byCondition[cond].totalQty += parseInt(row.qty) || 0;
            // Use shop name as key since shop_id might not be available
            if (!byCondition[cond].shops[shop]) {
                byCondition[cond].shops[shop] = {
                    name: shop,
                    shopId: shopId,
                    qty: 0,
                    price: row.price || 0
                };
            }
            byCondition[cond].shops[shop].qty += parseInt(row.qty) || 0;
        });

        const conditionKeys = Object.keys(byCondition);
        if (conditionKeys.length === 0) {
            containerEl.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fa-solid fa-exclamation-triangle me-1"></i>
                    No available stock found for this release.
                </div>`;
            contentEl.classList.remove('d-none');
            return;
        }

        // Sort conditions
        const condOrder = ['New', 'Mint', 'NM', 'VG+', 'VG', 'G+', 'G', 'F', 'P'];
        const sortedConditions = conditionKeys.sort((a, b) =>
            condOrder.indexOf(a) - condOrder.indexOf(b)
        );

        // Render cards with per-shop price inputs
        let html = '<div class="row g-3">';
        sortedConditions.forEach(cond => {
            const info = byCondition[cond];
            const shopEntries = Object.values(info.shops);
            const hasMultipleShops = shopEntries.length > 1;

            // Build shop list with individual price inputs if multiple shops
            let shopContent = '';
            if (hasMultipleShops) {
                shopContent = shopEntries.map(s =>
                    `<div class="d-flex justify-content-between align-items-center small mb-2 p-2 bg-dark rounded">
                        <div>
                            <span class="text-light">${escapeHtml(s.name)}</span>
                            <span class="badge bg-info ms-1">x${s.qty}</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="text-warning me-2">¥${parseFloat(s.price).toFixed(2)}</span>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <span class="input-group-text bg-dark border-secondary text-light py-0">¥</span>
                                <input type="number" step="0.01" min="0"
                                       name="shop_prices[${escapeHtml(cond)}][${escapeHtml(s.name)}]"
                                       class="form-control form-control-sm bg-dark text-white border-secondary py-0"
                                       placeholder="${parseFloat(s.price).toFixed(2)}"
                                       title="New price for ${escapeHtml(s.name)}">
                            </div>
                        </div>
                    </div>`
                ).join('');
            } else {
                // Single shop - simpler layout
                const s = shopEntries[0];
                shopContent = `
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">${escapeHtml(s.name)}</span>
                        <span><span class="badge bg-info me-1">x${s.qty}</span>¥${parseFloat(s.price).toFixed(2)}</span>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-5">
                            <small class="text-muted">Current:</small>
                            <div class="text-success fw-bold">¥${parseFloat(s.price).toFixed(2)}</div>
                        </div>
                        <div class="col-7">
                            <label class="small text-muted">New Price</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-dark border-secondary text-light">¥</span>
                                <input type="number" step="0.01" min="0" name="prices[${escapeHtml(cond)}]"
                                       class="form-control bg-dark text-white border-secondary"
                                       placeholder="${parseFloat(s.price).toFixed(2)}">
                            </div>
                        </div>
                    </div>`;
            }

            html += `
            <div class="col-md-6">
                <div class="card bg-secondary bg-opacity-25 border-secondary">
                    <div class="card-header border-secondary d-flex justify-content-between align-items-center py-2">
                        <span class="badge bg-secondary fs-6">${escapeHtml(cond)}</span>
                        <span class="badge bg-warning text-dark">Total: ${info.totalQty} units</span>
                    </div>
                    <div class="card-body py-2">
                        ${hasMultipleShops ? '<small class="text-info d-block mb-2"><i class="fa-solid fa-store me-1"></i>Multiple shops - set price per shop:</small>' : ''}
                        <div style="max-height: 200px; overflow-y: auto;">
                            ${shopContent}
                        </div>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';

        containerEl.innerHTML = html;
        contentEl.classList.remove('d-none');
    } else {
        containerEl.innerHTML = `
            <div class="alert alert-warning">
                <i class="fa-solid fa-exclamation-triangle me-1"></i>
                No available stock found for this release.
            </div>`;
        contentEl.classList.remove('d-none');
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

    // Price modal - 直接在show.bs.modal事件中渲染（数据已预加载，无需等待）
    const priceModal = document.getElementById('priceModal');
    if (priceModal) {
        priceModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const releaseId = button.getAttribute('data-release-id');
            const releaseTitle = button.getAttribute('data-release-title');

            // 数据已预加载，直接渲染，无需loading
            renderPriceData(releaseId, releaseTitle, this);
        });
    }
});
