<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
    <script>
        const accountCsrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const requestAccountApi = async (payload) => {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...payload, csrf_token: accountCsrfToken })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'ดำเนินการไม่สำเร็จ');
            return result;
        };

        document.getElementById('refreshStockRequestsBtn').addEventListener('click', () => location.reload());

        const stockRequestPanel = document.getElementById('stock-requests');
        let pendingStockRequestCount = Number(stockRequestPanel.dataset.pendingCount);
        window.setInterval(async () => {
            const message = document.getElementById('stockRequestMsg');
            try {
                const result = await requestAccountApi({ action: 'pending_stock_request_count' });
                if (result.count === pendingStockRequestCount) return;

                pendingStockRequestCount = result.count;
                document.getElementById('pendingStockRequestCount').textContent = result.count.toLocaleString();
                document.getElementById('stockRequestSummary').textContent =
                    `มี ${result.count.toLocaleString()} รายการรอการยืนยัน · อนุมัติแล้วระบบจึงเปลี่ยนยอดคงเหลือ`;
                message.textContent = result.count > Number(stockRequestPanel.dataset.pendingCount)
                    ? 'มีคำขอทำรายการสต๊อกใหม่ กรุณารีเฟรชเพื่อดูรายละเอียดและยืนยัน'
                    : 'รายการคำขอเปลี่ยนแปลง กรุณารีเฟรชเพื่อดูข้อมูลล่าสุด';
            } catch (error) {
                message.textContent = 'ตรวจสอบคำขอใหม่ไม่สำเร็จ: ' + error.message;
            }
        }, 30000);

        document.querySelectorAll('.review-stock-request-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                const approve = button.dataset.decision === 'approve';
                if (!window.confirm(`${approve ? 'ยืนยันอนุมัติ' : 'ยืนยันปฏิเสธ'}คำขอทำรายการสต๊อก #${button.dataset.requestId} หรือไม่?`)) return;

                const message = document.getElementById('stockRequestMsg');
                document.querySelectorAll('.review-stock-request-btn').forEach((item) => { item.disabled = true; });
                message.textContent = approve ? 'กำลังอนุมัติคำขอ...' : 'กำลังปฏิเสธคำขอ...';
                try {
                    const result = await requestAccountApi({
                        action: 'review_stock_request',
                        request_id: button.dataset.requestId,
                        decision: button.dataset.decision
                    });
                    message.textContent = approve
                        ? `อนุมัติคำขอแล้ว คงเหลือใหม่ ${Number(result.balance_after).toLocaleString()} ชิ้น`
                        : 'ปฏิเสธคำขอแล้ว';
                    setTimeout(() => location.reload(), 800);
                } catch (error) {
                    message.textContent = error.message;
                    document.querySelectorAll('.review-stock-request-btn').forEach((item) => { item.disabled = false; });
                }
            });
        });

        document.querySelectorAll('.toggle-password-visibility-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordTarget);
                const isRevealed = input.type === 'text';
                input.type = isRevealed ? 'password' : 'text';
                button.textContent = isRevealed ? 'แสดงรหัสผ่าน' : 'ซ่อนรหัสผ่าน';
                button.setAttribute('aria-label', button.textContent);
                button.setAttribute('aria-pressed', String(!isRevealed));
            });
        });

        const resetPasswordVisibility = (container) => {
            container.querySelectorAll('.toggle-password-visibility-btn').forEach((button) => {
                document.getElementById(button.dataset.passwordTarget).type = 'password';
                button.textContent = 'แสดงรหัสผ่าน';
                button.setAttribute('aria-label', 'แสดงรหัสผ่าน');
                button.setAttribute('aria-pressed', 'false');
            });
        };

        const changePasswordDialog = document.getElementById('changeUserPasswordDialog');
        const changePasswordForm = document.getElementById('changeUserPasswordForm');
        document.querySelectorAll('.change-user-password-btn').forEach((button) => {
            button.addEventListener('click', () => {
                resetPasswordVisibility(changePasswordForm);
                changePasswordForm.reset();
                document.getElementById('changePasswordUserId').value = button.dataset.userId;
                document.getElementById('changePasswordUserName').textContent = button.dataset.userName;
                document.getElementById('changeUserPasswordMsg').textContent = '';
                changePasswordDialog.showModal();
            });
        });
        document.getElementById('closeChangePasswordDialog').addEventListener('click', () => changePasswordDialog.close());
        changePasswordDialog.addEventListener('close', () => {
            resetPasswordVisibility(changePasswordForm);
            changePasswordForm.reset();
            document.getElementById('changeUserPasswordMsg').textContent = '';
        });
        changePasswordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const password = document.getElementById('newUserPassword').value;
            const confirmPassword = document.getElementById('confirmUserPassword').value;
            const message = document.getElementById('changeUserPasswordMsg');
            const button = document.getElementById('changeUserPasswordSubmit');
            if (password !== confirmPassword) {
                message.textContent = 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน';
                return;
            }

            button.disabled = true;
            message.textContent = 'กำลังเปลี่ยนรหัสผ่าน...';
            try {
                await requestAccountApi({
                    action: 'change_user_password',
                    user_id: document.getElementById('changePasswordUserId').value,
                    password,
                    confirm_password: confirmPassword
                });
                message.textContent = 'เปลี่ยนรหัสผ่านสำเร็จ';
                setTimeout(() => location.reload(), 900);
            } catch (error) {
                message.textContent = error.message;
                button.disabled = false;
            }
        });

        document.querySelectorAll('.delete-user-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                const confirmed = window.confirm(`ยืนยันลบบัญชี "${button.dataset.userName}" หรือไม่? ประวัติรายการเดิมจะยังคงอยู่`);
                if (!confirmed) return;

                button.disabled = true;
                button.textContent = 'กำลังลบ...';
                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete_user',
                            user_id: button.dataset.userId,
                            csrf_token: accountCsrfToken
                        })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.error || 'ลบบัญชีไม่สำเร็จ');
                    location.reload();
                } catch (error) {
                    window.alert(error.message);
                    button.disabled = false;
                    button.textContent = 'ลบบัญชี';
                }
            });
        });

        document.getElementById('userForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const data = Object.fromEntries(new FormData(form).entries());
            data.action = 'create_user';
            const message = document.getElementById('userMsg');
            message.textContent = 'กำลังสร้างบัญชี...';

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...data, csrf_token: accountCsrfToken })
                });
                const json = await response.json();
                if (!response.ok) throw new Error(json.error || 'สร้างบัญชีไม่สำเร็จ');

                message.textContent = 'สร้างบัญชีสำเร็จ';
                setTimeout(() => location.reload(), 800);
            } catch (error) {
                message.textContent = error.message;
            }
        });
    </script>
    <?php require __DIR__ . '/log_scripts.php'; ?>
</body>
</html>
