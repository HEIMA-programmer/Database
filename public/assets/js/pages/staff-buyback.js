/**
 * Staff Buyback Page JavaScript
 * 处理回购表单的价格计算和AJAX价格获取
 */
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const priceInput = document.getElementById('unit_price');
    const resalePriceInput = document.getElementById('resale_price');
    const totalDisplay = document.getElementById('total-payment');
    const pointsDisplay = document.getElementById('points-preview');
    const customerSelect = document.getElementById('customer_id');
    const releaseSelect = document.getElementById('release_id');
    const conditionSelect = document.querySelector('select[name="condition"]');

    function updateTotal() {
        const quantity = parseInt(quantityInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const total = quantity * price;

        totalDisplay.textContent = '¥' + total.toFixed(2);

        // 计算积分预览
        const customerId = customerSelect.value;
        if (customerId && total > 0) {
            const points = Math.floor(total * 0.5);
            pointsDisplay.innerHTML = '<i class="fa-solid fa-coins text-warning me-1"></i>+' + points + ' points';
        } else {
            pointsDisplay.textContent = '';
        }
    }

    // 通过AJAX从后端获取建议价格
    async function updateResalePrice() {
        const releaseId = releaseSelect.value;
        const condition = conditionSelect.value;

        if (!releaseId || !condition) {
            resalePriceInput.value = '';
            resalePriceInput.classList.remove('border-success', 'border-warning');
            resalePriceInput.title = '';
            return;
        }

        // 显示加载状态
        resalePriceInput.placeholder = 'Loading...';
        resalePriceInput.disabled = true;

        try {
            const formData = new FormData();
            formData.append('release_id', releaseId);
            formData.append('condition', condition);

            const response = await fetch('../api/staff/calculate_price.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                resalePriceInput.value = data.data.price.toFixed(2);
                if (data.data.source === 'existing') {
                    resalePriceInput.classList.add('border-success');
                    resalePriceInput.classList.remove('border-warning');
                    resalePriceInput.title = '已自动填充当前库存售价';
                } else {
                    resalePriceInput.classList.add('border-warning');
                    resalePriceInput.classList.remove('border-success');
                    resalePriceInput.title = '建议售价（系统计算）';
                }
            } else {
                resalePriceInput.value = '';
                resalePriceInput.classList.remove('border-success', 'border-warning');
                resalePriceInput.title = '请手动设置转售价格';
            }
        } catch (error) {
            console.error('Error fetching price:', error);
            resalePriceInput.value = '';
            resalePriceInput.classList.remove('border-success', 'border-warning');
            resalePriceInput.title = '请手动设置转售价格';
        } finally {
            resalePriceInput.placeholder = 'List price';
            resalePriceInput.disabled = false;
        }
    }

    quantityInput.addEventListener('input', updateTotal);
    priceInput.addEventListener('input', updateTotal);
    customerSelect.addEventListener('change', updateTotal);

    // 监听Release和Condition变化，通过AJAX获取建议价格
    releaseSelect.addEventListener('change', updateResalePrice);
    conditionSelect.addEventListener('change', updateResalePrice);

    updateTotal();
});
