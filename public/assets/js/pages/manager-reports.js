/**
 * Manager Reports Page JavaScript
 * 处理报表详情的AJAX加载
 */
document.addEventListener('DOMContentLoaded', function() {
    // 辅助函数
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // 通用AJAX获取函数
    async function fetchDetails(type, value) {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('value', value);

        const response = await fetch('../api/manager/report_details.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        return result.success ? result.data : [];
    }

    // ========== Genre Detail Modal（AJAX方式）==========
    const genreModalEl = document.getElementById('genreDetailModal');
    let currentGenre = null;

    async function loadGenreDetail(genre) {
        document.getElementById('genreTitle').textContent = genre;
        document.getElementById('genreDetailLoading').classList.remove('d-none');
        document.getElementById('genreDetailContent').classList.add('d-none');
        document.getElementById('genreDetailEmpty').classList.add('d-none');

        try {
            const data = await fetchDetails('genre', genre);
            document.getElementById('genreDetailLoading').classList.add('d-none');

            if (data.length > 0) {
                const html = data.map(row => `<tr>
                    <td><span class="badge bg-info">#${row.OrderID}</span></td>
                    <td>${row.OrderDate}</td>
                    <td>${row.CustomerName || 'Guest'}</td>
                    <td>${escapeHtml(row.Title)}</td>
                    <td><small class="text-muted">${escapeHtml(row.ArtistName)}</small></td>
                    <td><span class="badge bg-secondary">${row.ConditionGrade}</span></td>
                    <td class="text-end text-success">¥${parseFloat(row.PriceAtSale).toFixed(2)}</td>
                </tr>`).join('');
                document.getElementById('genreDetailBody').innerHTML = html;
                document.getElementById('genreDetailContent').classList.remove('d-none');
            } else {
                document.getElementById('genreDetailEmpty').textContent = 'No order details found for this genre.';
                document.getElementById('genreDetailEmpty').classList.remove('d-none');
            }
        } catch (error) {
            console.error('Error fetching genre details:', error);
            document.getElementById('genreDetailLoading').classList.add('d-none');
            document.getElementById('genreDetailEmpty').textContent = 'Error loading details.';
            document.getElementById('genreDetailEmpty').classList.remove('d-none');
        }
    }

    genreModalEl.addEventListener('show.bs.modal', function() {
        if (currentGenre) {
            loadGenreDetail(currentGenre);
        }
    });

    document.querySelectorAll('.btn-genre-detail').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentGenre = this.dataset.genre;
            const modal = bootstrap.Modal.getOrCreateInstance(genreModalEl);
            modal.show();
        });
    });

    genreModalEl.addEventListener('hidden.bs.modal', function() {
        document.getElementById('genreDetailContent').classList.add('d-none');
        document.getElementById('genreDetailEmpty').classList.add('d-none');
        document.getElementById('genreDetailBody').innerHTML = '';
        currentGenre = null;
    });

    // ========== Month Detail Modal（AJAX方式）==========
    const monthModalEl = document.getElementById('monthDetailModal');
    let currentMonth = null;

    const typeBadges = {
        'POS': '<span class="badge bg-warning text-dark">POS</span>',
        'OnlinePickup': '<span class="badge bg-info">Pickup</span>',
        'OnlineSales': '<span class="badge bg-success">Shipping</span>'
    };

    async function loadMonthDetail(month) {
        document.getElementById('monthTitle').textContent = month;
        document.getElementById('monthDetailLoading').classList.remove('d-none');
        document.getElementById('monthDetailContent').classList.add('d-none');
        document.getElementById('monthDetailEmpty').classList.add('d-none');

        try {
            const data = await fetchDetails('month', month);
            document.getElementById('monthDetailLoading').classList.add('d-none');

            if (data.length > 0) {
                const html = data.map(row => {
                    const typeBadge = typeBadges[row.OrderCategory] || '<span class="badge bg-secondary">Other</span>';
                    return `<tr>
                        <td><span class="badge bg-info">#${row.OrderID}</span></td>
                        <td>${row.OrderDate}</td>
                        <td>${typeBadge}</td>
                        <td>${row.CustomerName || 'Guest'}</td>
                        <td>${escapeHtml(row.Title)}</td>
                        <td><span class="badge bg-secondary">${row.ConditionGrade}</span></td>
                        <td class="text-end text-success">¥${parseFloat(row.PriceAtSale).toFixed(2)}</td>
                    </tr>`;
                }).join('');
                document.getElementById('monthDetailBody').innerHTML = html;
                document.getElementById('monthDetailContent').classList.remove('d-none');
            } else {
                document.getElementById('monthDetailEmpty').textContent = 'No order details found for this month.';
                document.getElementById('monthDetailEmpty').classList.remove('d-none');
            }
        } catch (error) {
            console.error('Error fetching month details:', error);
            document.getElementById('monthDetailLoading').classList.add('d-none');
            document.getElementById('monthDetailEmpty').textContent = 'Error loading details.';
            document.getElementById('monthDetailEmpty').classList.remove('d-none');
        }
    }

    monthModalEl.addEventListener('show.bs.modal', function() {
        if (currentMonth) {
            loadMonthDetail(currentMonth);
        }
    });

    document.querySelectorAll('.btn-month-detail').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            currentMonth = this.dataset.month;
            const modal = bootstrap.Modal.getOrCreateInstance(monthModalEl);
            modal.show();
        });
    });

    monthModalEl.addEventListener('hidden.bs.modal', function() {
        document.getElementById('monthDetailContent').classList.add('d-none');
        document.getElementById('monthDetailEmpty').classList.add('d-none');
        document.getElementById('monthDetailBody').innerHTML = '';
        currentMonth = null;
    });
});
