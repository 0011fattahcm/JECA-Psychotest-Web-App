<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/**
 * Gunakan attemptId yang sama untuk seluruh flow TAM (stimulus + soal)
 */
if (empty($_SESSION['attempt_id_tam'])) {
  $_SESSION['attempt_id_tam'] = bin2hex(random_bytes(16));
}
$attemptId = $_SESSION['attempt_id_tam'];

$displayMinutes = isset($package['duration_display']) ? (int)$package['duration_display'] : 5;
if ($displayMinutes <= 0) $displayMinutes = 5;

$imagePath = (!empty($package['image_path'])) ? $package['image_path'] : null;

// WAJIB: endAtTs dikirim dari controller (resume). fallback aman:
$endAtTs = $endAtTs ?? (time() + ($displayMinutes * 60));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tes Aspek Memori - Stimulus</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900 flex justify-center">

<!-- Warning toast -->
<div id="ac-warn"
     class="hidden fixed top-4 left-1/2 -translate-x-1/2 z-[10001]
            rounded-2xl bg-amber-500 text-white px-4 py-2 text-sm shadow-lg">
</div>

<!-- AntiCheat Camera Widget -->
<div class="fixed bottom-4 right-4 z-[9999] w-56 space-y-2">
  <div id="ac-badge"
       class="hidden items-center gap-2 rounded-xl bg-slate-900/90 text-white px-3 py-2 text-[11px]">
    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
    <span>Kamera aktif</span>
  </div>
  <video id="ac-cam" class="w-full rounded-2xl bg-black aspect-video" autoplay muted playsinline></video>
</div>

<!-- Block overlay -->
<div id="ac-block"
     class="hidden fixed inset-0 z-[10000] bg-slate-900/70 backdrop-blur-sm">
  <div class="w-full h-full flex items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-sm p-5 space-y-3">
      <h2 class="text-sm font-semibold text-slate-900">Tes diblokir</h2>
      <p class="text-[12px] text-slate-600 leading-relaxed" id="ac-block-msg">
        Izin kamera wajib untuk mengerjakan tes. Aktifkan izin kamera pada browser lalu refresh halaman ini.
      </p>
      <button type="button"
              onclick="location.reload()"
              class="w-full rounded-2xl bg-indigo-500 text-white text-sm font-semibold py-2.5 hover:bg-indigo-600">
        Refresh
      </button>
    </div>
  </div>
</div>

<main class="w-full max-w-2xl px-4 py-6">
  <header class="flex items-start justify-between mb-4">
    <div>
      <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-500 uppercase">
        JECA PSYCHOTEST
      </p>
      <h1 class="mt-1 text-lg font-semibold text-slate-900">
        Tes Aspek Memori
      </h1>
      <p class="text-[11px] text-slate-600 mt-0.5">
        Tahap stimulus • Waktu lihat:
        <span class="font-semibold text-slate-900"><?= (int)$displayMinutes ?> menit</span>
      </p>
    </div>
    <div class="text-right">
      <p class="text-[11px] text-slate-500">Sisa waktu</p>
      <p id="timer-text" class="font-mono text-lg font-semibold text-rose-600">--</p>
    </div>
  </header>

  <section class="bg-white rounded-3xl border border-slate-200 shadow-sm px-5 py-4">
    <p class="text-[12px] font-semibold text-slate-900">
      Perhatikan stimulus berikut dengan seksama.
    </p>
    <p class="mt-1 text-[11px] text-slate-600 leading-relaxed">
      Setelah waktu habis, Anda akan langsung diarahkan ke halaman soal.
      <span class="font-semibold text-slate-900">Anda tidak dapat kembali ke halaman stimulus ini.</span>
    </p>

    <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50/60
                flex items-center justify-center min-h-[260px] px-3 py-3">
      <?php if ($imagePath): ?>
        <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
             alt="Stimulus Tes Aspek Memori"
             class="max-h-[360px] max-w-full rounded-2xl border border-slate-200 object-contain">
      <?php else: ?>
        <p class="text-[12px] text-slate-500 text-center max-w-xs">
          Stimulus belum diunggah oleh admin. Silakan hubungi admin sistem.
        </p>
      <?php endif; ?>
    </div>

    <p class="mt-4 text-[11px] text-slate-500">
      Harap tetap berada di halaman ini hingga waktu habis.
    </p>
  </section>
