/**
 * 58 区块城市 — 统一后台管理脚本
 * 所有子站后台共用此脚本
 */

(function() {
    'use strict';

    // ========== 移动端侧边栏切换 ==========
    document.addEventListener('DOMContentLoaded', function() {
        // 点击主内容区关闭移动端侧边栏
        const main = document.querySelector('.admin-main');
        const sidebar = document.querySelector('.admin-sidebar');

        if (main && sidebar) {
            main.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                }
            });
        }

        // ========== 表格行选择 ==========
        const tableRows = document.querySelectorAll('.admin-data-table tbody tr');
        tableRows.forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
                this.classList.toggle('selected');
            });
        });

        // ========== 确认操作 ==========
        document.querySelectorAll('[data-confirm]').forEach(function(el) {
            el.addEventListener('click', function(e) {
                const message = this.getAttribute('data-confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });

        // ========== 搜索框自动聚焦 ==========
        const searchInput = document.querySelector('.admin-search-box input');
        if (searchInput) {
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                }
            });
        }
    });

    // ========== 工具函数 ==========
    window.AdminUtils = {
        /**
         * 显示 Toast 提示
         */
        toast: function(message, type) {
            type = type || 'info';
            const colors = {
                success: '#22c55e',
                warning: '#f59e0b',
                danger:  '#ef4444',
                info:    '#3b82f6'
            };

            const div = document.createElement('div');
            div.style.cssText = 'position:fixed;top:20px;right:20px;padding:14px 24px;border-radius:8px;color:#fff;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.3);animation:fadeIn 0.3s ease;background:' + (colors[type] || colors.info);
            div.textContent = message;
            document.body.appendChild(div);

            setTimeout(function() {
                div.style.opacity = '0';
                div.style.transform = 'translateY(-10px)';
                div.style.transition = 'all 0.3s ease';
                setTimeout(function() { div.remove(); }, 300);
            }, 3000);
        },

        /**
         * 确认删除
         */
        confirmDelete: function(message) {
            return confirm(message || '确定要删除吗？此操作不可撤销。');
        },

        /**
         * 表格排序
         */
        sortTable: function(table, columnIndex, asc) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort(function(a, b) {
                const aVal = a.cells[columnIndex].textContent.trim();
                const bVal = b.cells[columnIndex].textContent.trim();

                const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return asc ? aNum - bNum : bNum - aNum;
                }
                return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });

            rows.forEach(function(row) { tbody.appendChild(row); });
        }
    };
})();
