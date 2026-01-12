<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Ambil error (bisa dari controller atau GET)
$error = $error ?? (string)($_GET['error'] ?? '');

// Flash message via GET
$flash = (string)($_GET['flash'] ?? '');
$msg   = trim((string)($_GET['msg'] ?? ''));

// Auto-detect inactive jika msg berisi kata dinonaktifkan tapi flash kosong
if ($msg !== '' && $flash === '' && stripos($msg, 'dinonaktifkan') !== false) {
  $flash = 'inactive';
}

$map = [
  'inactive' => [
    'wrap'  => 'border-rose-500/30 bg-rose-500/10 text-rose-100',
    'icon'  => 'text-rose-300',
    'title' => 'Akun Dinonaktifkan',
    'svg'   => 'M12 9v3m0 4h.01M12 3c4.97 0 9 4.03 9 9s-4.03 9-9 9-9-4.03-9-9 4.03-9 9-9z',
  ],
  'warning' => [
    'wrap'  => 'border-amber-500/30 bg-amber-500/10 text-amber-100',
    'icon'  => 'text-amber-300',
    'title' => 'Perhatian',
    'svg'   => 'M12 9v3m0 4h.01M10.29 3.86l-8.4 14.55A2 2 0 003.62 21h16.76a2 2 0 001.73-2.59l-8.4-14.55a2 2 0 00-3.42 0z',
  ],
  'success' => [
    'wrap'  => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100',
    'icon'  => 'text-emerald-300',
    'title' => 'Berhasil',
    'svg'   => 'M9 12.75L11.25 15 15 9.75M12 21a9 9 0 110-18 9 9 0 010 18z',
  ],
  'info' => [
    'wrap'  => 'border-sky-500/30 bg-sky-500/10 text-sky-100',
    'icon'  => 'text-sky-300',
    'title' => 'Info',
    'svg'   => 'M12 9h.01M11 12h1v5h1M12 21a9 9 0 110-18 9 9 0 010 18z',
  ],
];

// fallback kalau msg ada tapi flash tidak valid
if ($msg !== '' && !isset($map[$flash])) $flash = 'info';
$ui = $map[$flash] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login Peserta - JECA Psychotest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-50 flex items-center justify-center">

