<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php if ($manager): ?>
            <section id="accounts" class="panel card bg-base-100 shadow-sm">
                <div class="panel-title">
                    <div>
                        <h2>จัดการบัญชีผู้ใช้</h2>
                        <p>สร้างบัญชีแยกตามบุคคล เพื่อให้ตรวจสอบผู้เข้าใช้งานและผู้ทำรายการได้</p>
                    </div>
                    <a class="btn btn-outline" href="index.php?export=accounts">⇩ ส่งออก CSV</a>
                </div>

                <form id="userForm" class="form-grid">
                    <div>
                        <label for="newUsername">ชื่อผู้ใช้</label>
                        <input class="input input-bordered" id="newUsername" name="username" minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" autocomplete="off" required>
                    </div>
                    <div>
                        <label for="newFullName">ชื่อ-นามสกุล</label>
                        <input class="input input-bordered" id="newFullName" name="full_name" maxlength="160" required>
                    </div>
                    <div>
                        <label for="newPassword">รหัสผ่านเริ่มต้น (อย่างน้อย 8 ตัว)</label>
                        <div class="password-input-group">
                            <input class="input input-bordered" id="newPassword" name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                            <button class="btn btn-outline btn-sm toggle-password-visibility-btn" type="button" data-password-target="newPassword" aria-label="แสดงรหัสผ่าน" aria-pressed="false">แสดงรหัสผ่าน</button>
                        </div>
                    </div>
                    <div>
                        <label for="newRole">สิทธิ์การใช้งาน</label>
                        <select class="select select-bordered" id="newRole" name="role">
                            <option value="manager">ผู้จัดการ</option>
                            <option value="stock_staff">พนักงานคลัง (รับเข้า / เบิกออก / ปรับยอด โดยรออนุมัติ)</option>
                            <option value="employee">พนักงานเบิกสินค้า</option>
                        </select>
                    </div>
                    <div>
                        <button class="btn btn-primary" type="submit">สร้างบัญชี</button>
                    </div>
                </form>
                <p id="userMsg" role="status"></p>

                <div class="table-wrap">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ชื่อผู้ใช้</th>
                                <th>สิทธิ์</th>
                                <th>สถานะ</th>
                                <th>สร้างเมื่อ</th>
                                <th>การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td><?= h($account['full_name']) ?></td>
                                    <td><?= h($account['username']) ?></td>
                                    <td><?= $account['role'] === 'manager' ? 'ผู้จัดการ' : ($account['role'] === 'stock_staff' ? 'พนักงานคลัง' : 'พนักงานเบิกสินค้า') ?></td>
                                    <td><?= (int) $account['active'] === 1 ? 'ใช้งาน' : 'ปิดใช้งาน' ?></td>
                                    <td><?= h($account['created_at']) ?></td>
                                    <td>
                                        <button
                                            class="btn btn-outline btn-sm change-user-password-btn"
                                            type="button"
                                            data-user-id="<?= (int) $account['id'] ?>"
                                            data-user-name="<?= h($account['full_name']) ?>"
                                        >เปลี่ยนรหัสผ่าน</button>
                                        <?php if ((int) $account['id'] !== (int) $u['id']): ?>
                                            <button
                                                class="btn btn-error btn-sm delete-user-btn"
                                                type="button"
                                                data-user-id="<?= (int) $account['id'] ?>"
                                                data-user-name="<?= h($account['full_name']) ?>"
                                            >ลบบัญชี</button>
                                        <?php else: ?>
                                            <span class="muted">บัญชีปัจจุบัน</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
<?php endif; ?>
