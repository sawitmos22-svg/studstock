<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
            <section id="history" class="panel card bg-base-100 shadow-sm">
                <div class="panel-title">
                    <div>
                        <h2>ประวัติรับเข้า–เบิกออก</h2>
                        <p>หน้า <?= number_format($transactionPage) ?> / <?= number_format($transactionPages) ?> · เลือกรายการในหน้านี้เพื่อลบ</p>
                    </div>
                    <div class="log-actions">
                        <?php if ($manager): ?>
                            <a class="btn btn-outline" href="?export=transactions">⇩ Export</a>
                            <button class="btn btn-outline delete-selected-logs-btn" data-log-type="transactions" type="button" disabled>ลบรายการที่เลือก</button>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="back-to-top" id="backToTop" type="button" aria-label="กลับไปด้านบน" title="กลับไปด้านบน" hidden>
                    <span aria-hidden="true">↑</span>
                </button>

                <div class="table-wrap">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <?php if ($manager): ?>
                                    <th><input class="select-all-logs" type="checkbox" aria-label="เลือกประวัติรับเข้าและเบิกออกทั้งหมดในหน้านี้"></th>
                                <?php endif; ?>
                                <th>วันเวลา</th>
                                <th>ประเภท</th>
                                <th>SKU</th>
                                <th>จำนวน</th>
                                <th>คงเหลือหลังทำรายการ</th>
                                <th>ผู้ทำรายการ</th>
                                <th>ใบงาน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <?php if ($manager): ?>
                                        <td><input class="select-log-row" type="checkbox" value="<?= h($r['id']) ?>" aria-label="เลือก log ประวัติรับเข้าและเบิกออก"></td>
                                    <?php endif; ?>
                                    <td><?= h($r['created_at']) ?></td>
                                    <td>
                                        <span class="badge <?= $r['txn_type'] === 'OUT' ? 'badge-warning' : 'badge-success' ?> badge-outline">
                                            <?= $r['txn_type'] === 'OUT' ? 'เบิกออก' : ($r['txn_type'] === 'IN' ? 'รับเข้า' : 'ปรับยอด') ?>
                                        </span>
                                    </td>
                                    <td><?= h($r['sku']) ?></td>
                                    <td>
                                        <?= number_format($r['qty']) ?>
                                        <?php if ($r['note'] !== ''): ?>
                                            <small class="subcell"><?= h($r['note']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($r['balance_after']) ?></td>
                                    <td><?= h($r['full_name']) ?><small class="subcell"><?= h($r['username']) ?></small></td>
                                    <td><?= h($r['job_ref']) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$recent): ?>
                                <tr>
                                    <td colspan="<?= $manager ? '8' : '7' ?>" class="muted">ยังไม่มีประวัติรายการ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($transactionPages > 1): ?>
                    <nav class="log-pagination" aria-label="หน้าประวัติรับเข้าและเบิกออก">
                        <?php if ($transactionPage > 1): ?>
                            <a class="btn btn-outline btn-sm" href="index.php?txn_page=<?= $transactionPage - 1 ?>#history">‹ ก่อนหน้า</a>
                        <?php endif; ?>
                        <span>หน้า <?= number_format($transactionPage) ?> จาก <?= number_format($transactionPages) ?></span>
                        <?php if ($transactionPage < $transactionPages): ?>
                            <a class="btn btn-outline btn-sm" href="index.php?txn_page=<?= $transactionPage + 1 ?>#history">ถัดไป ›</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>

            
        </main>
    </div>

    