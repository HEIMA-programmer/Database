/**
 * Manager Reports Page JavaScript
 * Handles genre, month, artist, and batch detail modals
 */

// Helper function - globally available
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// ========== Genre Detail Modal ==========
function renderGenreDetail(genre, modalElement) {
    if (!genre) {
        console.error('Genre is empty');
        return;
    }

    const modal = modalElement || document.getElementById('genreDetailModal');
    if (!modal) {
        console.error('Genre modal not found');
        return;
    }

    const titleEl = modal.querySelector('#genreTitle');
    const contentEl = modal.querySelector('#genreDetailContent');
    const bodyEl = modal.querySelector('#genreDetailBody');

    if (!titleEl || !contentEl || !bodyEl) {
        console.error('Genre modal essential elements not found');
        return;
    }

    titleEl.textContent = genre;
    contentEl.classList.add('d-none');

    const data = window.preloadedGenreDetails && window.preloadedGenreDetails[genre];

    if (Array.isArray(data) && data.length > 0) {
        const html = data.map(row => `<tr>
            <td><span class="badge bg-info">#${row.OrderID || ''}</span></td>
            <td>${row.OrderDate || ''}</td>
            <td>${row.CustomerName || 'Guest'}</td>
            <td>${escapeHtml(row.Title || '')}</td>
            <td><small class="text-muted">${escapeHtml(row.ArtistName || '')}</small></td>
            <td><span class="badge bg-secondary">${row.ConditionGrade || ''}</span></td>
            <td class="text-end text-success">¥${parseFloat(row.PriceAtSale || 0).toFixed(2)}</td>
        </tr>`).join('');
        bodyEl.innerHTML = html;
        contentEl.classList.remove('d-none');
    } else {
        bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-warning py-4">
            <i class="fa-solid fa-info-circle me-1"></i>No order details found for this genre.
        </td></tr>`;
        contentEl.classList.remove('d-none');
    }
}

// ========== Month Detail Modal ==========
const typeBadges = {
    'POS': '<span class="badge bg-warning text-dark">POS</span>',
    'OnlinePickup': '<span class="badge bg-info">Pickup</span>',
    'OnlineSales': '<span class="badge bg-success">Shipping</span>'
};

function renderMonthDetail(month, modalElement) {
    if (!month) {
        console.error('Month is empty');
        return;
    }

    const modal = modalElement || document.getElementById('monthDetailModal');
    if (!modal) {
        console.error('Month modal not found');
        return;
    }

    const titleEl = modal.querySelector('#monthTitle');
    const contentEl = modal.querySelector('#monthDetailContent');
    const bodyEl = modal.querySelector('#monthDetailBody');

    if (!titleEl || !contentEl || !bodyEl) {
        console.error('Month modal essential elements not found');
        return;
    }

    titleEl.textContent = month;
    contentEl.classList.add('d-none');

    const data = window.preloadedMonthDetails && window.preloadedMonthDetails[month];

    if (Array.isArray(data) && data.length > 0) {
        // 计算商品折后收入总计和运费总计（按订单去重）
        let itemRevenueTotal = 0;
        const orderShipping = {}; // 用于按订单去重运费
        data.forEach(row => {
            itemRevenueTotal += parseFloat(row.ItemRevenue || 0);
            if (row.OrderID && row.ShippingCost !== undefined) {
                orderShipping[row.OrderID] = parseFloat(row.ShippingCost || 0);
            }
        });
        const shippingTotal = Object.values(orderShipping).reduce((a, b) => a + b, 0);
        const grandTotal = itemRevenueTotal + shippingTotal;

        const html = data.map(row => {
            const typeBadge = typeBadges[row.OrderCategory] || '<span class="badge bg-secondary">Other</span>';
            // 使用商品折后收入（不含运费）
            const revenue = parseFloat(row.ItemRevenue || 0);
            return `<tr>
                <td><span class="badge bg-info">#${row.OrderID || ''}</span></td>
                <td>${row.OrderDate || ''}</td>
                <td>${typeBadge}</td>
                <td>${row.CustomerName || 'Guest'}</td>
                <td>${escapeHtml(row.Title || '')}</td>
                <td><span class="badge bg-secondary">${row.ConditionGrade || ''}</span></td>
                <td class="text-end text-success">¥${revenue.toFixed(2)}</td>
            </tr>`;
        }).join('');

        // 添加底部统计行
        const footerHtml = `
            <tr class="table-secondary">
                <td colspan="6" class="text-end fw-bold">Items Subtotal:</td>
                <td class="text-end text-success fw-bold">¥${itemRevenueTotal.toFixed(2)}</td>
            </tr>
            <tr class="table-secondary">
                <td colspan="6" class="text-end fw-bold">Shipping Total:</td>
                <td class="text-end text-info fw-bold">¥${shippingTotal.toFixed(2)}</td>
            </tr>
            <tr class="table-warning">
                <td colspan="6" class="text-end fw-bold">Grand Total:</td>
                <td class="text-end text-warning fw-bold">¥${grandTotal.toFixed(2)}</td>
            </tr>
        `;
        bodyEl.innerHTML = html + footerHtml;
        contentEl.classList.remove('d-none');
    } else {
        bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-warning py-4">
            <i class="fa-solid fa-info-circle me-1"></i>No order details found for this month.
        </td></tr>`;
        contentEl.classList.remove('d-none');
    }
}

