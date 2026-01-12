<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['attempt_id_kraeplin'])) {
  $_SESSION['attempt_id_kraeplin'] = bin2hex(random_bytes(16));
}
$attemptId = $_SESSION['attempt_id_kraeplin'];

$durationMinutes = isset($mainMinutes) ? (int)$mainMinutes : (int)($settings['duration'] ?? 15);
if ($durationMinutes < 1)  $durationMinutes = 1;
if ($durationMinutes > 30) $durationMinutes = 30;

$durationSeconds = $durationMinutes * 60;
$intervalSeconds = (int)($settings['interval_seconds'] ?? 10);

$endAtTs    = isset($endAtTs) ? (int)$endAtTs : (time() + $durationSeconds);
$seed       = isset($seed) ? (string)$seed : 'kraeplin-seed';
$savedLines = isset($savedLines) && is_array($savedLines) ? $savedLines : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tes Kraeplin - JECA Psychotest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    /* optional extra hardening (JS juga akan set user-select none) */
    html, body { -webkit-user-select:none; user-select:none; }
  </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex justify-center">

<!-- Toast Warning -->
<div id="ac-warn"
     class="hidden fixed top-4 left-1/2 -translate-x-1/2 z-[10001]
            items-center justify-center px-4 py-2 rounded-full
            bg-amber-500 text-white text-[12px] font-semibold shadow-lg">
</div>

<!-- Fail-Closed (kalau antiCheatLite.js gagal load) -->
<div id="ac-failclosed" class="hidden fixed inset-0 z-[10002] bg-slate-900/70 backdrop-blur-sm">
  <div class="w-full h-full flex items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-sm p-5 space-y-3">
      <h2 class="text-sm font-semibold text-slate-900">Sistem pengawas tidak aktif</h2>
      <p class="text-[12px] text-slate-600 leading-relaxed">
        Anti-cheat gagal dimuat. Refresh halaman. Jika tetap terjadi, hubungi admin.
      </p>
      <button type="button" onclick="location.reload()"
              class="w-full rounded-2xl bg-indigo-600 text-white text-sm font-semibold py-2.5 hover:bg-indigo-700">
        Refresh
      </button>
    </div>
  </div>
</div>

<!-- Camera Widget -->
<div class="fixed bottom-4 right-4 z-[9999] w-56 space-y-2">
  <div id="ac-badge"
       class="hidden items-center gap-2 rounded-xl bg-slate-900/90 text-white px-3 py-2 text-[11px]">
    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
    <span>Kamera aktif</span>
  </div>
  <video id="ac-cam" class="w-full rounded-2xl bg-black aspect-video" muted playsinline autoplay></video>
</div>

<!-- Block Overlay (kamera wajib) -->
<div id="ac-block"
     class="hidden fixed inset-0 z-[10000] bg-slate-900/70 backdrop-blur-sm">
  <div class="w-full h-full flex items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-sm p-5 space-y-3">
      <h2 class="text-sm font-semibold text-slate-900">Tes diblokir</h2>
      <p class="text-[12px] text-slate-600 leading-relaxed">
        Izin kamera wajib untuk mengerjakan tes. Aktifkan izin kamera pada browser lalu refresh halaman ini.
      </p>
      <button type="button"
              onclick="location.reload()"
              class="w-full rounded-2xl bg-indigo-600 text-white text-sm font-semibold py-2.5 hover:bg-indigo-700">
        Refresh
      </button>
    </div>
  </div>
</div>

