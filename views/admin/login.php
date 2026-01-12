<?php
if (!function_exists('e')) {
  function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$err = $error ?? '';
$map = [
  'method'  => 'Metode tidak valid.',
  'csrf'    => 'Permintaan tidak valid. Silakan refresh dan coba lagi.',
  'empty'   => 'Username dan password wajib diisi.',
  'invalid' => 'Kredensial tidak valid.',
  'locked'  => 'Terlalu banyak percobaan. Coba lagi beberapa menit.',
  'auth'    => 'Silakan login terlebih dahulu.',
];
$msg = $map[$err] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login - Psychotest</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
  <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg shadow-slate-200/80 p-6">
    <div class="mb-5">
      <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-500 uppercase">Psychotest System</p>
      <h1 class="mt-1 text-lg font-semibold text-slate-900">Admin Login</h1>
      <p class="mt-1 text-xs text-slate-600">Masuk untuk mengelola sistem.</p>
    </div>

    <?php if ($msg): ?>
      <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
        <?= e($msg) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="index.php?page=admin-login-process" class="space-y-3">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"/>

      <div>
        <label class="text-xs font-medium text-slate-700">Username</label>
        <input name="username" autocomplete="username"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none
                      focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300"
               placeholder="admin" />
      </div>

      <div>
        <label class="text-xs font-medium text-slate-700">Password</label>
        <input type="password" name="password" autocomplete="current-password"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none
                      focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300"
               placeholder="••••••••" />
      </div>

      <button type="submit"
              class="w-full rounded-xl bg-indigo-600 text-white text-sm font-semibold py-2
                     hover:bg-indigo-700 active:scale-[0.99] transition">
        Masuk
      </button>
    </form>
  </div>
</body>
</html>
