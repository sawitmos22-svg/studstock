<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
            <section id="issue" class="panel card bg-base-100 shadow-sm issue-panel">
                <div class="panel-title">
                    <div>
                        <h2>สแกน QR Code / ค้นหาสินค้า</h2>
                        <p>ใช้กล้องมือถือหรือพิมพ์ SKU เพื่อเลือกรายการ</p>
                    </div>
                    <span class="badge badge-primary badge-outline"><?= $stockStaff ? 'STOCK IN / OUT' : 'QUICK ISSUE' ?></span>
                </div>

                <div class="scan-grid">
                    <div class="scan-box card bg-base-200/50">
                        <div class="scan-icon">▦</div>
                        <h3>สแกน QR Code</h3>
                        <p class="muted">อนุญาตให้เว็บไซต์ใช้กล้องเพื่อสแกนฉลากสินค้า</p>
                        <button class="btn btn-primary" id="scanBtn" type="button">เปิดกล้องสแกน</button>
                        <button class="btn btn-outline" id="stopScanBtn" type="button" hidden>หยุดสแกน</button>
                        <div id="reader" class="reader"></div>
                        <div id="scanMsg" class="small-note" role="status" aria-live="polite"></div>
                    </div>

                    <div class="search-box">
                        <label>ค้นหาด้วย SKU / ขนาด / เกรด</label>
                        <input class="input input-bordered" id="search" placeholder="เช่น SB-M12x80-A4 หรือ M12">
                        <div class="product-results" id="productResults"></div>
                    </div>
                </div>

                <form id="txnForm" class="txn-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="product_id" id="product_id">
                    <div class="selected-product" id="selectedProduct">
                        <span class="empty-state">เลือกสินค้าจากผลการค้นหาหรือสแกน QR</span>
                    </div>

                    <div class="form-grid">
                        <div>
                            <label>ประเภท</label>
                            <select class="select select-bordered" name="type" id="type">
                                <option value="OUT">เบิกออก</option>
                                <?php if ($manager || $stockStaff): ?>
                                    <option value="IN">รับเข้า</option>
                                <?php endif; ?>
                                <?php if ($manager || $stockStaff): ?>
                                    <option value="ADJUST">ปรับยอดคงเหลือ</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label id="qtyLabel">จำนวน (ชิ้น)</label>
                            <input class="input input-bordered" name="qty" type="number" id="qty" min="1" value="1" required>
                        </div>
                        <div>
                            <label>เลขที่ใบงาน / โครงการ</label>
                            <input class="input input-bordered" name="job_ref" placeholder="เช่น WO-2026-001">
                        </div>
                        <div>
                            <label>หมายเหตุ</label>
                            <input class="input input-bordered" name="note" placeholder="ระบุเพิ่มเติม (ถ้ามี)">
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">ยืนยัน<?= $manager || $stockStaff ? 'รายการ' : 'การเบิกสินค้า' ?> →</button>
                    <div id="txnMsg" role="status"></div>
                </form>
            </section>

            