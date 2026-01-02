/**
 * Manager Reports Page JavaScript
 * 【修复】使用预加载数据替代AJAX，解决loading一直显示的问题
 * 【修复】增强按钮点击事件绑定，解决relatedTarget为空的问题
 */
document.addEventListener('DOMContentLoaded', function() {
    // 辅助函数
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // ========== Genre Detail Modal ==========
    const genreModalEl = document.getElementById('genreDetailModal');
    let pendingGenre = null;  // 存储待渲染的 genre

    function renderGenreDetail(genre) {
        if (!genre) {
            console.error('Genre is empty');
            return;
        }

        const titleEl = document.getElementById('genreTitle');
        const loadingEl = document.getElementById('genreDetailLoading');
        const contentEl = document.getElementById('genreDetailContent');
        const emptyEl = document.getElementById('genreDetailEmpty');
        const bodyEl = document.getElementById('genreDetailBody');

        if (!titleEl || !contentEl || !emptyEl || !bodyEl) {
            console.error('Genre modal elements not found');
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

    // 【修复】使用 shown.bs.modal 事件（模态框完全显示后触发）
    // 此时 click 事件肯定已执行完毕，pendingGenre 已正确设置
    document.querySelectorAll('.btn-genre-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            pendingGenre = this.dataset.genre;
        });
    });

    if (genreModalEl) {
        genreModalEl.addEventListener('shown.bs.modal', function() {
            if (pendingGenre) {
                renderGenreDetail(pendingGenre);
            }
        });
    }

    // ========== Month Detail Modal ==========
    const monthModalEl = document.getElementById('monthDetailModal');
    let pendingMonth = null;  // 存储待渲染的 month

    const typeBadges = {
        'POS': '<span class="badge bg-warning text-dark">POS</span>',
        'OnlinePickup': '<span class="badge bg-info">Pickup</span>',
        'OnlineSales': '<span class="badge bg-success">Shipping</span>'
    };

    function renderMonthDetail(month) {
        if (!month) {
            console.error('Month is empty');
            return;
        }

        const titleEl = document.getElementById('monthTitle');
        const loadingEl = document.getElementById('monthDetailLoading');
        const contentEl = document.getElementById('monthDetailContent');
        const emptyEl = document.getElementById('monthDetailEmpty');
        const bodyEl = document.getElementById('monthDetailBody');

        if (!titleEl || !contentEl || !emptyEl || !bodyEl) {
            console.error('Month modal elements not found');
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

    // 【修复】使用 shown.bs.modal 事件（模态框完全显示后触发）
    document.querySelectorAll('.btn-month-detail').forEach(btn => {
        btn.addEventListener('click', function() {
            pendingMonth = this.dataset.month;
        });
    });

    if (monthModalEl) {
        monthModalEl.addEventListener('shown.bs.modal', function() {
            if (pendingMonth) {
                renderMonthDetail(pendingMonth);
            }
        });
    }
});