<main class="w-full max-w-sm px-4 py-6">
  <div class="bg-white rounded-3xl shadow-lg shadow-slate-200/80 px-4 py-5 space-y-5">

    <!-- Header -->
    <header class="flex items-start justify-between">
      <div>
        <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-500 uppercase">
          JECA PSYCHOTEST
        </p>
        <h1 class="mt-1 text-lg font-semibold leading-snug">Tes Kraeplin</h1>
        <p class="mt-0.5 text-[11px] text-slate-600">
          Durasi tes:
          <span class="font-semibold"><?= (int)$durationMinutes ?> menit</span>
          • Interval skor:
          <span class="font-semibold"><?= (int)$intervalSeconds ?> detik</span>
        </p>
      </div>
      <div class="text-right">
        <p class="text-[11px] text-slate-500">Sisa waktu</p>
        <p id="kr-timer" class="font-mono text-lg font-semibold text-rose-600">
          <?= sprintf('%02d:00', (int)$durationMinutes) ?>
        </p>
      </div>
    </header>

    <!-- Info -->
    <section class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-[11px]">
      <p class="font-semibold text-slate-800">Petunjuk pengerjaan</p>
      <p class="mt-1 text-slate-600">
        Jumlahkan dua angka di tengah, lalu tekan <span class="font-semibold">digit terakhir</span> dari hasil penjumlahan.
        Tes akan berhenti otomatis ketika waktu habis.
      </p>
      <p class="mt-1 text-slate-600">Kamu bisa pakai keyboard angka (0–9).</p>
    </section>

    <!-- FORM TES -->
    <form id="kraeplin-form"
          action="index.php?page=user-kraeplin-submit"
          method="post"
          class="space-y-4">

      <input type="hidden" id="raw-lines-input" name="raw_lines" value="">

      <!-- Papan angka -->
      <section class="rounded-2xl bg-slate-900 text-white px-4 py-6 space-y-1">
        <div class="flex flex-col items-center justify-center space-y-3">
          <p id="digit-top" class="text-4xl font-semibold leading-none">7</p>
          <p class="text-xl font-semibold">+</p>
          <p id="digit-bottom" class="text-4xl font-semibold leading-none">3</p>
        </div>
        <p class="mt-4 text-xs text-center text-slate-300">
          Angka berikutnya:
          <span id="digit-next" class="font-semibold text-emerald-300">8</span>
        </p>
      </section>

      <!-- Keypad -->
      <section class="space-y-3">
        <div class="grid grid-cols-3 gap-3">
          <?php for ($i = 1; $i <= 9; $i++): ?>
            <button type="button"
                    data-digit="<?= $i ?>"
                    class="kr-key inline-flex items-center justify-center rounded-2xl bg-slate-100
                           text-slate-900 text-xl font-semibold py-3
                           shadow-sm hover:bg-indigo-100 active:scale-95 transition">
              <?= $i ?>
            </button>
          <?php endfor; ?>
        </div>
        <button type="button"
                data-digit="0"
                class="kr-key w-full inline-flex items-center justify-center rounded-2xl bg-slate-100
                       text-slate-900 text-xl font-semibold py-3
                       shadow-sm hover:bg-indigo-100 active:scale-95 transition">
          0
        </button>
      </section>

      <p class="text-[11px] text-slate-500 text-center">
        Kerjakan secepat dan seteliti mungkin. Jangan menutup halaman selama tes berlangsung.
      </p>
    </form>

  </div>
</main>

<!-- Overlay selesai -->
<div id="kr-finish-overlay"
     class="hidden fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm">
  <div class="w-full h-full flex items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-xs px-4 py-5 space-y-3">
      <h2 class="text-sm font-semibold text-slate-900">Tes Kraeplin selesai</h2>
      <p class="text-[11px] text-slate-600 leading-relaxed">
        Waktu sudah habis. Jawaban kamu sedang dikirim dan tes akan ditutup otomatis.
        Mohon jangan menutup halaman.
      </p>

      <div class="flex items-center gap-2">
        <div class="w-4 h-4 rounded-full border-2 border-slate-200 border-t-indigo-500 animate-spin"></div>
        <p id="kr-finish-countdown" class="text-[11px] text-slate-600">Mengirim dalam 1 detik...</p>
      </div>

      <button type="button"
              id="kr-submit-now"
              class="w-full rounded-2xl bg-indigo-500 text-white text-sm font-semibold
                     py-2.5 hover:bg-indigo-600 active:scale-[0.99] transition">
        Kirim sekarang
      </button>
    </div>
  </div>
