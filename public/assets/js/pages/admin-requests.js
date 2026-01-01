/**
 * Admin Requests Page JavaScript
 * 处理申请审批和AJAX实时获取库存信息
 */
document.addEventListener('DOMContentLoaded', function() {
    // 监听accordion展开事件
    document.querySelectorAll('.accordion-collapse').forEach(function(collapse) {
        collapse.addEventListener('show.bs.collapse', function() {
            const requestId = this.id.replace('req', '');
            const stockContainer = this.querySelector('.stock-inventory-container');
            const accordionBody = this.querySelector('.accordion-body');

            if (stockContainer) {
                // 获取申请参数
                const releaseId = stockContainer.dataset.releaseId;
                const condition = stockContainer.dataset.condition;
                const fromShopId = stockContainer.dataset.fromShopId;
                const quantity = stockContainer.dataset.quantity;

                // 显示加载状态
                stockContainer.innerHTML = '<div class="text-center py-3"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading real-time inventory...</div>';

                // 发送AJAX请求获取最新库存
                fetch('../api/admin/requests.php?action=get_inventory&release_id=' + releaseId + '&condition=' + encodeURIComponent(condition) + '&exclude_shop=' + fromShopId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateInventoryDisplay(stockContainer, accordionBody, data.data.inventory, quantity, requestId);
                        } else {
                            stockContainer.innerHTML = '<div class="alert alert-danger py-2"><i class="fa-solid fa-exclamation-circle me-1"></i>' + (data.message || 'Failed to load inventory') + '</div>';
                        }
                    })
                    .catch(error => {
                        stockContainer.innerHTML = '<div class="alert alert-warning py-2"><i class="fa-solid fa-exclamation-triangle me-1"></i>Error loading inventory. <a href="requests.php" class="alert-link">Refresh page</a></div>';
                    });
            }
        });
    });

    // 更新库存显示
    function updateInventoryDisplay(container, accordionBody, inventory, requiredQty, requestId) {
        const form = accordionBody ? accordionBody.querySelector('form') : null;

        if (!inventory || inventory.length === 0) {
            container.innerHTML = `
                <div class="alert alert-warning py-2">
                    <i class="fa-solid fa-exclamation-triangle me-1"></i>
                    No matching stock found in other shops for this album/condition.
                </div>`;

            // 禁用approve按钮
            if (form) {
                const approveBtn = form.querySelector('button[value="approve"]');
                if (approveBtn) {
                    approveBtn.disabled = true;
                    approveBtn.className = 'btn btn-secondary';
                    approveBtn.innerHTML = '<i class="fa-solid fa-ban me-1"></i>Cannot Approve (No Stock Available)';
                }
            }
        } else {
            let tableHtml = `
                <div class="table-responsive" style="max-height: 150px; overflow-y: auto;">
                    <table class="table table-sm table-dark mb-0">
                        <thead class="sticky-top bg-dark">
                            <tr><th>Shop</th><th class="text-center">Qty</th><th class="text-end">Price</th></tr>
                        </thead>
                        <tbody>`;

            inventory.forEach(inv => {
                const whBadge = inv.ShopType === 'Warehouse' ? '<span class="badge bg-secondary ms-1">WH</span>' : '';
                const qtyClass = inv.AvailableQuantity >= requiredQty ? 'bg-success' : 'bg-warning text-dark';
                tableHtml += `<tr>
                    <td>${inv.ShopName}${whBadge}</td>
                    <td class="text-center"><span class="badge ${qtyClass}">${inv.AvailableQuantity}</span></td>
                    <td class="text-end text-warning">¥${parseFloat(inv.UnitPrice).toFixed(2)}</td>
                </tr>`;
            });

            tableHtml += '</tbody></table></div>';
            container.innerHTML = tableHtml;

            // 更新源店铺选择下拉框和按钮
            if (form) {
                const selectContainer = form.querySelector('.source-shop-select-container');
                if (selectContainer) {
                    let selectHtml = `
                        <label class="form-label">Select Source Shop <span class="text-danger">*</span></label>
                        <select name="source_shop_id" class="form-select bg-dark text-white border-secondary" required>
                            <option value="">-- Choose source shop --</option>`;

                    inventory.forEach(inv => {
                        selectHtml += `<option value="${inv.ShopID}">${inv.ShopName} (${inv.AvailableQuantity} available @ ¥${parseFloat(inv.UnitPrice).toFixed(2)})</option>`;
                    });

                    selectHtml += '</select><small class="text-muted">Stock will be transferred from the selected shop</small>';
                    selectContainer.innerHTML = selectHtml;
                }

                // 启用approve按钮
                const approveBtn = form.querySelector('button[value="approve"]');
                if (approveBtn) {
                    approveBtn.disabled = false;
                    approveBtn.className = 'btn btn-success';
                    approveBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Approve Request';
                }
            }
        }
    }
});
