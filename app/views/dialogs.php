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
                        <h2>นำเข้า / ซิงก์รายการสินค้า Excel</h2>
                        <p>รองรับไฟล์ .csv และ .xlsx สูงสุด 5 MB (ไม่เกิน 10,000 รายการ)</p>
                    </div>
                    <button class="btn btn-ghost btn-sm" id="closeImportDialog" type="button" aria-label="ปิดหน้าต่าง">✕</button>
                </div>

                <p class="muted">รองรับไฟล์ Master Awarded items (ชีตแรก) หรือเทมเพลต sku, size_mm, diameter_mm, length_mm, grade, standard_name, description, qty, min_qty, location_code</p>
                <p class="muted">สินค้าใหม่เริ่มที่คงเหลือ 0 และจุดเตือน 0 เพื่อไม่เดาจำนวนสต๊อกจากยอด PO; ตั้งยอดและจุดเตือนหลังตรวจนับได้ในตาราง · แถวที่ไม่มี Part Number/JDE จะแสดงในตาราง Excel แต่ยังไม่สร้าง SKU</p>
                <label class="stock-clear-confirm">
                    <input type="checkbox" name="replace_catalog" value="1" checked>
                    <span>ซิงก์กับ Excel: ซ่อนสินค้าเดิมที่ไม่อยู่ในไฟล์ (ไม่ลบยอดหรือประวัติ)</span>
                </label>
                <p class="muted">ถ้า SKU เดิมมีอยู่ ระบบจะอัปเดตรายละเอียดแต่คงยอดสต๊อกและจุดเตือนไว้</p>
                <label for="importFile">เลือกไฟล์</label>
                <input class="file-input file-input-bordered" id="importFile" name="file" type="file" accept=".csv,.xlsx" required>
                <button class="btn btn-primary full" type="submit">ตรวจสอบและนำเข้า</button>
                <div id="importMsg" role="status"></div>
            </form>
        </dialog>

        <dialog id="productDialog" class="modal">
            <form id="productForm" class="modal-box" method="dialog">
                <div class="panel-title">
                    <div>
                        <h2>เพิ่มสินค้า Stud Bolt</h2>
                        <p>ข้อมูล Master จะลงคอลัมน์ Lot Name, AWARDED Line Item Name, Part Number, SIZE, Thread และ Stud Material</p>
                    </div>
                    <button class="btn btn-ghost btn-sm" id="closeProductDialog" type="button" aria-label="ปิดหน้าต่าง">✕</button>
                </div>

                <div class="form-grid">
                    <p class="product-form-section">ข้อมูลสินค้า — อิงตามคอลัมน์ในตาราง Master</p>
                    <div>
                        <label>Lot Name</label>
                        <input class="input input-bordered" name="location_code" placeholder="A-01">
                    </div>
                    <div>
                        <label>AWARDED Line Item Name</label>
                        <input class="input input-bordered" name="description" placeholder="รายละเอียดสินค้า">
                    </div>
                    <div>
                        <label>Part Number (SKU) *</label>
                        <input class="input input-bordered" name="sku" required placeholder="1000020294">
                    </div>
                    <div>
                        <label>SIZE *</label>
                        <input class="input input-bordered" name="size_mm" required placeholder="DIA 3/4 IN X 5-1/2 IN LG">
                    </div>
                    <div>
                        <label>Thread</label>
                        <input class="input input-bordered" name="standard_name" placeholder="DIN 976">
                    </div>
                    <div>
                        <label>Stud Material *</label>
                        <input class="input input-bordered" name="grade" required placeholder="A4-70">
                    </div>
                    <p class="product-form-section">ข้อมูลสต๊อกระบบ — แยกจากคอลัมน์ยอด PO ใน Excel</p>
                    <div>
                        <label>เส้นผ่านศูนย์กลาง (mm)</label>
                        <input class="input input-bordered" name="diameter_mm" type="number" step="0.01">
                    </div>
                    <div>
                        <label>ความยาว (mm) *</label>
                        <input class="input input-bordered" name="length_mm" type="number" step="0.01" required>
                    </div>
                    <div>
                        <label>จำนวนตั้งต้นในสต๊อกระบบ (ชิ้น)</label>
                        <input class="input input-bordered" name="qty" type="number" min="0" value="0">
                    </div>
                    <div>
                        <label>จุดเตือนสต๊อกขั้นต่ำ (ชิ้น)</label>
                        <input class="input input-bordered" name="min_qty" type="number" min="0" value="0">
                    </div>
                </div>
                <p class="muted">จำนวนตั้งต้นคือยอดตรวจนับจริงในสต๊อกระบบ ไม่ใช่ Forecast Usage หรือ Total Quantity received from PO placed</p>

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

                <div class="qr-label-list" id="qrLabelList">
                </div>
                <p class="muted qr-dialog-note" id="qrLabelNote">QR นี้บันทึก SKU เพื่อใช้ค้นหาสินค้าในระบบ</p>
                <p id="qrLabelMsg" role="status" aria-live="polite"></p>
                <div class="qr-dialog-actions">
                    <button class="btn btn-primary full" id="printQrLabelBtn" type="button">พิมพ์ฉลาก</button>
                </div>
            </div>
        </dialog>
    <?php endif; ?>
