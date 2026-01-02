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
// 【修复】从模态框元素内部查找子元素，避免DOM查询问题
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
    const loadingEl = modal.querySelector('#genreDetailLoading');
    const contentEl = modal.querySelector('#genreDetailContent');
    const emptyEl = modal.querySelector('#genreDetailEmpty');
    const bodyEl = modal.querySelector('#genreDetailBody');

    if (!titleEl || !contentEl || !emptyEl || !bodyEl) {
        console.error('Genre modal elements not found inside modal');
        return;
    }

    titleEl.textContent = genre;

    // 先隐藏所有状态
    if (loadingEl) loadingEl.classList.add('d-none');
    contentEl.classList.add('d-none');
    emptyEl.classList.add('d-none');

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
        emptyEl.textContent = 'No order details found for this genre.';
        emptyEl.classList.remove('d-none');
    }
}

// ========== Month Detail Modal ==========
const typeBadges = {
    'POS': '<span class="badge bg-warning text-dark">POS</span>',
    'OnlinePickup': '<span class="badge bg-info">Pickup</span>',
    'OnlineSales': '<span class="badge bg-success">Shipping</span>'
};

// 【修复】从模态框元素内部查找子元素，避免DOM查询问题
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
    const loadingEl = modal.querySelector('#monthDetailLoading');
    const contentEl = modal.querySelector('#monthDetailContent');
    const emptyEl = modal.querySelector('#monthDetailEmpty');
    const bodyEl = modal.querySelector('#monthDetailBody');

    if (!titleEl || !contentEl || !emptyEl || !bodyEl) {
        console.error('Month modal elements not found inside modal');
        return;
    }

    titleEl.textContent = month;

    // 先隐藏所有状态
    if (loadingEl) loadingEl.classList.add('d-none');
    contentEl.classList.add('d-none');
    emptyEl.classList.add('d-none');

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
        emptyEl.textContent = 'No order details found for this month.';
        emptyEl.classList.remove('d-none');
    }
}

// 【修复】使用Bootstrap的show.bs.modal事件，传入模态框元素避免DOM查询问题
document.addEventListener('DOMContentLoaded', function() {
    // Genre Detail Modal
    const genreModal = document.getElementById('genreDetailModal');
    if (genreModal) {
        genreModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const genre = button.getAttribute('data-genre');
            if (genre) {
                renderGenreDetail(genre, this);
            }
        });
    }

    // Month Detail Modal
    const monthModal = document.getElementById('monthDetailModal');
    if (monthModal) {
        monthModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;

            const month = button.getAttribute('data-month');
            if (month) {
                renderMonthDetail(month, this);
            }
        });
    }
});
