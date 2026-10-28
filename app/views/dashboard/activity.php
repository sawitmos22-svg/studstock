<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php if ($manager): ?>
            <section id="activity" class="panel card bg-base-100 shadow-sm">
                <div class="panel-title">
                    <div>
                        <h2>ประวัติการเข้าใช้งาน</h2>
                        <p>หน้า <?= number_format($activityPage) ?> / <?= number_format($activityPages) ?> · เลือกรายการในหน้านี้เพื่อลบ</p>
                    </div>
                    <div class="log-actions">
                        <a class="btn btn-outline" href="index.php?export=activity">⇩ ส่งออก CSV</a>
                        <button class="btn btn-outline delete-selected-logs-btn" data-log-type="activity" type="button" disabled>ลบรายการที่เลือก</button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th><input class="select-all-logs" type="checkbox" aria-label="เลือกประวัติการเข้าใช้งานทั้งหมดในหน้านี้"></th>
                                <th>วันเวลา</th>
                                <th>กิจกรรม</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>บัญชีผู้ใช้</th>
                                <th>รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityLogs as $log): ?>
                                <?php
                                $eventLabels = [
                                    'LOGIN' => 'เข้าสู่ระบบ',
                                    'LOGIN_FAILED' => 'เข้าสู่ระบบไม่สำเร็จ',
                                    'LOGOUT' => 'ออกจากระบบ',
                                    'IMPORT' => 'นำเข้าสินค้า',
                                    'USER_CREATE' => 'สร้างบัญชีผู้ใช้',
                                    'USER_DELETE' => 'ลบบัญชีผู้ใช้',
                                    'PASSWORD_CHANGE' => 'เปลี่ยนรหัสผ่าน',
                                    'LOG_CLEAR' => 'ล้างประวัติ log',
                                    'STOCK_CLEAR' => 'เคลียร์สต๊อก',
                                    'STOCK_REQUEST' => 'ส่งคำขอสต๊อก',
                                    'STOCK_REQUEST_REVIEW' => 'ตรวจสอบคำขอสต๊อก',
                                ];
                                ?>
                                <tr>
                                    <td><input class="select-log-row" type="checkbox" value="<?= h($log['id']) ?>" aria-label="เลือก log ประวัติการเข้าใช้งาน"></td>
                                    <td><?= h($log['created_at']) ?></td>
                                    <td><?= h($eventLabels[$log['event_type']] ?? $log['event_type']) ?></td>
                                    <td><?= h($log['full_name'] ?: 'ไม่พบข้อมูลผู้ใช้') ?></td>
                                    <td><?= h($log['username']) ?></td>
                                    <td><?= h($log['details']) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$activityLogs): ?>
                                <tr>
                                    <td colspan="6" class="muted">ยังไม่มีประวัติการเข้าใช้งาน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($activityPages > 1): ?>
                    <nav class="log-pagination" aria-label="หน้าประวัติการเข้าใช้งาน">
                        <?php if ($activityPage > 1): ?>
                            <a class="btn btn-outline btn-sm" href="index.php?page=accounts&amp;activity_page=<?= $activityPage - 1 ?>#activity">‹ ก่อนหน้า</a>
                        <?php endif; ?>
                        <span>หน้า <?= number_format($activityPage) ?> จาก <?= number_format($activityPages) ?></span>
                        <?php if ($activityPage < $activityPages): ?>
                            <a class="btn btn-outline btn-sm" href="index.php?page=accounts&amp;activity_page=<?= $activityPage + 1 ?>#activity">ถัดไป ›</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>
<?php endif; ?>
