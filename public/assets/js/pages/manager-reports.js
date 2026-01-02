/**
 * Manager Reports Page JavaScript
 * 【修复】使用预加载数据替代AJAX，解决loading一直显示的问题
 * 【修复】使用onclick直接调用渲染函数，与pos.php的Detail按钮处理方式完全一致
 */

// 辅助函数 - 全局可用
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// ========== Genre Detail Modal ==========
// 【修复】简化元素依赖，只使用必要的元素
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

    // 从预加载数据获取
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
        // 【修复】直接在表格中显示"无数据"消息
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

// 【修复】简化元素依赖，只使用必要的元素
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

    // 从预加载数据获取
    const data = window.preloadedMonthDetails && window.preloadedMonthDetails[month];

    if (Array.isArray(data) && data.length > 0) {
        const html = data.map(row => {
            const typeBadge = typeBadges[row.OrderCategory] || '<span class="badge bg-secondary">Other</span>';
            return `<tr>
                <td><span class="badge bg-info">#${row.OrderID || ''}</span></td>
                <td>${row.OrderDate || ''}</td>
                <td>${typeBadge}</td>
                <td>${row.CustomerName || 'Guest'}</td>
                <td>${escapeHtml(row.Title || '')}</td>
                <td><span class="badge bg-secondary">${row.ConditionGrade || ''}</span></td>
                <td class="text-end text-success">¥${parseFloat(row.PriceAtSale || 0).toFixed(2)}</td>
            </tr>`;
        }).join('');
        bodyEl.innerHTML = html;
        contentEl.classList.remove('d-none');
    } else {
        // 【修复】直接在表格中显示"无数据"消息
        bodyEl.innerHTML = `<tr><td colspan="7" class="text-center text-warning py-4">
            <i class="fa-solid fa-info-circle me-1"></i>No order details found for this month.
        </td></tr>`;
        contentEl.classList.remove('d-none');
    }
}

// 直接在show.bs.modal事件中渲染（数据已预加载，无需等待）
document.addEventListener('DOMContentLoaded', function() {
    // Genre Detail Modal
    const genreModal = document.getElementById('genreDetailModal');
    if (genreModal) {
        genreModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const genre = button.getAttribute('data-genre');
            // 数据已预加载，直接渲染
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
            // 数据已预加载，直接渲染
            renderMonthDetail(month, this);
        });
    }
});
