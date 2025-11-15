/**
 * FILE: assets/js/report.js
 * Xử lý validation & logic báo cáo
 */

document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const tuNgayInput = document.getElementById('tuNgay');
    const denNgayInput = document.getElementById('denNgay');
    const thangSelect = document.getElementById('thang');

    // ========== VALIDATION NGÀY ==========
    const currentYear = new Date().getFullYear();
    const minYear = 2020;
    const minDate = `${minYear}-01-01`;
    const maxDate = `${currentYear}-12-31`;

    if (tuNgayInput) {
        tuNgayInput.min = minDate;
        tuNgayInput.max = maxDate;
        tuNgayInput.addEventListener('change', validateDateRange);
        tuNgayInput.addEventListener('blur', validateDateInput);
    }

    if (denNgayInput) {
        denNgayInput.min = minDate;
        denNgayInput.max = maxDate;
        denNgayInput.addEventListener('change', validateDateRange);
        denNgayInput.addEventListener('blur', validateDateInput);
    }

    if (thangSelect) {
        thangSelect.addEventListener('change', updateDateRangeFromMonth);
    }

    /**
     * Validate input ngày riêng lẻ
     */
    function validateDateInput(e) {
        const input = e.target;
        const value = input.value;

        clearError(input);

        if (!value) {
            showError(input, 'Vui lòng chọn ngày');
            return false;
        }

        const date = new Date(value);
        const year = date.getFullYear();

        if (year < minYear) {
            showError(input, `Năm phải >= ${minYear}`);
            input.value = '';
            return false;
        }

        if (year > currentYear) {
            showError(input, `Năm phải <= ${currentYear}`);
            input.value = '';
            return false;
        }

        return true;
    }

    /**
     * Validate khoảng ngày
     */
    function validateDateRange() {
        const tuNgay = tuNgayInput?.value;
        const denNgay = denNgayInput?.value;

        if (!tuNgay || !denNgay) return true;

        clearError(tuNgayInput);
        clearError(denNgayInput);

        const tuDate = new Date(tuNgay);
        const denDate = new Date(denNgay);

        // Đảo ngược nếu từ ngày > đến ngày
        if (tuDate > denDate) {
            showError(denNgayInput, 'Đến ngày phải >= Từ ngày');
            return false;
        }

        // Giới hạn khoảng không quá 90 ngày
        const diff = Math.ceil((denDate - tuDate) / (1000 * 60 * 60 * 24));
        if (diff > 90) {
            showError(denNgayInput, 'Khoảng thời gian không vượt quá 90 ngày');
            return false;
        }

        return true;
    }

    /**
     * Cập nhật ngày từ tháng
     */
    function updateDateRangeFromMonth() {
        if (!thangSelect || !tuNgayInput || !denNgayInput) return;

        const thang = thangSelect.value;
        if (!thang) return;

        const [year, month] = thang.split('-');
        const firstDay = `${year}-${month}-01`;
        const lastDay = new Date(year, month, 0).toISOString().split('T')[0];

        tuNgayInput.value = firstDay;
        denNgayInput.value = lastDay;

        clearError(tuNgayInput);
        clearError(denNgayInput);
    }

    /**
     * Hiển thị lỗi
     */
    function showError(input, message) {
        if (!input) return;

        input.classList.add('error');
        let errorMsg = input.nextElementSibling;

        if (!errorMsg || !errorMsg.classList.contains('error-message')) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'error-message';
            input.parentElement.appendChild(errorMsg);
        }

        errorMsg.textContent = message;
    }

    /**
     * Xóa lỗi
     */
    function clearError(input) {
        if (!input) return;

        input.classList.remove('error');
        const errorMsg = input.nextElementSibling;
        if (errorMsg && errorMsg.classList.contains('error-message')) {
            errorMsg.remove();
        }
    }

    /**
     * Submit form
     */
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            const tuNgayValid = validateDateInput({ target: tuNgayInput });
            const denNgayValid = validateDateInput({ target: denNgayInput });
            const rangeValid = validateDateRange();

            if (!tuNgayValid || !denNgayValid || !rangeValid) {
                e.preventDefault();
                return false;
            }

            return true;
        });
    }

    /**
     * Sắp xếp bảng khi click header
     */
    document.querySelectorAll('th[data-sort]').forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const column = this.getAttribute('data-sort');
            const isAsc = this.getAttribute('data-order') === 'asc';
            sortTable(column, !isAsc);
        });
    });

    function sortTable(column, ascending) {
        const tbody = document.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            const cellA = a.querySelector(`td[data-column="${column}"]`)?.textContent.trim();
            const cellB = b.querySelector(`td[data-column="${column}"]`)?.textContent.trim();

            if (!cellA || !cellB) return 0;

            const numA = parseFloat(cellA.replace(/[^\d.-]/g, '')) || cellA;
            const numB = parseFloat(cellB.replace(/[^\d.-]/g, '')) || cellB;

            if (typeof numA === 'number' && typeof numB === 'number') {
                return ascending ? numA - numB : numB - numA;
            }

            return ascending 
                ? cellA.localeCompare(cellB, 'vi')
                : cellB.localeCompare(cellA, 'vi');
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    /**
     * Export table to CSV
     */
    window.exportToCSV = function() {
        const table = document.querySelector('table');
        if (!table) return;

        let csv = [];
        table.querySelectorAll('tr').forEach(row => {
            const cells = [];
            row.querySelectorAll('th, td').forEach(cell => {
                cells.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
            });
            csv.push(cells.join(','));
        });

        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bao_cao_${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    };

    /**
     * Tooltip cho badge nghi vấn
     */
    document.querySelectorAll('.badge[data-tooltip]').forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip-box';
            tooltip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);

            const rect = this.getBoundingClientRect();
            tooltip.style.position = 'fixed';
            tooltip.style.top = (rect.bottom + 10) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
        });

        badge.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.tooltip-box');
            if (tooltip) tooltip.remove();
        });
    });
});