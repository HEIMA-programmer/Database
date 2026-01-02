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
    // 【修复】保存最近点击的按钮元素
    let lastClickedGenreBtn = null;

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

    // 【修复】使用事件委托捕获 mousedown，保存按钮元素
    document.addEventListener('mousedown', function(e) {
        const btn = e.target.closest('.btn-genre-detail');
        if (btn) {
            lastClickedGenreBtn = btn;
        }
    });

    if (genreModalEl) {
        genreModalEl.addEventListener('show.bs.modal', function(event) {
            // 获取触发按钮：优先使用 relatedTarget，否则使用保存的按钮
            const button = event.relatedTarget || lastClickedGenreBtn;

            // 从按钮获取数据
            let genre = null;
            if (button && button.dataset) {
                genre = button.dataset.genre || button.getAttribute('data-genre');
            }

            if (genre) {
                renderGenreDetail(genre);
            } else {
                const emptyEl = document.getElementById('genreDetailEmpty');
                const loadingEl = document.getElementById('genreDetailLoading');
                const contentEl = document.getElementById('genreDetailContent');
                if (loadingEl) loadingEl.classList.add('d-none');
                if (contentEl) contentEl.classList.add('d-none');
                if (emptyEl) {
                    emptyEl.textContent = 'Unable to load genre details.';
                    emptyEl.classList.remove('d-none');
                }
            }
        });

        genreModalEl.addEventListener('hidden.bs.modal', function() {
            const titleEl = document.getElementById('genreTitle');
            const contentEl = document.getElementById('genreDetailContent');
            const emptyEl = document.getElementById('genreDetailEmpty');
            const bodyEl = document.getElementById('genreDetailBody');

            if (titleEl) titleEl.textContent = '';
            if (contentEl) contentEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (bodyEl) bodyEl.innerHTML = '';
        });
    }

    // ========== Month Detail Modal ==========
    const monthModalEl = document.getElementById('monthDetailModal');
    // 【修复】保存最近点击的按钮元素
    let lastClickedMonthBtn = null;

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

    // 【修复】使用事件委托捕获 mousedown，保存按钮元素
    document.addEventListener('mousedown', function(e) {
        const btn = e.target.closest('.btn-month-detail');
        if (btn) {
            lastClickedMonthBtn = btn;
        }
    });

    if (monthModalEl) {
        monthModalEl.addEventListener('show.bs.modal', function(event) {
            // 获取触发按钮：优先使用 relatedTarget，否则使用保存的按钮
            const button = event.relatedTarget || lastClickedMonthBtn;

            // 从按钮获取数据
            let month = null;
            if (button && button.dataset) {
                month = button.dataset.month || button.getAttribute('data-month');
            }

            if (month) {
                renderMonthDetail(month);
            } else {
                const emptyEl = document.getElementById('monthDetailEmpty');
                const loadingEl = document.getElementById('monthDetailLoading');
                const contentEl = document.getElementById('monthDetailContent');
                if (loadingEl) loadingEl.classList.add('d-none');
                if (contentEl) contentEl.classList.add('d-none');
                if (emptyEl) {
                    emptyEl.textContent = 'Unable to load month details.';
                    emptyEl.classList.remove('d-none');
                }
            }
        });

        monthModalEl.addEventListener('hidden.bs.modal', function() {
            const titleEl = document.getElementById('monthTitle');
            const contentEl = document.getElementById('monthDetailContent');
            const emptyEl = document.getElementById('monthDetailEmpty');
            const bodyEl = document.getElementById('monthDetailBody');

            if (titleEl) titleEl.textContent = '';
            if (contentEl) contentEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (bodyEl) bodyEl.innerHTML = '';
        });
    }
});
