<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
            <section class="page-head">
                <div>
                    <p class="eyebrow">USER ADMINISTRATION</p>
                    <h1>บัญชีผู้ใช้และประวัติการเข้าใช้งาน</h1>
                    <p class="muted">จัดการบัญชี ตรวจสอบกิจกรรม และอนุมัติคำขอทำรายการสต๊อกจากหน้าเฉพาะสำหรับผู้จัดการ</p>
                </div>
                <a class="btn btn-outline" href="index.php">กลับหน้าหลัก</a>
            </section>

            <section class="panel card bg-base-100 shadow-sm">
                <div class="panel-title">
                    <div>
                        <h2>ส่งออกข้อมูลที่เลือก</h2>
                        <p>เลือกชุดข้อมูลที่ต้องการ แล้วดาวน์โหลดเป็น ZIP ซึ่งภายในแยกเป็นไฟล์ CSV</p>
                    </div>
                </div>
                <form id="selectedExportForm" action="index.php" method="get">
                    <input type="hidden" name="export" value="selected">
                    <div class="export-datasets">
                        <label><input class="select-export-dataset" type="checkbox" name="datasets[]" value="products"> สินค้าทั้งหมด</label>
                        <label><input class="select-export-dataset" type="checkbox" name="datasets[]" value="accounts"> บัญชีผู้ใช้</label>
                        <label><input class="select-export-dataset" type="checkbox" name="datasets[]" value="activity"> ประวัติการเข้าใช้งาน</label>
                        <label><input class="select-export-dataset" type="checkbox" name="datasets[]" value="transactions"> ประวัติรับเข้า-เบิกออก</label>
                        <label><input id="selectAllExportDatasets" type="checkbox"> เลือกทั้งหมด</label>
                    </div>
                    <button id="exportSelectedDatasetsBtn" class="btn btn-primary" type="submit" disabled>ดาวน์โหลดข้อมูลที่เลือก</button>
                </form>
            </section>

            <?php require __DIR__ . '/dashboard/approvals.php'; ?>
            <?php require __DIR__ . '/dashboard/accounts.php'; ?>
            <?php require __DIR__ . '/dashboard/activity.php'; ?>

            <dialog id="changeUserPasswordDialog" class="modal">
                <form id="changeUserPasswordForm" class="modal-box">
                    <div class="panel-title">
                        <div>
                            <h2>เปลี่ยนรหัสผ่าน</h2>
                            <p id="changePasswordUserName"></p>
                        </div>
                        <button class="btn btn-ghost btn-sm" id="closeChangePasswordDialog" type="button" aria-label="ปิดหน้าต่าง">✕</button>
                    </div>
                    <input type="hidden" id="changePasswordUserId">
                    <div>
                        <label for="newUserPassword">รหัสผ่านใหม่ (8–72 ไบต์)</label>
                        <div class="password-input-group">
                            <input class="input input-bordered" id="newUserPassword" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                            <button class="btn btn-outline btn-sm toggle-password-visibility-btn" type="button" data-password-target="newUserPassword" aria-label="แสดงรหัสผ่าน" aria-pressed="false">แสดงรหัสผ่าน</button>
                        </div>
                    </div>
                    <div>
                        <label for="confirmUserPassword">ยืนยันรหัสผ่านใหม่</label>
                        <div class="password-input-group">
                            <input class="input input-bordered" id="confirmUserPassword" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                            <button class="btn btn-outline btn-sm toggle-password-visibility-btn" type="button" data-password-target="confirmUserPassword" aria-label="แสดงรหัสผ่าน" aria-pressed="false">แสดงรหัสผ่าน</button>
                        </div>
                    </div>
                    <button class="btn btn-primary full" id="changeUserPasswordSubmit" type="submit">บันทึก</button>
                    <p id="changeUserPasswordMsg" role="status" aria-live="polite"></p>
                </form>
            </dialog>
        </main>
    </div>
