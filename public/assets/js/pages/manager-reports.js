/**
 * Manager Reports Page JavaScript
 * 处理报表详情的AJAX加载
 * 【修复】增强错误处理、超时和状态管理
 */
document.addEventListener('DOMContentLoaded', function() {
    // 辅助函数
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

    /**
     * 【修复】通用AJAX获取函数 - 增加响应状态检查和超时
     */
    async function fetchDetails(type, value) {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('value', value);

        const response = await fetchWithTimeout('../api/manager/report_details.php', {
            method: 'POST',
            body: formData
        }, 10000);

        // 检查响应状态
        if (!response.ok) {
            // 尝试解析错误信息
            try {
                const errorResult = await response.json();
                throw new Error(errorResult.message || `HTTP error ${response.status}`);
            } catch (e) {
                throw new Error(`HTTP error ${response.status}`);
            }
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'API returned error');
        }

        return result.data || [];
    }

    // ========== Genre Detail Modal ==========
    const genreModalEl = document.getElementById('genreDetailModal');

    async function loadGenreDetail(genre) {
        const titleEl = document.getElementById('genreTitle');
        const loadingEl = document.getElementById('genreDetailLoading');
        const contentEl = document.getElementById('genreDetailContent');
        const emptyEl = document.getElementById('genreDetailEmpty');
        const bodyEl = document.getElementById('genreDetailBody');

        // 检查所有必需元素是否存在
        if (!titleEl || !loadingEl || !contentEl || !emptyEl || !bodyEl) {
            console.error('Genre modal elements not found');
            return;
        }

        titleEl.textContent = genre;
        loadingEl.classList.remove('d-none');
        contentEl.classList.add('d-none');
        emptyEl.classList.add('d-none');

        try {
            const data = await fetchDetails('genre', genre);
            loadingEl.classList.add('d-none');

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
        } catch (error) {
            console.error('Error fetching genre details:', error);
            loadingEl.classList.add('d-none');

            // 显示具体错误信息
            if (error.name === 'AbortError') {
                emptyEl.textContent = 'Request timeout. Please try again.';
            } else {
                emptyEl.textContent = error.message || 'Error loading details. Please try again.';
            }
            emptyEl.classList.remove('d-none');
        }
    }

    if (genreModalEl) {
        genreModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button && button.dataset.genre) {
                const genre = button.dataset.genre;

                // 重置状态
                const loadingEl = document.getElementById('genreDetailLoading');
                const contentEl = document.getElementById('genreDetailContent');
                const emptyEl = document.getElementById('genreDetailEmpty');
                const bodyEl = document.getElementById('genreDetailBody');

                if (loadingEl) loadingEl.classList.remove('d-none');
                if (contentEl) contentEl.classList.add('d-none');
                if (emptyEl) emptyEl.classList.add('d-none');
                if (bodyEl) bodyEl.innerHTML = '';

                // 加载数据
                loadGenreDetail(genre);
            }
        });

        genreModalEl.addEventListener('hidden.bs.modal', function() {
            const contentEl = document.getElementById('genreDetailContent');
            const emptyEl = document.getElementById('genreDetailEmpty');
            const bodyEl = document.getElementById('genreDetailBody');
            const loadingEl = document.getElementById('genreDetailLoading');

            if (contentEl) contentEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (bodyEl) bodyEl.innerHTML = '';
            if (loadingEl) loadingEl.classList.add('d-none');
        });
    }

    // ========== Month Detail Modal ==========
    const monthModalEl = document.getElementById('monthDetailModal');

    const typeBadges = {
        'POS': '<span class="badge bg-warning text-dark">POS</span>',
        'OnlinePickup': '<span class="badge bg-info">Pickup</span>',
        'OnlineSales': '<span class="badge bg-success">Shipping</span>'
    };

    async function loadMonthDetail(month) {
        const titleEl = document.getElementById('monthTitle');
        const loadingEl = document.getElementById('monthDetailLoading');
        const contentEl = document.getElementById('monthDetailContent');
        const emptyEl = document.getElementById('monthDetailEmpty');
        const bodyEl = document.getElementById('monthDetailBody');

        // 检查所有必需元素是否存在
        if (!titleEl || !loadingEl || !contentEl || !emptyEl || !bodyEl) {
            console.error('Month modal elements not found');
            return;
        }

        titleEl.textContent = month;
        loadingEl.classList.remove('d-none');
        contentEl.classList.add('d-none');
        emptyEl.classList.add('d-none');

        try {
            const data = await fetchDetails('month', month);
            loadingEl.classList.add('d-none');

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
        } catch (error) {
            console.error('Error fetching month details:', error);
            loadingEl.classList.add('d-none');

            // 显示具体错误信息
            if (error.name === 'AbortError') {
                emptyEl.textContent = 'Request timeout. Please try again.';
            } else {
                emptyEl.textContent = error.message || 'Error loading details. Please try again.';
            }
            emptyEl.classList.remove('d-none');
        }
    }

    if (monthModalEl) {
        monthModalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button && button.dataset.month) {
                const month = button.dataset.month;

                // 重置状态
                const loadingEl = document.getElementById('monthDetailLoading');
                const contentEl = document.getElementById('monthDetailContent');
                const emptyEl = document.getElementById('monthDetailEmpty');
                const bodyEl = document.getElementById('monthDetailBody');

                if (loadingEl) loadingEl.classList.remove('d-none');
                if (contentEl) contentEl.classList.add('d-none');
                if (emptyEl) emptyEl.classList.add('d-none');
                if (bodyEl) bodyEl.innerHTML = '';

                // 加载数据
                loadMonthDetail(month);
            }
        });

        monthModalEl.addEventListener('hidden.bs.modal', function() {
            const contentEl = document.getElementById('monthDetailContent');
            const emptyEl = document.getElementById('monthDetailEmpty');
            const bodyEl = document.getElementById('monthDetailBody');
            const loadingEl = document.getElementById('monthDetailLoading');

            if (contentEl) contentEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (bodyEl) bodyEl.innerHTML = '';
            if (loadingEl) loadingEl.classList.add('d-none');
        });
    }
});
