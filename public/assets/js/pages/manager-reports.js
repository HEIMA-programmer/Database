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
    // 保存当前选中的genre
    let currentGenre = null;

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

    // 【修复】使用 mousedown 事件（比 click 更早触发），确保在 show.bs.modal 之前保存数据
    document.querySelectorAll('.btn-genre-detail').forEach(btn => {
        btn.addEventListener('mousedown', function() {
            currentGenre = this.dataset.genre || null;
        });
    });

    // 【修复】使用事件委托作为备用方案
    document.addEventListener('mousedown', function(e) {
        const btn = e.target.closest('.btn-genre-detail');
        if (btn) {
            currentGenre = btn.dataset.genre || null;
        }
    });

    if (genreModalEl) {
        genreModalEl.addEventListener('show.bs.modal', function(event) {
            // 优先使用 relatedTarget，如果为空则使用保存的数据
            const button = event.relatedTarget;
            const genre = (button && button.dataset.genre) ? button.dataset.genre : currentGenre;

            if (genre) {
                renderGenreDetail(genre);
            } else {
                // 如果没有数据，显示空提示
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

            // 重置标题，避免显示上次的内容
            if (titleEl) titleEl.textContent = '';
            if (contentEl) contentEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (bodyEl) bodyEl.innerHTML = '';
            // 注意：不再清除 currentGenre，让下次 mousedown 事件来更新
        });
    }

    // ========== Month Detail Modal ==========
    const monthModalEl = document.getElementById('monthDetailModal');
    // 保存当前选中的month
    let currentMonth = null;

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

    // 【修复】使用 mousedown 事件（比 click 更早触发），确保在 show.bs.modal 之前保存数据
    document.querySelectorAll('.btn-month-detail').forEach(btn => {
        btn.addEventListener('mousedown', function() {
            currentMonth = this.dataset.month || null;
        });
    });

    // 【修复】使用事件委托作为备用方案
    document.addEventListener('mousedown', function(e) {
        const btn = e.target.closest('.btn-month-detail');
        if (btn) {
            currentMonth = btn.dataset.month || null;
        }
    });

    if (monthModalEl) {
        monthModalEl.addEventListener('show.bs.modal', function(event) {
            // 优先使用 relatedTarget，如果为空则使用保存的数据
            const button = event.relatedTarget;
            const month = (button && button.dataset.month) ? button.dataset.month : currentMonth;

            if (month) {
                renderMonthDetail(month);
            } else {
                // 如果没有数据，显示空提示
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

            // 重置标题，避免显示上次的内容
            if (titleEl) titleEl.textContent = '';
            if (contentEl) contentEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (bodyEl) bodyEl.innerHTML = '';
            // 注意：不再清除 currentMonth，让下次 mousedown 事件来更新
        });
    }
});
