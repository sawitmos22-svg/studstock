<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<!doctype html>
    <html lang="th" data-theme="corporate">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>StudBolt Stock | Login</title>
        <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
        <link rel="stylesheet" href="<?= h(APP_PUBLIC_BASE) ?>assets/style.css">
    </head>
    <body class="login-bg">
        <div class="login-card card bg-base-100 shadow-xl">
            <div class="card-body">
            <div class="brand-mark">⚙</div>
            <p class="eyebrow">WAREHOUSE MANAGEMENT</p>
            <h1>StudBolt <span>Stock</span></h1>
            <p class="muted">ระบบจัดการคลังสินค้า Stud Bolt</p>
            <p class="muted">เข้าสู่ระบบด้วยบัญชีส่วนบุคคล เพื่อบันทึกชื่อผู้ทำรายการ</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>ชื่อผู้ใช้</label>
                <input class="input input-bordered" name="username" autocomplete="username" required placeholder="Username">

                <label>รหัสผ่าน</label>
                <input class="input input-bordered" type="password" name="password" autocomplete="current-password" required placeholder="Password">

                <button name="login" value="1" class="btn btn-primary full">เข้าสู่ระบบ</button>
            </form>

           
        </div>
    </body>
    </html>
