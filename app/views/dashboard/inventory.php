<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php if ($canViewStock): ?>
                <section id="inventory" class="panel card bg-base-100 shadow-sm">
                    <div class="panel-title">
                        <div>
                            <h2>รายการสินค้า Stud Bolt ตาม Excel</h2>
                            <p>ตารางเดียวกับไฟล์ Master Awarded items · ยอดคงเหลือและจุดเตือนมาจากสต๊อกในระบบ ไม่ใช่จำนวน PO</p>
                        </div>
                        <?php if ($manager): ?>
                            <div class="inventory-header-actions">
                                <button class="btn btn-outline" id="printSelectedQrBtn" type="button" disabled>พิมพ์ QR ที่เลือก (0)</button>
                                <button class="btn btn-error" id="clearStockBtn" type="button">ล้างยอดสต๊อกระบบ</button>
                                <button class="btn btn-primary" id="newProductBtn" type="button">+ เพิ่มสินค้าในระบบ</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-tools">
                        <form class="inventory-search" method="get" action="index.php">
                            <input class="input input-bordered" id="inventorySearch" name="product_search" value="<?= h($productSearch) ?>" placeholder="ค้นหา Part Number, JDE, ขนาด หรือรายละเอียด..." aria-label="ค้นหาสินค้า">
                            <button class="btn btn-outline" type="submit">ค้นหา</button>
                        </form>
                        <?php if ($manager): ?>
                            <div class="table-actions">
                                <a class="btn btn-outline" href="?export=awarded_items">⇩ ส่งออกรายการ Excel</a>
                                <a class="btn btn-outline" href="?export=products">⇩ ส่งออกสินค้าสต๊อก</a>
                                <button class="btn btn-outline" id="importProductsBtn" type="button">นำเข้า / ซิงก์รายการ Excel</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-wrap">
                        <table class="table table-zebra stock-products-table awarded-items-table" id="stockTable">
                            <thead>
                                <tr>
                                    <?php if ($manager): ?>
                                        <th><input id="selectAllQrProducts" type="checkbox" aria-label="เลือกสินค้าทั้งหมดในหน้านี้สำหรับพิมพ์ QR"></th>
                                    <?php endif; ?>
                                    <?php foreach ($awardedHeaders as $header): ?>
                                        <th><?= nl2br(h($header)) ?></th>
                                    <?php endforeach; ?>
                                    <th>คงเหลือ</th>
                                    <th>จุดเตือน</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visibleAwardedItems as $entry): ?>
                                    <?php
                                    $item = $entry['item'];
                                    $product = $entry['product'];
                                    $qty = $product ? (int) $product['qty'] : null;
                                    $minQty = $product ? (int) $product['min_qty'] : null;
                                    $canPrintQr = $manager && $product;
                                    ?>
                                    <tr class="stock-product-row" <?= $product ? 'data-product-id="' . (int) $product['id'] . '"' : '' ?> data-search="<?= h(implode(' ', array_merge($item, [$entry['sku'], (string) $qty]))) ?>">
                                        <?php if ($manager): ?>
                                            <td>
                                                <?php if ($canPrintQr): ?>
                                                    <input class="select-qr-product" type="checkbox" value="<?= (int) $product['id'] ?>" aria-label="เลือกรหัส <?= h($entry['sku']) ?> เพื่อพิมพ์ QR">
                                                <?php else: ?>
                                                    <span class="muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <?php foreach ($item as $index => $value): ?>
                                            <td>
                                                <?php if ((int) $index === 4): ?>
                                                    <strong><?= h($value !== '' ? $value : ($entry['sku'] ?: '-')) ?></strong>
                                                    <?php if ($manager && $product): ?>
                                                        <button class="qr-link generate-qr-btn" type="button" data-qr-id="<?= (int) $product['id'] ?>">▦ QR</button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?= nl2br(h($value)) ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td><?= $qty === null ? '<span class="muted">ยังไม่ซิงก์</span>' : '<strong>' . number_format($qty) . '</strong>' ?></td>
                                        <td>
                                            <?php if ($manager && $product): ?>
                                                <form class="min-stock-form" data-product-id="<?= (int) $product['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                    <input class="input input-bordered input-xs" name="min_qty" type="number" min="0" value="<?= $minQty ?>" aria-label="จุดเตือน <?= h($entry['sku']) ?>">
                                                    <button class="btn btn-outline btn-xs" type="submit">บันทึก</button>
                                                </form>
                                            <?php elseif ($minQty === null): ?>
                                                <span class="muted">-</span>
                                            <?php else: ?>
                                                <?= number_format($minQty) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($product === null): ?>
                                                <span class="badge badge-outline">ไม่มี SKU</span>
                                            <?php elseif ($minQty > 0 && $qty <= $minQty): ?>
                                                <span class="badge badge-warning">ใกล้หมด</span>
                                            <?php elseif ($minQty === 0): ?>
                                                <span class="badge badge-outline">ตั้งจุดเตือน</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">ปกติ</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$visibleAwardedItems): ?>
                                    <tr>
                                        <td colspan="<?= count($awardedHeaders) + 3 + ($manager ? 1 : 0) ?>" class="muted">ไม่พบสินค้า</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="inventory-pagination">
                        <p class="inventory-result-count">แสดง <?= number_format($inventoryStart) ?>–<?= number_format($inventoryEnd) ?> จาก <?= number_format($inventoryTotal) ?> รายการ (รวม Master Excel และสินค้าในระบบ)</p>
                        <?php if ($inventoryPages > 1): ?>
                            <nav aria-label="หน้ารายการสินค้า">
                                <?php if ($inventoryPage > 1): ?>
                                    <a class="btn btn-outline btn-sm" href="index.php?inventory_page=<?= $inventoryPage - 1 ?><?= $productSearch !== '' ? '&amp;product_search=' . rawurlencode($productSearch) : '' ?>#inventory">‹ ก่อนหน้า</a>
                                <?php endif; ?>
                                <span>หน้า <?= number_format($inventoryPage) ?> จาก <?= number_format($inventoryPages) ?></span>
                                <?php if ($inventoryPage < $inventoryPages): ?>
                                    <a class="btn btn-outline btn-sm" href="index.php?inventory_page=<?= $inventoryPage + 1 ?><?= $productSearch !== '' ? '&amp;product_search=' . rawurlencode($productSearch) : '' ?>#inventory">ถัดไป ›</a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                </section>

                <aside id="stockAside" class="stock-aside">
                <section id="lowStock" class="panel card bg-base-100 shadow-sm">
                    <div class="panel-title">
                        <div>
                            <h2>แจ้งเตือนสินค้าใกล้หมด</h2>
                            <p>แจ้งจากสินค้าชุดเดียวกับตารางและค้นหา · ต้องกำหนดจุดเตือนมากกว่า 0</p>
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
                                        <small><?= h($p['size_mm']) ?> · <?= h($p['grade']) ?></small>
                                    </div>
                                    <strong><?= number_format($p['qty']) ?> ชิ้น</strong>
                                    <span class="badge badge-warning badge-outline">ขั้นต่ำ <?= number_format($p['min_qty']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($manager): ?>
                <?php $sampleQr = $products[0] ?? null; ?>
                <section id="qrShelfCard" class="qr-shelf-card card bg-base-100 shadow-sm">
                    <div class="qr-shelf-heading">
                        <div class="qr-shelf-mark">▦</div>
                        <div>
                            <h2>ตัวอย่างป้าย QR Code สินค้า</h2>
                            <p>QR เก็บ SKU เดียวกับช่องค้นหาและรายการสต๊อก</p>
                        </div>
                    </div>
                    <?php if ($sampleQr): ?>
                        <div class="qr-shelf-label">
                            <div class="qr-shelf-product">
                                <b><?= h($sampleQr['sku']) ?></b>
                                <span><?= h($sampleQr['size_mm']) ?> · <?= h($sampleQr['grade']) ?></span>
                                <span><?= h($sampleQr['description'] ?: $sampleQr['standard_name']) ?></span>
                                <small>รหัสนี้ใช้ค้นหาและบันทึกสต๊อก</small>
                            </div>
                            <div id="shelfQrCode" class="shelf-qr-code" aria-label="QR Code ตัวอย่าง"></div>
                        </div>
                        <button class="btn btn-outline full generate-qr-btn" type="button" data-qr-id="<?= (int) $sampleQr['id'] ?>">สร้าง / พิมพ์ฉลาก</button>
                    <?php else: ?>
                        <p class="muted">ยังไม่มีสินค้าในรายการสำหรับสร้าง QR</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
                </aside>
            <?php endif; ?>
