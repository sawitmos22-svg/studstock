<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<!doctype html>
<html lang="th" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>StudBolt Stock</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <?php if ($canViewStock): ?>
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="<?= h(APP_PUBLIC_BASE) ?>assets/style.css?v=<?= (int) filemtime(__DIR__ . '/../../public/assets/style.css') ?>">
</head>
<body>
    <header class="navbar topbar">
        <div class="brand">
            <div class="brand-mark small">⚙</div>
            <div>
                <b>Stud Bolt <span class="brand-full">Stock Management System</span><span class="brand-short">Stock</span></b>
                <small>ระบบคลังสินค้า Stud Bolt&nbsp;&nbsp; | &nbsp;&nbsp;ควบคุมสต๊อก เบิกจ่าย ตรวจสอบได้</small>
            </div>
        </div>

        <nav class="top-navigation" aria-label="เมนูด่วน">
            <?php if ($canViewStock): ?>
                <a href="index.php#inventory">▦ <span><?= $manager ? 'จัดการสต๊อกทั้งหมด' : 'ดูสต๊อกสินค้า' ?></span></a>
            <?php endif; ?>
            <a href="index.php#issue">▣ <span>ระบบเบิกสินค้า</span></a>
            <a href="index.php#history">▤ <span><?= $manager ? 'ประวัติและส่งออก Excel' : 'ประวัติการทำรายการ' ?></span></a>
            <?php if ($canViewStock): ?>
                <a href="index.php#lowStock">⚠ <span>แจ้งเตือนสต๊อกต่ำ</span></a>
            <?php endif; ?>
        </nav>

        <div class="userbox">
            <span class="badge badge-primary role-pill"><?= $manager ? 'MANAGER' : ($stockStaff ? 'STOCK STAFF' : 'EMPLOYEE') ?></span>
            <span class="user-name"><?= h($u['full_name']) ?> (<?= h($u['username']) ?>)</span>
            <a class="btn btn-ghost btn-sm logout-link" href="?logout=1">ออกจากระบบ ↗</a>
        </div>
    </header>

    <div class="app <?= $manager ? 'manager-view' : ($stockStaff ? 'stock-staff-view' : 'employee-view') ?>">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <!-- <div class="sidebar-brand-icon">⚙</div> -->
                <!-- <div><b>StudBolt</b><small>Warehouse System</small></div> -->
            </div>
            <div class="side-caption">เมนูหลัก</div>
            <ul class="menu sidebar-menu">
                <li><a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="index.php">⌂ &nbsp;หน้าหลัก<?= $manager ? ' (ผู้จัดการ)' : '' ?></a></li>
            <?php if ($canViewStock): ?>
                <li><a href="index.php#inventory">▤ &nbsp;สต๊อกสินค้า</a></li>
            <?php endif; ?>
                <li><a href="index.php#issue">▣ &nbsp;รับเข้า / เบิกออก</a></li>
                <li><a href="index.php#history">◷ &nbsp;ประวัติการทำรายการ</a></li>
            <?php if ($manager): ?>
                <li><a class="<?= $page === 'accounts' ? 'active' : '' ?>" href="index.php?page=accounts#stock-requests">♙ &nbsp;คำขออนุมัติ (<span id="pendingStockRequestCount"><?= number_format($pendingStockRequestCount) ?></span>)</a></li>
            <?php endif; ?>
            <?php if ($canViewStock): ?>
                <li><a href="index.php#lowStock">⚠ &nbsp;แจ้งเตือนสต๊อกต่ำ</a></li>
            <?php endif; ?>
            <?php if ($manager): ?>
                <li><a href="?export=products">⇩ &nbsp;ส่งออกสินค้าทั้งหมด CSV</a></li>
            <?php endif; ?>
            </ul>
            <div class="sidebar-account">
                <span class="account-avatar">♙</span>
                <span><b><?= h($u['full_name']) ?></b><small><?= h($u['username']) ?> · <?= $manager ? 'ผู้จัดการคลัง' : ($stockStaff ? 'พนักงานคลัง' : 'พนักงานเบิกสินค้า') ?></small></span>
            </div>
            <div class="sidebar-bottom"><span class="dot"></span> ฐานข้อมูลคลังสินค้าออนไลน์</div>
        </aside>

        <main class="content <?= $page === 'accounts' ? 'accounts-page' : '' ?>">
