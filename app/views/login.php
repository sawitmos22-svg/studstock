<?php if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<!doctype html>
<html lang="th" data-theme="corporate">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudBolt Stock | เข้าสู่ระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="<?= h(APP_PUBLIC_BASE) ?>assets/style.css">
    <style>
        body.login-bg {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(ellipse at 80% 20%, rgb(222 237 255 / 60%), transparent 40rem), #f3f6fb;
            font-family: 'Prompt', system-ui, sans-serif;
            padding: 1.5rem;
            margin: 0;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
        }
    </style>
</head>
<body class="login-bg">
    <div class="login-card card bg-base-100 shadow-2xl border border-base-200">
        <div class="card-body p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="brand-mark text-2xl">⚙</div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-base-content m-0">StudBolt <span class="text-primary">Stock</span></h1>
                    <p class="text-xs text-base-content/60 m-0">ระบบจัดการคลังสินค้า Stud Bolt</p>
                </div>
            </div>

            <p class="text-sm text-base-content/70 mt-1 mb-4">เข้าสู่ระบบด้วยบัญชีผู้ใช้เพื่อบันทึกประวัติการเบิก-รับเข้า</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error text-sm py-2 px-3 mb-4 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-5 w-5" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span><?= h($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <div>
                    <label class="label py-1"><span class="label-text font-semibold text-xs uppercase tracking-wider text-base-content/80">ชื่อผู้ใช้ (Username)</span></label>
                    <input class="input input-bordered w-full" name="username" autocomplete="username" required placeholder="manager หรือ staff" autofocus>
                </div>

                <div>
                    <label class="label py-1"><span class="label-text font-semibold text-xs uppercase tracking-wider text-base-content/80">รหัสผ่าน (Password)</span></label>
                    <div class="relative">
                        <input class="input input-bordered w-full pr-10" type="password" id="loginPassword" name="password" autocomplete="current-password" required placeholder="••••••••">
                        <button type="button" class="btn btn-ghost btn-xs absolute right-2 top-2.5 text-base-content/50" onclick="const p=document.getElementById('loginPassword'); p.type = p.type==='password'?'text':'password'; this.textContent = p.type==='password'?'👁':'🔒';">👁</button>
                    </div>
                </div>

                <button name="login" value="1" class="btn btn-primary w-full shadow-md hover:shadow-primary/25 mt-2">
                    เข้าสู่ระบบ →
                </button>
            </form>

            <div class="mt-4 rounded-lg bg-base-200/50 p-3 text-xs text-base-content/70 border border-base-300">
                ต้องสร้างบัญชีผู้ดูแลระบบในฐานข้อมูล Supabase ก่อนเข้าใช้งานครั้งแรก
            </div>
        </div>
    </div>
</body>
</html>
