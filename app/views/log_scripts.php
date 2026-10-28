<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
    <script>
        const exportDatasetCheckboxes = [...document.querySelectorAll('.select-export-dataset')];
        const exportSelectedButton = document.getElementById('exportSelectedDatasetsBtn');
        const selectAllDatasetsCheckbox = document.getElementById('selectAllExportDatasets');
        const updateExportDatasetSelection = () => {
            const selectedCount = exportDatasetCheckboxes.filter((checkbox) => checkbox.checked).length;
            if (exportSelectedButton) exportSelectedButton.disabled = selectedCount === 0;
            if (selectAllDatasetsCheckbox) {
                selectAllDatasetsCheckbox.checked = selectedCount === exportDatasetCheckboxes.length;
                selectAllDatasetsCheckbox.indeterminate = selectedCount > 0 && selectedCount < exportDatasetCheckboxes.length;
            }
        };
        exportDatasetCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateExportDatasetSelection));
        selectAllDatasetsCheckbox?.addEventListener('change', () => {
            exportDatasetCheckboxes.forEach((checkbox) => {
                checkbox.checked = selectAllDatasetsCheckbox.checked;
            });
            updateExportDatasetSelection();
        });
        updateExportDatasetSelection();

        document.querySelectorAll('.delete-selected-logs-btn').forEach((button) => {
            const section = button.closest('section');
            const rowCheckboxes = [...section.querySelectorAll('.select-log-row')];
            const selectAllCheckbox = section.querySelector('.select-all-logs');
            const updateLogSelection = () => {
                const selectedCount = rowCheckboxes.filter((checkbox) => checkbox.checked).length;
                button.disabled = selectedCount === 0;
                button.textContent = selectedCount ? `ลบรายการที่เลือก (${selectedCount})` : 'ลบรายการที่เลือก';
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = rowCheckboxes.length > 0 && selectedCount === rowCheckboxes.length;
                    selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < rowCheckboxes.length;
                }
            };
            rowCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateLogSelection));
            selectAllCheckbox?.addEventListener('change', () => {
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateLogSelection();
            });
            updateLogSelection();

            button.addEventListener('click', async () => {
                const selectedIds = rowCheckboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
                if (!selectedIds.length) return;
                const confirmed = window.confirm(
                    `ยืนยันลบ log ที่เลือก ${selectedIds.length} รายการหรือไม่? การลบนี้ย้อนคืนไม่ได้${button.dataset.logType === 'transactions' ? ' ยอดคงเหลือสินค้าจะไม่เปลี่ยน' : ''}`
                );
                if (!confirmed) return;

                button.disabled = true;
                button.textContent = 'กำลังลบ...';
                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'clear_logs',
                            log_type: button.dataset.logType,
                            log_ids: selectedIds,
                            csrf_token: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
                        })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.error || 'ลบ log ไม่สำเร็จ');
                    location.reload();
                } catch (error) {
                    updateLogSelection();
                    window.alert(error.message);
                }
            });
        });
    </script>
