<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php if ($manager): ?>
        <dialog id="clearStockDialog" class="modal">
            <form id="clearStockForm" class="modal-box">
                <div class="panel-title">
                    <div>
                        <h2>เคลียร์สต๊อกทั้งหมด</h2>
                        <p>ยืนยันการปรับยอดคงเหลือสินค้าเป็น 0</p>
                    </div>
                    <button class="btn btn-ghost btn-sm" id="closeClearStockDialog" type="button" aria-label="ปิดหน้าต่าง">✕</button>
                </div>

                <div class="stock-clear-warning">
                    <strong>การดำเนินการนี้มีผลกับยอดคงเหลือของสินค้าทุก SKU</strong>
                    <p>ระบบจะไม่ลบรายการสินค้าและไม่ลบประวัติเดิม โดยจะบันทึกประวัติปรับยอดสำหรับสินค้าที่มียอดคงเหลือ</p>
                </div>
                <label class="stock-clear-confirm">
                    <input id="confirmClearStock" type="checkbox" required>
                    <span>ฉันยืนยันให้ปรับยอดคงเหลือสินค้าทั้งหมดเป็น 0</span>
                </label>
                <button class="btn btn-danger full" id="confirmClearStockBtn" type="submit" disabled>ยืนยันเคลียร์สต๊อก</button>
                <div id="clearStockMsg" role="status" aria-live="polite"></div>
            </form>
        </dialog>

        <dialog id="importDialog" class="modal">
            <form id="importForm" class="modal-box">
                <div class="panel-title">
                    <div>
                        <h2>นำเข้าสินค้าจาก CSV / Excel</h2>
                        <p>รองรับไฟล์ .csv และ .xlsx สูงสุด 5 MB (ไม่เกิน 10,000 รายการ)</p>
                    </div>
                    <button class="btn btn-ghost btn-sm" id="closeImportDialog" type="button" aria-label="ปิดหน้าต่าง">✕</button>
                </div>

                <p class="muted">ใช้แถวแรกเป็นหัวตาราง และดาวน์โหลดเทมเพลตก่อนกรอกข้อมูลได้</p>
                <p class="muted">คอลัมน์ที่ต้องมี: sku, size_mm, length_mm, grade</p>
                <p class="muted">คอลัมน์อื่นเลือกกรอกได้: diameter_mm, standard_name, description, qty, min_qty, location_code</p>
                <label for="importFile">เลือกไฟล์</label>
                <input class="file-input file-input-bordered" id="importFile" name="file" type="file" accept=".csv,.xlsx" required>
                <button class="btn btn-primary full" type="submit">ตรวจสอบและนำเข้า</button>
                <div id="importMsg" role="status"></div>
            </form>
        </dialog>

        <dialog id="productDialog" class="modal">
            <form id="productForm" class="modal-box" method="dialog">
                <div class="panel-title">
                    <h2>เพิ่มสินค้า Stud Bolt</h2>
                    <button class="btn btn-ghost btn-sm" id="closeProductDialog" type="button" aria-label="ปิดหน้าต่าง">✕</button>
                </div>

                <div class="form-grid">
                    <div>
                        <label>SKU *</label>
                        <input class="input input-bordered" name="sku" required placeholder="SB-M12x80-A4">
                    </div>
                    <div>
                        <label>ขนาด *</label>
                        <input class="input input-bordered" name="size_mm" required placeholder="M12">
                    </div>
                    <div>
                        <label>เส้นผ่านศูนย์กลาง (mm)</label>
                        <input class="input input-bordered" name="diameter_mm" type="number" step="0.01">
                    </div>
                    <div>
                        <label>ความยาว (mm) *</label>
                        <input class="input input-bordered" name="length_mm" type="number" step="0.01" required>
                    </div>
                    <div>
                        <label>เกรด *</label>
                        <input class="input input-bordered" name="grade" required placeholder="A4-70">
                    </div>
                    <div>
                        <label>มาตรฐาน</label>
                        <input class="input input-bordered" name="standard_name" placeholder="DIN 976">
                    </div>
                    <div>
                        <label>จำนวนตั้งต้น</label>
                        <input class="input input-bordered" name="qty" type="number" min="0" value="0">
                    </div>
                    <div>
                        <label>จำนวนขั้นต่ำ</label>
                        <input class="input input-bordered" name="min_qty" type="number" min="0" value="0">
                    </div>
                    <div>
                        <label>ตำแหน่งจัดเก็บ</label>
                        <input class="input input-bordered" name="location_code" placeholder="A-01">
                    </div>
                    <div>
                        <label>รายละเอียด</label>
                        <input class="input input-bordered" name="description" placeholder="รายละเอียดเพิ่มเติม">
                    </div>
                </div>

                <button class="btn btn-primary full" type="submit">บันทึกสินค้า</button>
                <div id="productMsg"></div>
            </form>
        </dialog>

        <dialog id="qrLabelDialog" class="modal">
            <div class="modal-box qr-dialog-box">
                <div class="panel-title qr-dialog-heading">
                    <div>
                        <h2>ฉลาก QR สินค้า</h2>
                        <p>พิมพ์แล้วนำไปติดที่กล่องสินค้า</p>
                    </div>
                    <button class="btn btn-ghost btn-sm" id="closeQrLabelDialog" type="button" aria-label="ปิดหน้าต่าง">✕</button>
                </div>

                <div class="qr-label" id="qrLabel">
                    <div class="qr-label-info">
                        <strong id="qrLabelSku"></strong>
                        <span id="qrLabelDescription"></span>
                        <span id="qrLabelLocation"></span>
                    </div>
                    <div class="qr-code" id="qrCode" aria-label="QR Code ของ SKU"></div>
                </div>
                <p class="muted qr-dialog-note">QR นี้บันทึกเฉพาะ SKU เพื่อใช้ค้นหาสินค้าในระบบ</p>
                <p id="qrLabelMsg" role="status" aria-live="polite"></p>
                <div class="qr-dialog-actions">
                    <button class="btn btn-primary full" id="printQrLabelBtn" type="button">พิมพ์ฉลาก</button>
                </div>
            </div>
        </dialog>
    <?php endif; ?>
