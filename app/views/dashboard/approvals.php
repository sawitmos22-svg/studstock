<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php if ($manager): ?>
            <section id="stock-requests" class="panel card bg-base-100 shadow-sm" data-pending-count="<?= count($stockRequests) ?>">
                <div class="panel-title">
                    <div>
                        <h2>คำขอทำรายการสต๊อก</h2>
                        <p id="stockRequestSummary">มี <?= number_format(count($stockRequests)) ?> รายการรอการยืนยัน · อนุมัติแล้วระบบจึงเปลี่ยนยอดคงเหลือ</p>
                    </div>
                    <button class="btn btn-outline" id="refreshStockRequestsBtn" type="button">↻ รีเฟรชรายการ</button>
                </div>

                <p id="stockRequestMsg" role="status" aria-live="polite"></p>
                <div class="table-wrap">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>วันที่ขอ</th>
                                <th>ผู้ขอ</th>
                                <th>ประเภท</th>
                                <th>สินค้า</th>
                                <th>จำนวน / ยอดใหม่</th>
                                <th>คงเหลือปัจจุบัน</th>
                                <th>รายละเอียด</th>
                                <th>การยืนยัน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockRequests as $request): ?>
                                <tr>
                                    <td><?= h($request['created_at']) ?></td>
                                    <td><?= h($request['requester_full_name']) ?><small class="subcell"><?= h($request['requester_username']) ?></small></td>
                                    <td><?= $request['request_type'] === 'OUT' ? 'เบิกออก' : ($request['request_type'] === 'IN' ? 'รับเข้า' : 'ปรับยอด') ?></td>
                                    <td>
                                        <b><?= h($request['sku']) ?></b>
                                        <small class="subcell"><?= h($request['description'] ?: ($request['size_mm'] . ' × ' . $request['length_mm'] . ' mm · ' . $request['grade'])) ?></small>
                                    </td>
                                    <td><?= number_format($request['qty']) ?> ชิ้น</td>
                                    <td><?= number_format($request['current_qty']) ?> ชิ้น</td>
                                    <td>
                                        <?php if ($request['job_ref'] !== ''): ?>
                                            <small class="subcell">ใบงาน: <?= h($request['job_ref']) ?></small>
                                        <?php endif; ?>
                                        <?= h($request['note'] !== '' ? $request['note'] : '-') ?>
                                    </td>
                                    <td>
                                        <div class="log-actions">
                                            <button class="btn btn-primary btn-sm review-stock-request-btn" type="button" data-request-id="<?= (int) $request['id'] ?>" data-decision="approve">อนุมัติ</button>
                                            <button class="btn btn-error btn-sm review-stock-request-btn" type="button" data-request-id="<?= (int) $request['id'] ?>" data-decision="reject">ปฏิเสธ</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$stockRequests): ?>
                                <tr><td colspan="8" class="muted">ไม่มีคำขอที่รออนุมัติ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
<?php endif; ?>
