/**
 * Retro Echo Records - UI Enhancement Scripts
 * 增强脚本 - 提升用户体验
 */

// ==================== 工具函数 ====================

/**
 * 显示加载动画
 */
function showLoading(message = 'Loading...') {
    const loadingHTML = `
        <div class="loading-overlay" id="loadingOverlay">
            <div class="vinyl-spinner"></div>
            <div class="loading-text">${message}</div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', loadingHTML);
}

/**
 * 隐藏加载动画
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
    }
}

/**
 * 显示Toast通知
 * @param {string} message - 消息内容
 * @param {string} type - success|danger|warning|info
 */
function showToast(message, type = 'success') {
    const iconMap = {
        success: 'fa-check-circle',
        danger: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    const toastHTML = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3"
             style="z-index: 9999; min-width: 300px;" role="alert">
            <i class="fas ${iconMap[type]} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', toastHTML);

    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        if (alerts.length > 0) {
            const lastAlert = alerts[alerts.length - 1];
            lastAlert.classList.remove('show');
            setTimeout(() => lastAlert.remove(), 300);
        }
    }, 3000);
}

/**
 * 确认对话框
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * 格式化货币
 */
function formatCurrency(amount) {
    return '¥' + parseFloat(amount).toFixed(2);
}

/**
 * 格式化日期
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

// ==================== 表单验证增强 ====================

/**
 * 实时表单验证
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                showToast('请填写所有必填字段', 'danger');
            }
            form.classList.add('was-validated');
        }, false);

        // 实时验证
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                if (input.checkValidity()) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                } else {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                }
            });
        });
    });
}

// ==================== 表格增强 ====================

/**
 * 表格排序
 */
function initTableSort() {
    const tables = document.querySelectorAll('.table-sortable');

    tables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sortable]');

        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.innerHTML += ' <i class="fas fa-sort text-muted"></i>';

            header.addEventListener('click', () => {
                sortTable(table, index);
            });
        });
    });
}

function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isAscending = table.dataset.sortOrder !== 'asc';

    rows.sort((a, b) => {
        const aText = a.cells[column].textContent.trim();
        const bText = b.cells[column].textContent.trim();

        const aVal = parseFloat(aText) || aText;
        const bVal = parseFloat(bText) || bText;

        if (aVal < bVal) return isAscending ? -1 : 1;
        if (aVal > bVal) return isAscending ? 1 : -1;
        return 0;
    });

    table.dataset.sortOrder = isAscending ? 'asc' : 'desc';

    rows.forEach(row => tbody.appendChild(row));
}

/**
 * 表格搜索过滤
 */
function initTableFilter() {
    const searchInputs = document.querySelectorAll('[data-table-filter]');

    searchInputs.forEach(input => {
        const tableId = input.dataset.tableFilter;
        const table = document.getElementById(tableId);

        if (table) {
            input.addEventListener('keyup', () => {
                const filter = input.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }
    });
}

// ==================== 数字计数动画 ====================

function animateNumber(element, target, duration = 1000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString();
    }, 16);
}

function initNumberAnimations() {
    const numbers = document.querySelectorAll('[data-animate-number]');

    numbers.forEach(el => {
        const target = parseInt(el.dataset.animateNumber);
        if (!isNaN(target)) {
            animateNumber(el, target);
        }
    });
}

/**
 * 更新购物车计数
 */
function updateCartCount(count) {
    const badge = document.querySelector('.fa-cart-shopping + .badge');
    if (badge) {
        badge.textContent = count;
        if (count > 0) {
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }
}

// ==================== 库存状态指示器 ====================

/**
 * 根据库存数量显示状态
 */
function getStockIndicator(quantity) {
    if (quantity === 0) {
        return '<span class="stock-indicator out"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>';
    } else if (quantity < 3) {
        return `<span class="stock-indicator low"><i class="fas fa-exclamation-triangle me-1"></i>Low (${quantity})</span>`;
    } else if (quantity < 10) {
        return `<span class="stock-indicator medium"><i class="fas fa-minus-circle me-1"></i>Medium (${quantity})</span>`;
    } else {
        return `<span class="stock-indicator high"><i class="fas fa-check-circle me-1"></i>In Stock (${quantity})</span>`;
    }
}

// ==================== 初始化 ====================

document.addEventListener('DOMContentLoaded', function() {
    // 初始化表单验证
    initFormValidation();

    // 初始化表格功能
    initTableSort();
    initTableFilter();

    // 初始化数字动画
    initNumberAnimations();

    // 初始化Bootstrap工具提示
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 平滑滚动
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // 自动隐藏Flash消息
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert:not(.alert-dismissible)');
        alerts.forEach(alert => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // 添加页面加载完成的淡入效果
    document.body.classList.add('fade-in');
});

// ==================== 导出函数供全局使用 ====================

window.RetroEcho = {
    showLoading,
    hideLoading,
    showToast,
    confirmAction,
    formatCurrency,
    formatDate,
    updateCartCount,
    getStockIndicator,
    animateNumber
};
