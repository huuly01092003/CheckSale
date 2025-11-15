<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">UPLOAD 2 FILE EXCEL</h4>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="mt-3">
                <div class="mb-3">
                    <label class="form-label fw-bold">Chọn file Excel</label>
                    <input type="file" name="file" accept=".xlsx" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100">Upload & Import</button>
            </form>
            <hr>
            <p class="text-muted small">
                <b>Thứ tự upload:</b><br>
                1. <code>DSach_NV_Công.xlsx</code><br>
                2. <code>1.3 Báo cáo chi tiết đơn hàng_*.xlsx</code>
            </p>
            <a href="index.php" class="btn btn-secondary w-100 mt-3">Quay lại Báo Cáo</a>
        </div>
    </div>
</div>