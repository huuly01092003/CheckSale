/**
 * FILE: assets/js/upload.js
 * Xử lý upload file & validation
 */

document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    const submitBtn = document.getElementById('submitBtn');
    const filePreview = document.getElementById('filePreview');
    const progressBar = document.getElementById('progressBar');

    const maxFileSize = 500 * 1024 * 1024; // 500MB
    const allowedExtensions = ['csv', 'xlsx', 'xls'];

    // ========== FILE INPUT CHANGE ==========
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }

    /**
     * Xử lý chọn file
     */
    function handleFileSelect(e) {
        const file = e.target.files[0];
        clearFileErrors();

        if (!file) {
            clearFilePreview();
            return;
        }

        // Validate file
        if (!validateFile(file)) {
            fileInput.value = '';
            return;
        }

        // Hiển thị preview
        showFilePreview(file);
    }

    /**
     * Validate file
     */
    function validateFile(file) {
        // Check size
        if (file.size > maxFileSize) {
            const sizeMB = (maxFileSize / 1024 / 1024).toFixed(0);
            showFileError(`File quá lớn. Tối đa: ${sizeMB}MB (File của bạn: ${(file.size / 1024 / 1024).toFixed(2)}MB)`);
            return false;
        }

        // Check extension
        const ext = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(ext)) {
            showFileError(`Định dạng không hỗ trợ. Chỉ chấp nhận: ${allowedExtensions.join(', ')}`);
            return false;
        }

        // Check filename
        if (!isValidFileName(file.name)) {
            showFileError('Tên file không hợp lệ. Vui lòng kiểm tra tên file');
            return false;
        }

        return true;
    }

    /**
     * Kiểm tra tên file
     */
    function isValidFileName(name) {
        const validPatterns = [
            /DSach_NV_C/i,
            /DSNV/i,
            /nhanvien/i,
            /1\.3/i,
            /báo\s*cáo/i,
            /donhang/i,
            /order/i
        ];

        return validPatterns.some(pattern => pattern.test(name));
    }

    /**
     * Hiển thị preview file
     */
    function showFilePreview(file) {
        if (!filePreview) return;

        const fileSize = formatFileSize(file.size);
        const fileType = getFileTypeIcon(file.name);

        let html = `
            <div class="file-item">
                <div class="file-item-info">
                    <div class="file-item-icon">${fileType}</div>
                    <div>
                        <div class="file-item-name">${escapeHtml(file.name)}</div>
                        <div class="file-item-size">${fileSize}</div>
                    </div>
                </div>
                <span class="badge bg-success">✓ OK</span>
            </div>
        `;

        filePreview.innerHTML = html;
        filePreview.classList.add('active');

        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }

    /**
     * Xóa preview file
     */
    function clearFilePreview() {
        if (filePreview) {
            filePreview.classList.remove('active');
            filePreview.innerHTML = '';
        }

        if (submitBtn) {
            submitBtn.disabled = true;
        }
    }

    /**
     * Format kích thước file
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Lấy icon loại file
     */
    function getFileTypeIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();

        switch (ext) {
            case 'csv':
                return '📄';
            case 'xlsx':
            case 'xls':
                return '📊';
            default:
                return '📁';
        }
    }

    /**
     * Hiển thị lỗi file
     */
    function showFileError(message) {
        clearFileErrors();
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger alert-dismissible fade show';
        errorDiv.innerHTML = `
            ⚠️ ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const formGroup = fileInput?.parentElement?.parentElement;
        if (formGroup) {
            formGroup.insertBefore(errorDiv, formGroup.firstChild);
        }

        clearFilePreview();
    }

    /**
     * Xóa lỗi file
     */
    function clearFileErrors() {
        document.querySelectorAll('.alert.alert-danger').forEach(alert => {
            if (alert.textContent.includes('File quá lớn') ||
                alert.textContent.includes('Định dạng') ||
                alert.textContent.includes('Tên file')) {
                alert.remove();
            }
        });
    }

    /**
     * Submit form
     */
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            if (!fileInput || !fileInput.files[0]) {
                e.preventDefault();
                showFileError('Vui lòng chọn file');
                return false;
            }

            const file = fileInput.files[0];
            if (!validateFile(file)) {
                e.preventDefault();
                return false;
            }

            // Disable button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
            }

            return true;
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Drag & drop
     */
    if (fileInput) {
        const dropZone = fileInput.parentElement;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.style.backgroundColor = 'rgba(102, 126, 234, 0.1)';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.style.backgroundColor = '';
            });
        });

        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            fileInput.files = files;

            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        });
    }
});