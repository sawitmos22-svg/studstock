<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
    <script>
        const products = <?= json_encode(
            array_map(function ($p) {
                return [
                    'id' => (int) $p['id'],
                    'sku' => $p['sku'],
                    'size_mm' => $p['size_mm'],
                    'length_mm' => (float) $p['length_mm'],
                    'grade' => $p['grade'],
                    'standard_name' => $p['standard_name'],
                    'qty' => (int) $p['qty'],
                    'min_qty' => (int) $p['min_qty'],
                    'location_code' => $p['location_code'],
                ];
            }, $products),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) ?>;

        let selected = null;
        let cameraStream = null;
        let scanFrame = null;
        let lastScanAt = 0;
        const $ = (id) => document.getElementById(id);

        const backToTopButton = $('backToTop');
        const updateBackToTopButton = () => {
            backToTopButton.hidden = window.scrollY < 120;
        };
        window.addEventListener('scroll', updateBackToTopButton, { passive: true });
        updateBackToTopButton();
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
            });
        });

        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[c]));
        }

        function renderResults(q = '') {
            const box = $('productResults');
            if (!box) return;

            const search = q.toLowerCase().trim();
            const matches = products
                .filter((p) => (p.sku + ' ' + p.size_mm + ' ' + p.grade + ' ' + p.length_mm).toLowerCase().includes(search))
                .slice(0, 12);

            box.innerHTML = matches.map((p) => `
                <button type="button" class="result-item" data-id="${p.id}">
                    <span>
                        <b>${esc(p.sku)}</b>
                        <small>${esc(p.size_mm)} × ${p.length_mm} mm · ${esc(p.grade)}</small>
                    </span>
                    <strong class="${p.qty <= p.min_qty ? 'qty-low' : ''}">${p.qty.toLocaleString()} ชิ้น</strong>
                </button>
            `).join('') || '<p class="muted">ไม่พบสินค้า</p>';

            box.querySelectorAll('[data-id]').forEach((button) => {
                button.onclick = () => choose(+button.dataset.id);
            });
        }

        function choose(id) {
            selected = products.find((p) => p.id === id);
            if (!selected) return;

            $('product_id').value = id;
            $('selectedProduct').innerHTML = `
                <div>
                    <span class="eyebrow">SELECTED ITEM</span>
                    <h3>${esc(selected.sku)}</h3>
                    <p>${esc(selected.size_mm)} × ${selected.length_mm} mm · ${esc(selected.grade)} · ${esc(selected.standard_name)}</p>
                    <small>ตำแหน่ง ${esc(selected.location_code || '-')}</small>
                </div>
                <div class="stock-number">
                    <small>คงเหลือ</small>
                    <strong>${selected.qty.toLocaleString()}</strong>
                    <small>ชิ้น</small>
                </div>
            `;
            $('txnMsg').textContent = '';
        }

        $('search')?.addEventListener('input', (e) => renderResults(e.target.value));
        renderResults();

        $('type').addEventListener('change', () => {
            const isAdjust = $('type').value === 'ADJUST';
            $('qty').min = isAdjust ? '0' : '1';
            $('qtyLabel').textContent = isAdjust ? 'ยอดคงเหลือใหม่ (ชิ้น)' : 'จำนวน (ชิ้น)';
            if (!isAdjust && Number($('qty').value) < 1) $('qty').value = '1';
        });

        $('txnForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!selected) {
                $('txnMsg').textContent = 'กรุณาเลือกสินค้าก่อน';
                return;
            }

            const data = Object.fromEntries(new FormData(e.target).entries());
            data.action = 'transaction';
            $('txnMsg').textContent = 'กำลังบันทึก...';

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const json = await response.json();
                if (!response.ok) throw new Error(json.error || 'ทำรายการไม่สำเร็จ');

                if (json.status === 'PENDING') {
                    $('txnMsg').textContent = `ส่งคำขอสำเร็จ เลขที่คำขอ #${json.request_id} · รอผู้จัดการอนุมัติก่อนเปลี่ยนยอดสต๊อก`;
                    $('txnForm').querySelector('button[type="submit"]').disabled = true;
                    setTimeout(() => location.reload(), 1800);
                    return;
                }

                $('txnMsg').textContent = 'บันทึกสำเร็จ เลขที่รายการ #' + json.id + ' · คงเหลือ ' + json.balance_after + ' ชิ้น';
                setTimeout(() => location.reload(), 1000);
            } catch (error) {
                $('txnMsg').textContent = error.message;
            }
        });

        function stopCameraScan() {
            if (scanFrame !== null) {
                cancelAnimationFrame(scanFrame);
                scanFrame = null;
            }
            if (cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                cameraStream = null;
            }

            const video = $('reader').querySelector('video');
            if (video) video.srcObject = null;
            $('reader').replaceChildren();
            $('stopScanBtn').hidden = true;
            $('scanBtn').disabled = false;
        }

        function cameraErrorMessage(error) {
            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                return 'ไม่ได้รับอนุญาตให้ใช้กล้อง โปรดอนุญาต Camera ในการตั้งค่าเว็บไซต์แล้วลองอีกครั้ง';
            }
            if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
                return 'ไม่พบกล้องที่ใช้งานได้บนอุปกรณ์นี้';
            }
            if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
                return 'กล้องกำลังถูกใช้งานโดยแอปอื่น โปรดปิดแอปที่ใช้กล้องแล้วลองใหม่';
            }
            if (error.name === 'OverconstrainedError' || error.name === 'ConstraintNot satisfied') {
                return 'เปิดกล้องหลังไม่ได้ โปรดลองใช้กล้องอีกตัวหรือค้นหาสินค้าด้วย SKU';
            }
            return 'เปิดกล้องไม่สำเร็จ: ' + (error.message || 'โปรดตรวจสอบสิทธิ์และการตั้งค่ากล้อง');
        }

        function scanQrFrame(video, canvas, context) {
            if (!cameraStream || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                scanFrame = requestAnimationFrame(() => scanQrFrame(video, canvas, context));
                return;
            }

            const now = performance.now();
            if (now - lastScanAt >= 120) {
                lastScanAt = now;
                const scale = Math.min(1, 640 / video.videoWidth);
                canvas.width = Math.round(video.videoWidth * scale);
                canvas.height = Math.round(video.videoHeight * scale);

                try {
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const image = context.getImageData(0, 0, canvas.width, canvas.height);
                    const result = window.jsQR(image.data, image.width, image.height, {
                        inversionAttempts: 'attemptBoth'
                    });

                    if (result?.data) {
                        const value = result.data.trim();
                        stopCameraScan();
                        $('scanMsg').textContent = 'สแกนได้: ' + value;

                        const product = products.find((item) =>
                            item.sku.toLowerCase() === value.toLowerCase() || value.includes(item.sku)
                        );
                        if (product) {
                            choose(product.id);
                            const actionLabel = $('type').value === 'IN'
                                ? 'กรอกจำนวนรับเข้า'
                                : ($('type').value === 'ADJUST' ? 'กรอกยอดคงเหลือใหม่' : 'กรอกจำนวนเพื่อเบิก');
                            $('scanMsg').textContent = 'เลือก ' + product.sku + ' แล้ว · ' + actionLabel;
                            requestAnimationFrame(() => {
                                $('txnForm').scrollIntoView({
                                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                                    block: 'center'
                                });
                            });
                        } else {
                            $('search').value = value;
                            renderResults(value);
                            $('scanMsg').textContent = 'สแกนแล้ว กรุณาเลือกสินค้าที่ตรงกัน';
                        }
                        return;
                    }
                } catch (error) {
                    stopCameraScan();
                    $('scanMsg').textContent = 'อ่านภาพจากกล้องไม่สำเร็จ: ' + error.message;
                    return;
                }
            }

            scanFrame = requestAnimationFrame(() => scanQrFrame(video, canvas, context));
        }

        $('scanBtn')?.addEventListener('click', async () => {
            const message = $('scanMsg');
            const button = $('scanBtn');
            button.disabled = true;

            if (!window.isSecureContext) {
                message.textContent = 'เบราว์เซอร์บล็อกกล้องในที่อยู่นี้ โปรดเปิดเว็บผ่าน HTTPS (localhost ใช้ได้เฉพาะบนเครื่องที่รันเว็บ ไม่ใช่ localhost บนมือถือ)';
                button.disabled = false;
                return;
            }
            if (!navigator.mediaDevices?.getUserMedia) {
                message.textContent = 'เบราว์เซอร์นี้ไม่รองรับการเปิดกล้อง กรุณาใช้ Chrome/Safari รุ่นล่าสุดหรือค้นหาด้วย SKU';
                button.disabled = false;
                return;
            }
            if (typeof window.jsQR !== 'function') {
                message.textContent = 'โหลดตัวอ่าน QR ไม่สำเร็จ โปรดตรวจสอบอินเทอร์เน็ตแล้วรีเฟรชหน้า หรือค้นหาด้วย SKU';
                button.disabled = false;
                return;
            }

            message.textContent = 'กำลังขออนุญาตใช้กล้อง...';
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    audio: false,
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                });

                const video = document.createElement('video');
                video.setAttribute('playsinline', '');
                video.muted = true;
                video.autoplay = true;
                video.srcObject = cameraStream;
                video.className = 'scan-video';
                $('reader').replaceChildren(video);
                await video.play();

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d', { willReadFrequently: true });
                if (!context) throw new Error('เบราว์เซอร์ไม่สามารถอ่านภาพจากกล้องได้');

                lastScanAt = 0;
                $('stopScanBtn').hidden = false;
                message.textContent = 'กำลังสแกน... จ่อกล้องไปที่ QR Code';
                scanFrame = requestAnimationFrame(() => scanQrFrame(video, canvas, context));
            } catch (error) {
                stopCameraScan();
                message.textContent = error.name === 'Error'
                    ? error.message
                    : cameraErrorMessage(error);
            }
        });

        $('stopScanBtn')?.addEventListener('click', () => {
            stopCameraScan();
            $('scanMsg').textContent = 'หยุดสแกนแล้ว';
        });

        <?php if ($manager): ?>
            $('clearStockBtn').onclick = () => $('clearStockDialog').showModal();
            $('closeClearStockDialog').onclick = () => $('clearStockDialog').close();
            $('confirmClearStock').addEventListener('change', (e) => {
                $('confirmClearStockBtn').disabled = !e.target.checked;
            });
            $('clearStockDialog').addEventListener('close', () => {
                $('clearStockForm').reset();
                $('confirmClearStockBtn').disabled = true;
                $('clearStockMsg').textContent = '';
            });
            $('clearStockForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!$('confirmClearStock').checked) return;

                const button = $('confirmClearStockBtn');
                const message = $('clearStockMsg');
                button.disabled = true;
                message.textContent = 'กำลังเคลียร์สต๊อก...';
                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'clear_all_stock',
                            csrf_token: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
                        })
                    });
                    const json = await response.json();
                    if (!response.ok) throw new Error(json.error || 'เคลียร์สต๊อกไม่สำเร็จ');

                    message.textContent = json.cleared
                        ? `เคลียร์สต๊อกสำเร็จ ${json.cleared.toLocaleString()} SKU รวม ${json.units.toLocaleString()} ชิ้น`
                        : 'ไม่มีสินค้าคงเหลือให้เคลียร์';
                    setTimeout(() => location.reload(), 1200);
                } catch (error) {
                    message.textContent = error.message;
                    button.disabled = !$('confirmClearStock').checked;
                }
            });

            $('importProductsBtn').onclick = () => $('importDialog').showModal();
            $('closeImportDialog').onclick = () => $('importDialog').close();
            $('importForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                formData.append('action', 'import_products');
                const message = $('importMsg');
                message.textContent = 'กำลังตรวจสอบและนำเข้า...';

                try {
                    const response = await fetch('api.php', { method: 'POST', body: formData });
                    const json = await response.json();
                    if (!response.ok) {
                        const details = Array.isArray(json.errors) ? '\n' + json.errors.join('\n') : '';
                        throw new Error((json.error || 'นำเข้าไม่สำเร็จ') + details);
                    }

                    message.textContent = 'นำเข้าสำเร็จ ' + json.imported + ' รายการ';
                    setTimeout(() => location.reload(), 1200);
                } catch (error) {
                    message.textContent = error.message;
                }
            });

            $('newProductBtn').onclick = () => $('productDialog').showModal();
            $('closeProductDialog').onclick = () => $('productDialog').close();
            $('closeQrLabelDialog').onclick = () => $('qrLabelDialog').close();

            function openQrLabel(productId) {
                const product = products.find((item) => item.id === productId);
                const message = $('qrLabelMsg');
                const code = $('qrCode');
                code.replaceChildren();
                message.textContent = '';

                if (!product) {
                    message.textContent = 'ไม่พบข้อมูลสินค้าที่ต้องการสร้าง QR';
                    $('qrLabelDialog').showModal();
                    return;
                }
                if (typeof window.QRCode !== 'function') {
                    message.textContent = 'โหลดตัวสร้าง QR ไม่สำเร็จ โปรดตรวจสอบอินเทอร์เน็ตแล้วรีเฟรชหน้า';
                    $('qrLabelDialog').showModal();
                    return;
                }

                $('qrLabelSku').textContent = product.sku;
                $('qrLabelDescription').textContent = `${product.size_mm} × ${product.length_mm} mm · ${product.grade}${product.standard_name ? ' · ' + product.standard_name : ''}`;
                $('qrLabelLocation').textContent = 'ตำแหน่ง: ' + (product.location_code || '-');
                try {
                    new window.QRCode(code, {
                        text: product.sku,
                        width: 240,
                        height: 240,
                        colorDark: '#102a43',
                        colorLight: '#ffffff',
                        correctLevel: window.QRCode.CorrectLevel.M
                    });
                    $('qrLabelDialog').showModal();
                } catch (error) {
                    message.textContent = 'สร้าง QR ไม่สำเร็จ: ' + error.message;
                    $('qrLabelDialog').showModal();
                }
            }

            document.querySelectorAll('.generate-qr-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    openQrLabel(Number(button.dataset.qrId));
                });
            });
            if (products.length && typeof window.QRCode === 'function' && $('shelfQrCode')) {
                new window.QRCode($('shelfQrCode'), {
                    text: products[0].sku,
                    width: 150,
                    height: 150,
                    colorDark: '#102a43',
                    colorLight: '#ffffff',
                    correctLevel: window.QRCode.CorrectLevel.M
                });
            }
            $('printQrLabelBtn').onclick = () => window.print();
            $('productForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = Object.fromEntries(new FormData(e.target).entries());
                data.action = 'create_product';

                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const json = await response.json();
                    if (!response.ok) throw new Error(json.error || 'บันทึกไม่สำเร็จ');

                    $('productMsg').textContent = 'บันทึกแล้ว';
                    location.reload();
                } catch (error) {
                    $('productMsg').textContent = error.message;
                }
            });
        <?php endif; ?>
    </script>
    <?php require __DIR__ . '/log_scripts.php'; ?>
</body>
</html>