<div class="w-full max-w-sm px-4">
  <!-- Logo / Title -->
  <div class="mb-8 text-center">
    <p class="text-xs font-semibold tracking-[0.2em] text-indigo-400 uppercase">
      JECA Psychotest
    </p>
    <h1 class="mt-2 text-2xl font-semibold text-slate-50">
      Masuk sebagai Peserta
    </h1>
    <p class="mt-1 text-xs text-slate-400">
      Masukkan ID peserta yang diberikan oleh admin.
    </p>
  </div>

  <!-- Card -->
  <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-5 shadow-xl">

    <!-- FLASH MESSAGE (selalu bisa tampil walau tidak ada $error) -->
    <?php if ($msg !== '' && $ui): ?>
      <div id="flashMsg"
           role="alert"
           aria-live="polite"
           class="mb-4 rounded-2xl border px-4 py-3 <?= $ui['wrap'] ?>">
        <div class="flex items-start gap-3">
          <div class="mt-0.5 <?= $ui['icon'] ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="<?= h($ui['svg']) ?>" />
            </svg>
          </div>

          <div class="flex-1">
            <p class="text-sm font-semibold leading-tight"><?= h($ui['title']) ?></p>
            <p class="mt-0.5 text-sm opacity-90 leading-relaxed"><?= h($msg) ?></p>
          </div>

          <button type="button"
                  aria-label="Tutup notifikasi"
                  class="ml-2 inline-flex h-7 w-7 items-center justify-center rounded-full opacity-70 hover:opacity-100 hover:bg-white/10 transition"
                  onclick="(function(){const el=document.getElementById('flashMsg'); if(el) el.remove(); if(window.__flashTimer) clearTimeout(window.__flashTimer);})();">
            ✕
          </button>
        </div>
      </div>

      <script>
        // auto-hide 6 detik
        window.__flashTimer = setTimeout(() => {
          const el = document.getElementById('flashMsg');
          if (el) el.remove();
        }, 6000);
      </script>
    <?php endif; ?>


    <!-- NOTICE / ERROR BLOCK -->
    <?php if (!empty($error)): ?>

      <?php if ($error === 'test_closed'): ?>
        <!-- NOTICE: Test belum dibuka -->
        <div id="test-closed-box" class="mb-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3">
          <div class="flex items-start gap-3">
            <div class="mt-0.5 h-8 w-8 rounded-xl bg-amber-500/20 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-200" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.485 0l6.518 11.59c.75 1.334-.214 2.99-1.742 2.99H3.48c-1.528 0-2.492-1.656-1.742-2.99l6.518-11.59zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-7a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm font-semibold text-amber-100">Tes belum dibuka</p>
              <p class="mt-0.5 text-xs text-amber-100/80">
                Admin belum memulai Tes. Silakan coba lagi sesuai jadwal yang ditentukan.
              </p>
            </div>
          </div>
        </div>

      <?php else: ?>
        <!-- ERROR: Login -->
        <div class="mb-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3">
          <p class="text-sm font-semibold text-rose-100">Login gagal</p>
          <p class="mt-1 text-xs text-rose-100/80">
            <?php if ($error === 'empty'): ?>
              ID peserta tidak boleh kosong.
            <?php elseif ($error === 'notfound'): ?>
              ID peserta tidak ditemukan. Periksa kembali atau hubungi admin.
            <?php else: ?>
              Terjadi kesalahan saat login. Coba lagi.
            <?php endif; ?>
          </p>
        </div>
      <?php endif; ?>

    <?php endif; ?>


    <!-- Auto refresh jika test closed -->
    <?php if (!empty($error) && $error === 'test_closed'): ?>
      <script>
        (function(){
          async function check(){
            try{
              const res = await fetch('index.php?page=ajax-test-window-status', { cache: 'no-store' });
              const js = await res.json();
              if (js && js.is_open_now) {
                window.location.href = 'index.php?page=user-login';
              }
            }catch(e){}
          }
          setInterval(check, 5000);
        })();
      </script>
    <?php endif; ?>


    <form action="index.php?page=user-login-process" method="post" class="space-y-5">
      <!-- User Code -->
      <div class="space-y-1.5">
        <label for="user_code" class="block text-xs font-medium text-slate-200">
          ID Peserta
        </label>
        <div class="relative">
          <input
            type="text"
            id="user_code"
            name="user_code"
            required
            placeholder="Contoh: 15-20040720"
            class="block w-full rounded-xl border border-slate-700 bg-slate-900/80 px-4 py-2.5
                   text-sm text-slate-50 placeholder:text-slate-500
                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
          >
          <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
            <span class="text-[10px] font-mono text-slate-500">ID-YYYYMMDD</span>
          </div>
        </div>
        <p class="text-[11px] text-slate-500 leading-snug">
          Format ID dibuat oleh sistem, contoh:
          <span class="font-mono text-slate-200">15-20040720</span>.
          Jika belum punya ID, minta ke admin / LPK.
        </p>
      </div>

      <!-- Button -->
      <button type="submit"
              class="w-full inline-flex justify-center items-center rounded-xl bg-indigo-500
                     px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/30
                     hover:bg-indigo-600 active:bg-indigo-700 focus:outline-none focus:ring-2
                     focus:ring-offset-2 focus:ring-indigo-500 focus:ring-offset-slate-950">
        Masuk ke Dashboard
      </button>
    </form>

    <div class="mt-4 text-[11px] text-center text-slate-500">
      JECA Psychotest App • Webiste Version
    </div>

  </div>
</div>

</body>
</html>