</main>

<script>
  window.__TAM_END_AT__ = <?= (int)$endAtTs ?>;

  // Anti BFCache (supaya tombol back tidak balikin stimulus)
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) window.location.reload();
  });

  (function(){
    const endAt = window.__TAM_END_AT__ * 1000;
    const el = document.getElementById('timer-text');

    function fmt(sec){
      const m=Math.floor(sec/60), s=sec%60;
      return String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    }

    function tick(){
      let r = Math.ceil((endAt - Date.now())/1000);
      if (r < 0) r = 0;
      if (el) el.textContent = fmt(r);
      if (r <= 0) location.href='index.php?page=user-tam-test';
    }

    tick();
    setInterval(tick,1000);
  })();
</script>

<script>
(function () {
  const LOGIN_URL = "index.php?page=user-login&error=disabled&msg=" + encodeURIComponent("Akun Anda dinonaktifkan oleh admin.");

  async function ping() {
    try {
      const res = await fetch("index.php?page=ajax-user-status&test=TAM", {
        cache: "no-store",
        headers: { "Accept": "application/json" }
      });
      if (res.status === 403) return (window.location.href = LOGIN_URL);

      const data = await res.json().catch(() => null);
      if (data && data.active === 0) window.location.href = LOGIN_URL;
    } catch (e) {}
  }

  ping();
  setInterval(ping, 2500);
})();
</script>

<!-- AntiCheatLite: PATH WAJIB RELATIVE (tanpa leading slash) -->
<script src="assets/JS/antiCheatLite.js?v=<?= time() ?>"></script>
<script>
(function () {
  const warnEl   = document.getElementById('ac-warn');
  const blockEl  = document.getElementById('ac-block');
  const blockMsg = document.getElementById('ac-block-msg');

  function showWarn(count, limit) {
    if (!warnEl) return;
    warnEl.textContent = `Peringatan ${count}/${limit}: Jangan pindah tab / minimize. Lebih dari ${limit}x akan dianggap melakukan kecurangan.`;
    warnEl.classList.remove('hidden');
    clearTimeout(warnEl.__t);
    warnEl.__t = setTimeout(() => warnEl.classList.add('hidden'), 1800);
  }

  function failClosed(reason) {
    if (blockMsg) blockMsg.textContent = "Sistem pengawas tidak aktif: " + reason + ". Silakan refresh halaman.";
    if (blockEl) blockEl.classList.remove('hidden');
  }

  if (!window.AntiCheatLite || !window.AntiCheatLite.init) {
    failClosed('antiCheatLite.js gagal dimuat (cek assets/JS/antiCheatLite.js)');
    return;
  }

  const ac = window.AntiCheatLite.init({
    attemptId: <?= json_encode($attemptId, JSON_UNESCAPED_UNICODE) ?>,
    testCode: "TAM",
    postUrl: "index.php?page=ajax-anti-cheat",

    warningLimit: 3,
    maxViolations: 4,
    strikeCooldownMs: 1500,

    cameraRequired: true,
    cameraVideoEl: document.getElementById('ac-cam'),
    cameraIndicatorEl: document.getElementById('ac-badge'),

    onViolation: ({count, warningLimit, willInvalidate}) => {
      if (!willInvalidate && count <= warningLimit) showWarn(count, warningLimit);
    },

    onBlocked: ({reason}) => {
      if (blockMsg) {
        if (reason === "INSECURE_CONTEXT") blockMsg.textContent = "Izin kamera wajib. Gunakan HTTPS/localhost lalu refresh.";
        if (reason === "CAMERA_DENIED" || reason === "CAMERA_UNSUPPORTED") blockMsg.textContent = "Izin kamera wajib. Aktifkan izin kamera lalu refresh.";
      }
      if (blockEl) blockEl.classList.remove('hidden');
    },

    onInvalidate: () => {
      // Stimulus tidak punya submit, jadi keluar flow
      window.location.href = "index.php?page=user-dashboard";
    }
  });

  ac.start();
})();
</script>

</body>
</html>
