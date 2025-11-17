/**
 * FILE: assets/js/kpi.js
 * Xử lý logic KPI Report
 */

document.addEventListener('DOMContentLoaded', function() {
    // Validation ngày
    const tuNgayInput = document.getElementById('tuNgay');
    const denNgayInput = document.getElementById('denNgay');
    const thangSelect = document.getElementById('thang');

    if (tuNgayInput) {
        tuNgayInput.addEventListener('change', validateDateRange);
        tuNgayInput.addEventListener('blur', validateDateInput);
    }

    if (denNgayInput) {
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
        const currentYear = new Date().getFullYear();
        const minYear = 2020;

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

        if (tuDate > denDate) {
            showError(denNgayInput, 'Đến ngày phải >= Từ ngày');
            return false;
        }

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
     * Form submit validation
     */
    const filterForm = document.getElementById('kpiFilterForm');
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
});

/**
 * Hiển thị chi tiết nghi vấn trong modal
 */
function showDetails(jsonData) {
    try {
        const data = JSON.parse(jsonData);
        
        document.getElementById('modalEmpName').textContent = data.ten_nv + ' (' + data.ma_nv + ')';
        
        let html = `
            <div class="suspicion-detail">
                <h6 class="mb-3">
                    <i class="fas fa-user-circle"></i> Thông Tin Nhân Viên
                </h6>
                <div class="detail-metric">
                    <span class="detail-metric-label">Mã NV:</span>
                    <span class="detail-metric-value">${escapeHtml(data.ma_nv)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tên:</span>
                    <span class="detail-metric-value">${escapeHtml(data.ten_nv)}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tỉnh:</span>
                    <span class="detail-metric-value">${escapeHtml(data.tinh || 'N/A')}</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">GS:</span>
                    <span class="detail-metric-value">${escapeHtml(data.gs || 'N/A')}</span>
                </div>
            </div>

            <hr>

            <div class="suspicion-detail">
                <h6 class="mb-3">
                    <i class="fas fa-chart-bar"></i> Chỉ Số KPI
                </h6>
                <div class="detail-metric">
                    <span class="detail-metric-label">Tổng Đơn Hàng:</span>
                    <span class="detail-metric-value">${data.total_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">TBD/Ngày:</span>
                    <span class="detail-metric-value">${data.avg_daily_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Max/Ngày:</span>
                    <span class="detail-metric-value" style="color: #28a745;">${data.max_day_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Min/Ngày:</span>
                    <span class="detail-metric-value">${data.min_day_orders} đơn</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Ngày Hoạt Động:</span>
                    <span class="detail-metric-value">${data.working_days} ngày</span>
                </div>
                <div class="detail-metric">
                    <span class="detail-metric-label">Xu Hướng:</span>
                    <span class="detail-metric-value">
                        ${data.trend === 'increasing' ? '📈 Tăng' : 
                          data.trend === 'decreasing' ? '📉 Giảm' : '→ Ổn Định'}
                    </span>
                </div>
            </div>

            <hr>

            <div class="suspicion-detail">
                <h6 class="mb-3">
                    <i class="fas fa-exclamation-circle"></i> Lý Do Nghi Vấn
                    <span class="badge" style="background: ${data.suspicion_level === 'danger' ? '#dc3545' : 
                        data.suspicion_level === 'warning' ? '#ffc107' : '#28a745'}; margin-left: 10px;">
                        Điểm: ${data.suspicion_score}
                    </span>
                </h6>
        `;
        
        if (data.suspicion_reasons && data.suspicion_reasons.length > 0) {
            data.suspicion_reasons.forEach(reason => {
                html += `<div class="suspicion-reason">${reason}</div>`;
            });
        } else {
            html += `<div class="suspicion-reason">Không có nghi vấn đặc biệt</div>`;
        }
        
        html += `</div>`;

        document.getElementById('modalContent').innerHTML = html;
    } catch (e) {
        console.error('Error parsing data:', e);
        document.getElementById('modalContent').innerHTML = '<p class="text-danger">Lỗi tải dữ liệu</p>';
    }
}

/**
 * Export KPI Report to CSV
 */
function exportKPIToCSV() {
    const table = document.querySelector('.kpi-table');
    if (!table) return;

    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        const cells = [];
        row.querySelectorAll('th, td').forEach(cell => {
            const text = cell.textContent.trim()
                .replace(/\n/g, ' ')
                .replace(/\s+/g, ' ');
            cells.push('"' + text.replace(/"/g, '""') + '"');
        });
        csv.push(cells.join(','));
    });

    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `KPI_Report_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}