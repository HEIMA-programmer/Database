/**
 * Customer Checkout Page JavaScript
 * 处理履行方式切换和价格更新
 */
document.addEventListener('DOMContentLoaded', function() {
    const pickupRadio = document.getElementById('pickup');
    const shippingRadio = document.getElementById('shipping');
    const addressSection = document.getElementById('shipping-address-section');
    const fulfillmentOptions = document.querySelectorAll('.fulfillment-option');
    const shippingCostDisplay = document.getElementById('shipping-cost-display');
    const totalDisplay = document.getElementById('total-display');

    // 价格显示数据从PHP传入window对象
    const priceDisplays = window.checkoutPriceDisplays || {
        shipping: { shippingCost: '¥15.00', total: '¥0.00' },
        pickup: { shippingCost: '<span class="text-success">Free</span>', total: '¥0.00' }
    };

    function updateUI() {
        // 更新地址显示
        // 修复：Warehouse 时没有 radio 按钮，地址栏应始终显示
        const isWarehouse = !pickupRadio && !shippingRadio;

        if (isWarehouse || (shippingRadio && shippingRadio.checked)) {
            addressSection.style.display = 'block';
        } else if (addressSection) {
            addressSection.style.display = 'none';
        }

        // 使用预渲染的价格显示
        if (shippingRadio && shippingRadio.checked) {
            shippingCostDisplay.textContent = priceDisplays.shipping.shippingCost;
            totalDisplay.textContent = priceDisplays.shipping.total;
        } else if (pickupRadio && pickupRadio.checked) {
            shippingCostDisplay.innerHTML = priceDisplays.pickup.shippingCost;
            totalDisplay.textContent = priceDisplays.pickup.total;
        }

        // 更新选中样式
        fulfillmentOptions.forEach(opt => {
            const radio = opt.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                opt.classList.remove('border-secondary');
                opt.classList.add('border-warning');
            } else {
                opt.classList.remove('border-warning');
                opt.classList.add('border-secondary');
            }
        });
    }

    if (pickupRadio) pickupRadio.addEventListener('change', updateUI);
    if (shippingRadio) shippingRadio.addEventListener('change', updateUI);

    // 点击卡片也能选中
    fulfillmentOptions.forEach(opt => {
        opt.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                updateUI();
            }
        });
    });

    // Form validation - require address for shipping
    const checkoutForm = document.querySelector('form');
    const addressField = document.getElementById('shipping_address');

    if (checkoutForm && addressField) {
        checkoutForm.addEventListener('submit', function(e) {
            const isWarehouse = !pickupRadio && !shippingRadio;
            const needsAddress = isWarehouse || (shippingRadio && shippingRadio.checked);

            if (needsAddress && !addressField.value.trim()) {
                e.preventDefault();
                addressField.classList.add('is-invalid');
                addressField.focus();

                // Show error message
                let errorDiv = addressField.parentNode.querySelector('.invalid-feedback');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'Please enter your shipping address';
                    addressField.parentNode.appendChild(errorDiv);
                }
                return false;
            }
        });

        // Clear error on input
        addressField.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    }

    updateUI();
});
