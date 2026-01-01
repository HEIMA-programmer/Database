/**
 * Staff POS Page JavaScript
 * 处理POS系统的AJAX操作和订单详情加载
 */

// 订单详情加载函数（全局可用）
async function loadOrderDetail(orderId) {
    const contentEl = document.getElementById('orderDetailContent');
    const titleEl = document.getElementById('orderDetailModalLabel');

    // 显示加载状态
    contentEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    titleEl.innerHTML = '<i class="fa-solid fa-receipt me-2"></i>Order Details';

    try {
        const formData = new FormData();
        formData.append('order_id', orderId);

        const response = await fetch('../api/staff/order_detail.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            contentEl.innerHTML = '<div class="text-center py-4 text-danger"><i class="fa-solid fa-exclamation-circle fa-3x mb-3 d-block"></i>' + (result.message || 'Failed to load order') + '</div>';
            return;
        }

        titleEl.innerHTML = '<i class="fa-solid fa-receipt me-2"></i>Order #' + orderId + ' Details';

        let html = '<div class="mb-3 p-3 bg-secondary bg-opacity-25 rounded">';
        html += '<div class="row">';
        html += '<div class="col-6"><strong class="text-muted">Customer:</strong> <span class="text-white">' + escapeHtml(result.data.info.customer) + '</span></div>';
        html += '<div class="col-6"><strong class="text-muted">Date:</strong> <span class="text-white">' + escapeHtml(result.data.info.date) + '</span></div>';
        html += '</div>';
        html += '<div class="row mt-2">';
        html += '<div class="col-6"><strong class="text-muted">Status:</strong> <span class="badge bg-' + (result.data.info.status == 'Completed' ? 'success' : 'info') + '">' + escapeHtml(result.data.info.status) + '</span></div>';
        html += '<div class="col-6"><strong class="text-muted">Total:</strong> <span class="text-warning fw-bold">¥' + parseFloat(result.data.info.total).toFixed(2) + '</span></div>';
        html += '</div></div>';

        html += '<h6 class="text-warning mt-3 mb-2"><i class="fa-solid fa-compact-disc me-2"></i>Items (' + result.data.items.length + ')</h6>';
        html += '<div class="table-responsive"><table class="table table-dark table-sm mb-0">';
        html += '<thead><tr><th>Release</th><th>Artist</th><th>Condition</th><th>Genre</th><th>Year</th><th>Price</th></tr></thead>';
        html += '<tbody>';

        result.data.items.forEach(function(item) {
            html += '<tr>';
            html += '<td class="text-white">' + escapeHtml(item.title) + '</td>';
            html += '<td class="text-muted">' + escapeHtml(item.artist) + '</td>';
            html += '<td><span class="badge bg-secondary">' + escapeHtml(item.condition) + '</span></td>';
            html += '<td class="text-muted">' + escapeHtml(item.genre) + '</td>';
            html += '<td class="text-muted">' + escapeHtml(item.year) + '</td>';
            html += '<td class="text-warning">¥' + parseFloat(item.price).toFixed(2) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        contentEl.innerHTML = html;
    } catch (error) {
        console.error('Error loading order detail:', error);
        contentEl.innerHTML = '<div class="text-center py-4 text-danger"><i class="fa-solid fa-exclamation-circle fa-3x mb-3 d-block"></i>Network error. Please try again.</div>';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// 初始化添加商品表单的AJAX提交
document.addEventListener('DOMContentLoaded', function() {
    const currentSearch = new URLSearchParams(window.location.search).get('q') || '';

    document.querySelectorAll('.add-item-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('pos.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                // 刷新页面但保留搜索词
                if (currentSearch) {
                    window.location.href = 'pos.php?q=' + encodeURIComponent(currentSearch);
                } else {
                    window.location.href = 'pos.php';
                }
            }).catch(error => {
                console.error('Error:', error);
                window.location.reload();
            });
        });
    });
});