// ========== Artist Detail Modal ==========
function renderArtistDetail(artist, modalElement) {
    if (!artist) {
        console.error('Artist is empty');
        return;
    }

    const modal = modalElement || document.getElementById('artistDetailModal');
    if (!modal) {
        console.error('Artist modal not found');
        return;
    }

    const titleEl = modal.querySelector('#artistTitle');
    const contentEl = modal.querySelector('#artistDetailContent');
    const bodyEl = modal.querySelector('#artistDetailBody');
    const emptyEl = modal.querySelector('#artistDetailEmpty');

    if (!titleEl || !contentEl || !bodyEl) {
        console.error('Artist modal essential elements not found');
        return;
    }

    titleEl.textContent = artist;

    const data = window.preloadedArtistDetails && window.preloadedArtistDetails[artist];

    if (Array.isArray(data) && data.length > 0) {
        const html = data.map(row => {
            const profit = parseFloat(row.Profit || 0);
            const profitClass = profit >= 0 ? 'text-success' : 'text-danger';
            return `<tr>
                <td><span class="badge bg-info">#${row.OrderID || ''}</span></td>
                <td>${row.OrderDate || ''}</td>
                <td>${row.CustomerName || 'Guest'}</td>
                <td>${escapeHtml(row.Title || '')}</td>
                <td><span class="badge bg-secondary">${row.ConditionGrade || ''}</span></td>
                <td class="text-end text-info">¥${parseFloat(row.PriceAtSale || 0).toFixed(2)}</td>
                <td class="text-end text-muted">¥${parseFloat(row.Cost || 0).toFixed(2)}</td>
                <td class="text-end ${profitClass} fw-bold">¥${profit.toFixed(2)}</td>
            </tr>`;
        }).join('');
        bodyEl.innerHTML = html;
        contentEl.classList.remove('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');
    } else {
        bodyEl.innerHTML = `<tr><td colspan="8" class="text-center text-warning py-4">
            <i class="fa-solid fa-info-circle me-1"></i>No sales details found for this artist.
        </td></tr>`;
        contentEl.classList.remove('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');
    }
}