</div>

<script>
  window.__KR_END_AT__       = <?= (int)$endAtTs ?>;
  window.__KR_DURATION_SEC__ = <?= (int)$durationSeconds ?>;
  window.__KR_INT_SEC__      = <?= (int)$intervalSeconds ?>;
  window.__KR_SEED__         = <?= json_encode($seed, JSON_UNESCAPED_UNICODE) ?>;
  window.__KR_SAVED_LINES__  = <?= json_encode($savedLines, JSON_UNESCAPED_UNICODE) ?>;

  // Anti BFCache
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) window.location.reload();
  });

  (function () {
    const form    = document.getElementById('kraeplin-form');
    const timerEl = document.getElementById('kr-timer');
    const hid     = document.getElementById('raw-lines-input');

    const topEl  = document.getElementById('digit-top');
    const botEl  = document.getElementById('digit-bottom');
    const nextEl = document.getElementById('digit-next');

    const keys = Array.from(document.querySelectorAll('.kr-key'));

    const finishOverlay = document.getElementById('kr-finish-overlay');
    const finishCountdownEl = document.getElementById('kr-finish-countdown');
    const submitNowBtn = document.getElementById('kr-submit-now');

    const endAtMs  = (window.__KR_END_AT__ || 0) * 1000;
    const totalSec = Math.max(60, window.__KR_DURATION_SEC__ || 900);
    const intSec = Math.max(1, window.__KR_INT_SEC__ || 10);
    const totalIntervals = Math.max(1, Math.ceil(totalSec / intSec));

    function xmur3(str) {
      let h = 1779033703 ^ str.length;
      for (let i = 0; i < str.length; i++) {
        h = Math.imul(h ^ str.charCodeAt(i), 3432918353);
        h = (h << 13) | (h >>> 19);
      }
      return function () {
        h = Math.imul(h ^ (h >>> 16), 2246822507);
        h = Math.imul(h ^ (h >>> 13), 3266489909);
        return (h ^= h >>> 16) >>> 0;
      };
    }
    function mulberry32(a) {
      return function () {
        let t = (a += 0x6D2B79F5);
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
      };
    }
    const seedStr = String(window.__KR_SEED__ || 'kraeplin-seed');
    const rng = mulberry32(xmur3(seedStr)());
    function randDigit() { return Math.floor(rng() * 10); }

    function fmt(sec) {
      sec = Math.max(0, sec | 0);
      const m = String(Math.floor(sec / 60)).padStart(2, '0');
      const s = String(sec % 60).padStart(2, '0');
      return `${m}:${s}`;
    }
    function clampInt(n) {
      n = parseInt(n, 10);
      return Number.isFinite(n) ? n : 0;
    }
    function normalizeLines(raw) {
      const arr = Array.isArray(raw) ? raw : [];
      return arr.map((it, idx) => ({
        interval: clampInt(it.interval || (idx + 1)),
        total_items: clampInt(it.total_items),
        correct: clampInt(it.correct),
        wrong: clampInt(it.wrong),
      }));
    }

    let saved = normalizeLines(window.__KR_SAVED_LINES__);
    if (saved.length > totalIntervals) saved = saved.slice(0, totalIntervals);

    const startMs = endAtMs - (totalSec * 1000);

    function elapsedSecNow() {
      const e = Math.floor((Date.now() - startMs) / 1000);
      return Math.max(0, Math.min(totalSec, e));
    }

    let curIdx = Math.min(totalIntervals - 1, Math.floor(elapsedSecNow() / intSec));
    let completed = saved.slice(0, curIdx);

    let curSaved = saved[curIdx] || { interval: curIdx + 1, total_items: 0, correct: 0, wrong: 0 };
    let itItems   = clampInt(curSaved.total_items);
    let itCorrect = clampInt(curSaved.correct);
    let itWrong   = clampInt(curSaved.wrong);

    const lines = completed.map((it, i) => ({
      interval: i + 1,
      total_items: it.total_items,
      correct: it.correct,
      wrong: it.wrong,
    }));

    let top = randDigit();
    let bot = randDigit();
    let nxt = randDigit();

    function advanceOneStep() {
      top = bot;
      bot = nxt;
      nxt = randDigit();
    }
    function renderDigits() {
      if (topEl)  topEl.textContent = String(top);
      if (botEl)  botEl.textContent = String(bot);
      if (nextEl) nextEl.textContent = String(nxt);
    }

    const totalDone = (() => {
      let sum = 0;
      for (const it of lines) sum += clampInt(it.total_items);
      sum += itItems;
      return sum;
    })();

    for (let i = 0; i < totalDone; i++) advanceOneStep();
    renderDigits();

    function snapshotLines() {
      const out = [];
      for (let i = 0; i < lines.length; i++) {
        out.push({
          interval: i + 1,
          total_items: clampInt(lines[i].total_items),
          correct: clampInt(lines[i].correct),
          wrong: clampInt(lines[i].wrong),
        });
      }
      out.push({
        interval: out.length + 1,
        total_items: clampInt(itItems),
        correct: clampInt(itCorrect),
        wrong: clampInt(itWrong),
      });
      while (out.length < totalIntervals) {
        out.push({ interval: out.length + 1, total_items: 0, correct: 0, wrong: 0 });
      }
      return out;
    }

    function disableKeys() {
      keys.forEach((b) => {
        b.disabled = true;
        b.classList.add('opacity-60');
      });
    }

    async function doSave() {
      const payload = snapshotLines();
      try {
        await fetch('index.php?page=ajax-kraeplin-progress', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({ raw_lines: JSON.stringify(payload) }),
          keepalive: true
        });
      } catch (e) {}
    }

    let saveT = null;
    function scheduleSave() {
      if (saveT) clearTimeout(saveT);
      saveT = setTimeout(doSave, 350);
    }

    function pushInterval() {
      lines.push({
        interval: lines.length + 1,
        total_items: itItems,
        correct: itCorrect,
        wrong: itWrong,
      });
      itItems = 0; itCorrect = 0; itWrong = 0;
    }

    let finished = false;

    function handleDigitInput(digit) {
      if (finished) return;

      const expected = (top + bot) % 10;
      itItems++;

      if (digit === expected) itCorrect++;
      else itWrong++;

      advanceOneStep();
      renderDigits();
      scheduleSave();
    }

    keys.forEach((btn) => {
      btn.addEventListener('click', () => {
        const d = parseInt(btn.dataset.digit, 10);
        if (!Number.isNaN(d)) handleDigitInput(d);
      });
    });

    window.addEventListener('keydown', (e) => {
      if (finished) return;
      if (e.key >= '0' && e.key <= '9') {
        e.preventDefault();
        handleDigitInput(parseInt(e.key, 10));
      }
    });

    let finishSubmitted = false;
    function showFinishOverlayAndSubmit() {
      if (finishSubmitted) return;
      finishSubmitted = true;

      disableKeys();

      while (lines.length < totalIntervals) pushInterval();

      const payload = snapshotLines();
      if (hid) hid.value = JSON.stringify(payload);

      if (finishOverlay) {
        finishOverlay.classList.remove('hidden');
        finishOverlay.classList.add('block');
      }

      let cd = 1;
      if (finishCountdownEl) finishCountdownEl.textContent = `Mengirim dalam ${cd} detik...`;

      const t = setInterval(() => {
        cd--;
        if (finishCountdownEl && cd > 0) finishCountdownEl.textContent = `Mengirim dalam ${cd} detik...`;
        if (cd <= 0) {
          clearInterval(t);
          if (form && !form.dataset.submitted) {
            form.dataset.submitted = '1';
            form.submit();
          }
        }
      }, 600);

      if (submitNowBtn) {
        submitNowBtn.onclick = function () {
          clearInterval(t);
          if (form && !form.dataset.submitted) {
            form.dataset.submitted = '1';
            form.submit();
          }
        };
      }
    }
    window.__KR_FORCE_FINISH__ = showFinishOverlayAndSubmit;

    function tick() {
      let remain = Math.ceil((endAtMs - Date.now()) / 1000);
      if (remain < 0) remain = 0;

      if (timerEl) timerEl.textContent = fmt(remain);

      const elapsed = elapsedSecNow();
      const shouldDoneIntervals = Math.min(totalIntervals, Math.floor(elapsed / intSec));
      while (lines.length < shouldDoneIntervals) pushInterval();

      if (remain <= 0 && !finished) {
        finished = true;
        showFinishOverlayAndSubmit();
      }
    }

    setInterval(doSave, 10000);
    window.addEventListener('beforeunload', function () {
      const payload = snapshotLines();
      const data = new URLSearchParams({ raw_lines: JSON.stringify(payload) });
      if (navigator.sendBeacon) {
        navigator.sendBeacon('index.php?page=ajax-kraeplin-progress', data);
      }
    });

    tick();
    setInterval(tick, 1000);
  })();
