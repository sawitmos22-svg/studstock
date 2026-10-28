<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php if ($canViewStock): ?>
                <section id="inventory" class="panel card bg-base-100 shadow-sm">
                    <div class="panel-title">
                        <div>
                            <h2>รายการสินค้า Stud Bolt</h2>
                            <p><?= $manager ? 'ค้นหา เพิ่มสินค้า และแก้ไขข้อมูลสต๊อก' : 'ดูข้อมูลสินค้าเพื่อรับเข้าและเบิกสินค้า' ?></p>
                        </div>
                        <?php if ($manager): ?>
                            <div class="inventory-header-actions">
                                <button class="btn btn-error" id="clearStockBtn" type="button">เคลียร์สต๊อกทั้งหมด</button>
                                <button class="btn btn-primary" id="newProductBtn">+ เพิ่มสินค้า</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-tools">
                        <form class="inventory-search" method="get" action="index.php#inventory">
                            <input class="input input-bordered" name="product_search" value="<?= h($productSearch) ?>" placeholder="ค้นหา SKU / ขนาด / เกรด..." aria-label="ค้นหาสินค้า">
                            <button class="btn btn-outline btn-sm" type="submit">ค้นหา</button>
                            <?php if ($productSearch !== ''): ?>
                                <a class="btn btn-ghost btn-sm" href="index.php#inventory">ล้าง</a>
                            <?php endif; ?>
                        </form>
                        <?php if ($manager): ?>
                            <div class="table-actions">
                                <a class="btn btn-outline" href="?export=template">⇩ ดาวน์โหลดเทมเพลต</a>
                                <button class="btn btn-outline" id="importProductsBtn" type="button">นำเข้า CSV / Excel</button>
                                <a class="btn btn-outline" href="?export=products">ส่งออกสินค้าทั้งหมด CSV</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-wrap">
                        <table class="table table-zebra" id="stockTable">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>ขนาด</th>
                                    <th>ยาว (mm)</th>
                                    <th>เกรด</th>
                                    <th>มาตรฐาน</th>
                                    <th>คงเหลือ</th>
                                    <th>ขั้นต่ำ</th>
                                    <th>ตำแหน่ง</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventoryProducts as $p): ?>
                                    <tr data-search="<?= h(strtolower($p['sku'] . ' ' . $p['size_mm'] . ' ' . $p['grade'] . ' ' . $p['standard_name'])) ?>">
                                        <td>
                                            <b><?= h($p['sku']) ?></b>
                                            <small class="subcell"><?= h($p['description'] ?: ($p['size_mm'] . ' × ' . $p['length_mm'] . ' mm')) ?></small>
                                            <?php if ($manager): ?>
                                                <button class="qr-link generate-qr-btn" type="button" data-qr-id="<?= (int) $p['id'] ?>">▦ ฉลาก QR</button>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($p['size_mm']) ?></td>
                                        <td><?= h($p['length_mm']) ?></td>
                                        <td><?= h($p['grade']) ?></td>
                                        <td><?= h($p['standard_name']) ?></td>
                                        <td><b><?= number_format($p['qty']) ?></b></td>
                                        <td><?= number_format($p['min_qty']) ?></td>
                                        <td><?= h($p['location_code']) ?></td>
                                        <td>
                                            <?php if ($p['qty'] <= $p['min_qty']): ?>
                                                <span class="badge badge-warning badge-outline">ใกล้หมด</span>
                                            <?php else: ?>
                                                <span class="badge badge-success badge-outline">ปกติ</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$inventoryProducts): ?>
                                    <tr>
                                        <td colspan="9" class="muted">ไม่พบสินค้า</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav class="log-pagination inventory-pagination" aria-label="หน้ารายการสินค้า">
                        <span>
                            <?php if ($inventoryTotal > 0): ?>
                                แสดง <?= number_format($inventoryOffset + 1) ?>–<?= number_format(min($inventoryOffset + 10, $inventoryTotal)) ?>
                                จาก <?= number_format($inventoryTotal) ?> รายการ ·
                            <?php else: ?>
                                0 รายการ ·
                            <?php endif; ?>
                            หน้า <?= number_format($inventoryPage) ?> จาก <?= number_format($inventoryPages) ?>
                        </span>
                        <div>
                            <?php if ($inventoryPage > 1): ?>
                                <a class="btn btn-outline btn-sm" href="index.php?product_search=<?= rawurlencode($productSearch) ?>&amp;product_page=<?= $inventoryPage - 1 ?>#inventory">‹ ก่อนหน้า</a>
                            <?php endif; ?>
                            <?php if ($inventoryPage < $inventoryPages): ?>
                                <a class="btn btn-outline btn-sm" href="index.php?product_search=<?= rawurlencode($productSearch) ?>&amp;product_page=<?= $inventoryPage + 1 ?>#inventory">ถัดไป ›</a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </section>

                <aside id="stockAside" class="stock-aside">
                <section id="lowStock" class="panel card bg-base-100 shadow-sm">
                    <div class="panel-title">
                        <div>
                            <h2>แจ้งเตือนสินค้าใกล้หมด</h2>
                            <p>สินค้าที่คงเหลือเท่ากับหรือต่ำกว่าค่าขั้นต่ำ</p>
                        </div>
                        <span class="badge badge-warning badge-outline"><?= count($low) ?> รายการ</span>
                    </div>

                    <div class="low-list">
                        <?php if (!$low): ?>
                            <p class="muted">ไม่มีสินค้าใกล้หมดในขณะนี้</p>
                        <?php else: ?>
                            <?php foreach ($low as $p): ?>
                                <div class="low-item">
                                    <span class="warning-icon">!</span>
                                    <div>
                                        <b><?= h($p['sku']) ?></b>
                                        <small><?= h($p['size_mm']) ?> × <?= h($p['length_mm']) ?> mm · <?= h($p['grade']) ?></small>
                                    </div>
                                    <strong><?= number_format($p['qty']) ?> ชิ้น</strong>
                                    <span class="badge badge-warning badge-outline">ขั้นต่ำ <?= number_format($p['min_qty']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($manager): ?>
                <section id="qrShelfCard" class="qr-shelf-card card bg-base-100 shadow-sm">
                    <div class="qr-shelf-heading">
                        <div class="qr-shelf-mark">▦</div>
                        <div>
                            <h2>ตัวอย่างป้าย QR Code สินค้า</h2>
                            <p>สแกนเพื่อเปิดข้อมูลสินค้าในคลัง</p>
                        </div>
                    </div>
                    <?php if ($products): ?>
                        <?php $sample = $products[0]; ?>
                        <div class="qr-shelf-label">
                            <div class="qr-shelf-product">
                                <b id="qrShelfSku"><?= h($sample['sku']) ?></b>
                                <span><?= h($sample['size_mm']) ?> × <?= h($sample['length_mm']) ?> mm</span>
                                <span>เกรด <?= h($sample['grade']) ?><?= $sample['standard_name'] ? ' · ' . h($sample['standard_name']) : '' ?></span>
                                <small>ตำแหน่ง <?= h($sample['location_code'] ?: '-') ?></small>
                            </div>
                            <div id="shelfQrCode" class="shelf-qr-code" aria-label="QR Code ตัวอย่าง"></div>
                        </div>
                        <button class="btn btn-outline full generate-qr-btn" type="button" data-qr-id="<?= (int) $sample['id'] ?>">สร้าง / พิมพ์ฉลาก</button>
                    <?php else: ?>
                        <p class="muted">เพิ่มสินค้าเพื่อสร้างป้าย QR Code</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
                </aside>
            <?php endif; ?>
