<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
            <section id="home" class="page-head">
                <div>
                    <p class="eyebrow">STOCK OVERVIEW</p>
                    <h1><?= $manager ? 'ภาพรวมคลังสินค้า' : ($stockStaff ? 'จัดการรับเข้า / เบิกออกสินค้า' : 'เบิกสินค้า Stud Bolt') ?></h1>
                    <p class="muted"><?= $manager ? 'ตรวจสอบสต๊อกและกิจกรรมล่าสุดได้จากหน้าเดียว' : ($stockStaff ? 'ตรวจสอบสินค้าและบันทึกรับเข้า / เบิกออกได้ โดยไม่สามารถลบหรือแก้ข้อมูลสินค้า' : 'สแกน QR หรือค้นหารหัสสินค้าเพื่อทำรายการเบิก') ?></p>
                </div>
                <div class="date-chip badge badge-outline"><?= date('d/m/Y') ?> &nbsp; <?= date('H:i') ?></div>
            </section>

            <?php if ($manager): ?>
                <section class="stats stock-stats">
                    <div class="stat card bg-base-100 shadow-sm">
                        <div class="stat-icon blue">▦</div>
                        <span>SKU ทั้งหมด</span>
                        <strong><?= number_format($totals['skus']) ?></strong>
                        <small>รหัสสินค้า</small>
                    </div>
                    <div class="stat card bg-base-100 shadow-sm">
                        <div class="stat-icon cyan">▤</div>
                        <span>จำนวนคงเหลือรวม</span>
                        <strong><?= number_format($totals['pieces']) ?></strong>
                        <small>ชิ้น</small>
                    </div>
                    <div class="stat card bg-base-100 shadow-sm">
                        <div class="stat-icon orange">⚠</div>
                        <span>สินค้าใกล้หมด</span>
                        <strong><?= number_format($totals['low_count']) ?></strong>
                        <small>SKU ถึงจุดขั้นต่ำ</small>
                    </div>
                    <div class="stat card bg-base-100 shadow-sm">
                        <div class="stat-icon green">⇄</div>
                        <span>รายการทั้งหมด</span>
                        <strong><?= number_format($transactionTotal) ?></strong>
                        <small>แบ่งหน้า หน้าละ 10 รายการ</small>
                    </div>
                </section>
            <?php endif; ?>
