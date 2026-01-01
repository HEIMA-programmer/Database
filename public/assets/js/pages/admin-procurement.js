/**
 * Admin Procurement Page JavaScript
 * 处理采购表单价格计算和成本显示
 */
document.addEventListener('DOMContentLoaded', function() {
    // DOM元素
    const releaseSelect = document.querySelector('select[name="release_id"]');
    const conditionSelect = document.querySelector('select[name="condition"]');
    const salePriceInput = document.getElementById('salePrice');
    const quantityInput = document.getElementById('poQuantity');
    const unitCostDisplay = document.getElementById('unitCostDisplay');
    const suggestedPriceDisplay = document.getElementById('suggestedPriceDisplay');
    const totalCostDisplay = document.getElementById('totalCostDisplay');
    const expectedRevenueDisplay = document.getElementById('expectedRevenueDisplay');
    const expectedProfitDisplay = document.getElementById('expectedProfitDisplay');

    let currentUnitCost = 25.00;

    // 通过AJAX从后端获取价格信息
    async function updatePriceByCondition() {
        const releaseId = releaseSelect.value;
        const condition = conditionSelect.value;

        if (!releaseId || !condition) {
            currentUnitCost = 25.00;
            unitCostDisplay.value = '¥25.00';
            suggestedPriceDisplay.value = '¥40.00';
            salePriceInput.value = '40.00';
            updateCosts();
            return;
        }

        // 显示加载状态
        unitCostDisplay.value = 'Loading...';
        suggestedPriceDisplay.value = 'Loading...';

        try {
            const formData = new FormData();
            formData.append('release_id', releaseId);
            formData.append('condition', condition);

            const response = await fetch('../api/admin/price_config.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                currentUnitCost = data.data.unit_cost;
                const suggestedPrice = data.data.suggested_price;

                unitCostDisplay.value = '¥' + currentUnitCost.toFixed(2);
                suggestedPriceDisplay.value = '¥' + suggestedPrice.toFixed(2);
                salePriceInput.value = suggestedPrice.toFixed(2);
            } else {
                currentUnitCost = 25.00;
                unitCostDisplay.value = '¥25.00';
                suggestedPriceDisplay.value = '¥40.00';
                salePriceInput.value = '40.00';
            }
        } catch (error) {
            console.error('Error fetching price config:', error);
            currentUnitCost = 25.00;
            unitCostDisplay.value = '¥25.00';
            suggestedPriceDisplay.value = '¥40.00';
            salePriceInput.value = '40.00';
        }

        updateCosts();
    }

    // 更新成本和利润计算（仅用于显示）
    function updateCosts() {
        const salePrice = parseFloat(salePriceInput.value) || 0;
        const quantity = parseInt(quantityInput.value) || 0;

        const totalCost = currentUnitCost * quantity;
        const expectedRevenue = salePrice * quantity;
        const profit = expectedRevenue - totalCost;
        const profitPercent = expectedRevenue > 0 ? (profit / expectedRevenue * 100) : 0;

        totalCostDisplay.textContent = '¥' + totalCost.toFixed(2);
        expectedRevenueDisplay.textContent = '¥' + expectedRevenue.toFixed(2);
        expectedProfitDisplay.textContent = '¥' + profit.toFixed(2) + ' (' + profitPercent.toFixed(1) + '%)';

        // 根据利润率设置颜色
        if (profitPercent < 20) {
            expectedProfitDisplay.className = 'fw-bold text-danger';
        } else if (profitPercent < 30) {
            expectedProfitDisplay.className = 'fw-bold text-warning';
        } else {
            expectedProfitDisplay.className = 'fw-bold text-success';
        }
    }

    // 监听专辑和condition的变化，通过AJAX获取价格
    releaseSelect.addEventListener('change', updatePriceByCondition);
    conditionSelect.addEventListener('change', updatePriceByCondition);
    salePriceInput.addEventListener('input', updateCosts);
    quantityInput.addEventListener('input', updateCosts);

    // 初始化
    updateCosts();

    // Receive modal
    document.querySelectorAll('.receive-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const poId = this.dataset.poId;
            document.getElementById('receivePOId').value = poId;
            document.getElementById('receivePOIdDisplay').textContent = poId;
        });
    });
});