</script>

<script>
(function () {
  const LOGIN_URL = "index.php?page=user-login&error=disabled&msg=" + encodeURIComponent("Akun Anda dinonaktifkan oleh admin.");

  async function ping() {
    try {
      const res = await fetch("index.php?page=ajax-user-status&test=KRAEPLIN", {
        cache: "no-store",
        headers: { "Accept": "application/json" }
      });

      if (res.status === 403) {
        window.location.href = LOGIN_URL;
        return;
      }

      const data = await res.json().catch(() => null);
      if (data && data.active === 0) window.location.href = LOGIN_URL;
    } catch (e) {}
  }

  ping();
  setInterval(ping, 2500);
})();
</script>

<script src="assets/JS/antiCheatLite.js?v=<?= time() ?>"></script>
<script>
(function () {
  const failClosed = document.getElementById('ac-failclosed');
  if (!window.AntiCheatLite || !window.AntiCheatLite.init) {
    if (failClosed) failClosed.classList.remove('hidden');
    return;
  }

  const warnEl  = document.getElementById('ac-warn');
  const blockEl = document.getElementById('ac-block');

  function showWarn(count, limit) {
    if (!warnEl) return;
    warnEl.textContent = `Peringatan ${count}/${limit}: Jangan pindah tab / minimize. Lebih dari ${limit}x akan otomatis submit.`;
    warnEl.classList.remove('hidden');
    warnEl.classList.add('flex');
    clearTimeout(warnEl.__t);
    warnEl.__t = setTimeout(() => {
      warnEl.classList.add('hidden');
      warnEl.classList.remove('flex');
    }, 1800);
  }

  const ac = window.AntiCheatLite.init({
    attemptId: <?= json_encode($attemptId, JSON_UNESCAPED_UNICODE) ?>,
    testCode: "KRAEPLIN",
    postUrl: "index.php?page=ajax-anti-cheat",

    // Warning 1..3, pelanggaran ke-4 => invalidate => autosubmit
    warningLimit: 3,
    maxViolations: 4,
    strikeCooldownMs: 1500,

    cameraRequired: true,
    cameraVideoEl: document.getElementById('ac-cam'),
    cameraIndicatorEl: document.getElementById('ac-badge'),

    onViolation: ({count, warningLimit, willInvalidate}) => {
      if (!willInvalidate && count <= warningLimit) showWarn(count, warningLimit);
    },

    onBlocked: () => {
      if (blockEl) blockEl.classList.remove('hidden');
      // disable input tes
      document.querySelectorAll('#kraeplin-form button').forEach(el => el.disabled = true);
    },

    onInvalidate: () => {
      if (typeof window.__KR_FORCE_FINISH__ === "function") window.__KR_FORCE_FINISH__();
      else location.href = "index.php?page=user-dashboard";
    }
  });

  ac.start();
})();
</script>

</body>
</html>
