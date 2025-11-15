<div class="container mt-4">
    <h2 class="text-center mb-4 text-primary">BÁO CÁO KIỂM SOÁT DOANH SỐ</h2>

    <!-- Form chọn ngày -->
    <form method="get" class="row g-3 mb-4 align-items-end">
        <div class="col-md-5">
            <label class="form-label fw-bold">Từ ngày</label>
            <input type="text" name="tu_ngay" class="form-control flatpickr" value="<?= $tu_ngay ?>" required>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-bold">Đến ngày</label>
            <input type="text" name="den_ngay" class="form-control flatpickr" value="<?= $den_ngay ?>" required>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Xem</button>
        </div>
    </form>

    <!-- Tổng quan -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="info-box">
                <small>Số ngày</small>
                <h5><?= $so_ngay ?></h5>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box">
                <small>Tỉ lệ nghi vấn</small>
                <h5 class="text-success"><?= number_format($ty_le_nghi_van * 100, 2) ?>%</h5>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box">
                <small>Kết quả chung</small>
                <h5 class="text-warning"><?= number_format($ket_qua_chung * 100, 2) ?>%</h5>
            </div>
        </div>
    </div>

    <!-- Bảng -->
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>MA_NHAN_VIEN</th>
                    <th>TÊN_NHAN_VIEN</th>
                    <th>NGÀY_VÀO_CÔNG_TY</th>
                    <th>TỈNH</th>
                    <th>GS</th>
                    <th>DS_TIM_KIEM</th>
                    <th>DS_TIEN_DO</th>
                    <th>% TIẾN ĐỘ T9</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report as $r): ?>
                <tr class="<?= $r['ty_le'] > $ty_le_nghi_van ? 'highlight' : '' ?>">
                    <td><?= $r['ma_nv'] ?></td>
                    <td><?= $r['ten_nv'] ?></td>
                    <td><?= $r['ngay_vao_cong_ty'] ? date('d/m/Y', strtotime($r['ngay_vao_cong_ty'])) : '-' ?></td>
                    <td><?= $r['tinh'] ?></td>
                    <td><?= $r['gs'] ?></td>
                    <td class="text-end"><?= number_format($r['ds_tim_kiem']) ?></td>
                    <td class="text-end"><?= number_format($r['ds_tien_do']) ?></td>
                    <td class="text-end"><?= number_format($r['ty_le'] * 100, 2) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <a href="upload.php" class="btn btn-success mt-3">Upload File Mới</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr(".flatpickr", { dateFormat: "Y-m-d", locale: "vi" });
</script>