// ========== Batch Detail Modal ==========
function renderBatchDetail(batch, modalElement) {
    if (!batch) {
        console.error('Batch is empty');
        return;
    }

    const modal = modalElement || document.getElementById('batchDetailModal');
    if (!modal) {
        console.error('Batch modal not found');
        return;
    }

    const titleEl = modal.querySelector('#batchTitle');
    const contentEl = modal.querySelector('#batchDetailContent');
    const bodyEl = modal.querySelector('#batchDetailBody');
    const emptyEl = modal.querySelector('#batchDetailEmpty');

    if (!titleEl || !contentEl || !bodyEl) {
        console.error('Batch modal essential elements not found');
        return;
    }

    titleEl.textContent = batch;

    const data = window.preloadedBatchDetails && window.preloadedBatchDetails[batch];

    if (Array.isArray(data) && data.length > 0) {
        // 计算已售商品折后收入总计和运费总计（按订单去重）
        let itemRevenueTotal = 0;
        const orderShipping = {}; // 用于按订单去重运费
        data.forEach(row => {
            if (row.Status === 'Sold') {
                itemRevenueTotal += parseFloat(row.ItemSoldRevenue || 0);
                if (row.OrderID && row.ShippingCost !== undefined) {
                    orderShipping[row.OrderID] = parseFloat(row.ShippingCost || 0);
                }
            }
        });
        const shippingTotal = Object.values(orderShipping).reduce((a, b) => a + b, 0);
        const grandTotal = itemRevenueTotal + shippingTotal;

        const html = data.map(row => {
            const statusBadge = row.Status === 'Sold'
                ? '<span class="badge bg-success">Sold</span>'
                : '<span class="badge bg-warning text-dark">Available</span>';
            // 使用商品折后收入（不含运费），未售出显示标价
            const price = row.Status === 'Sold'
                ? `¥${parseFloat(row.ItemSoldRevenue || 0).toFixed(2)}`
                : `¥${parseFloat(row.UnitPrice || 0).toFixed(2)}`;
            const soldDate = row.SoldDate || '-';
            const customer = row.CustomerName || '-';
            return `<tr>
                <td>${escapeHtml(row.Title || '')}</td>
                <td><small class="text-muted">${escapeHtml(row.ArtistName || '')}</small></td>
                <td><span class="badge bg-secondary">${row.ConditionGrade || ''}</span></td>
                <td>${statusBadge}</td>
                <td class="text-end text-warning">${price}</td>
                <td>${soldDate}</td>
                <td>${customer}</td>
            </tr>`;
        }).join('');

        // 添加底部统计行（仅显示已售商品的统计）
        const footerHtml = `
            <tr class="table-secondary">
                <td colspan="4" class="text-end fw-bold">Sold Items Revenue:</td>
                <td class="text-end text-success fw-bold">¥${itemRevenueTotal.toFixed(2)}</td>
                <td colspan="2"></td>
            </tr>
            <tr class="table-secondary">
                <td colspan="4" class="text-end fw-bold">Shipping Total:</td>
                <td class="text-end text-info fw-bold">¥${shippingTotal.toFixed(2)}</td>
                <td colspan="2"></td>
            </tr>
            <tr class="table-warning">
                <td colspan="4" class="text-end fw-bold">Grand Total:</td>
                <td class="text-end text-warning fw-bold">¥${grandTotal.toFixed(2)}</td>
                <td colspan="2"></td>
            </tr>
        `;
        bodyEl.innerHTML = html + footerHtml;
        contentEl.classList.remove('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');
    } else {
        bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-warning py-4">
            <i class="fa-solid fa-info-circle me-1"></i>No items found in this batch.
        </td></tr>`;
        contentEl.classList.remove('d-none');
        if (emptyEl) emptyEl.classList.add('d-none');
    }
}

// Event listeners on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Genre Detail Modal
    const genreModal = document.getElementById('genreDetailModal');
    if (genreModal) {
        genreModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            const genre = button.getAttribute('data-genre');
            renderGenreDetail(genre, this);
        });
    }

    // Month Detail Modal
    const monthModal = document.getElementById('monthDetailModal');
    if (monthModal) {
        monthModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            const month = button.getAttribute('data-month');
            renderMonthDetail(month, this);
        });
    }

    // Artist Detail Modal
    const artistModal = document.getElementById('artistDetailModal');
    if (artistModal) {
        artistModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            const artist = button.getAttribute('data-artist');
            renderArtistDetail(artist, this);
        });
    }

    // Batch Detail Modal
    const batchModal = document.getElementById('batchDetailModal');
    if (batchModal) {
        batchModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            const batch = button.getAttribute('data-batch');
            renderBatchDetail(batch, this);
        });
    }
});
