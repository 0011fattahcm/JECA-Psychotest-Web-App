<?php
// Trial selalu 1 menit, 2 interval
$trialMinutes        = 1;
$trialDurationSec    = $trialMinutes * 60;  // 60
$trialIntervalSec    = 30;                  // 2 interval

// Harusnya controller set ini (end_at progress). Fallback aman:
$endAtTs = isset($endAtTs) ? (int)$endAtTs : (time() + $trialDurationSec);

// seed dari payload progress kalau ada (biar deterministik). Fallback aman:
$seed = isset($seed) ? (string)$seed : 'trial-seed';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Latihan Tes Kraeplin - JECA Psychotest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 flex justify-center">

<main class="w-full max-w-sm px-4 py-6">
  <div class="bg-white rounded-3xl shadow-lg shadow-slate-200/80 px-4 py-5 space-y-5 relative">

    <!-- Header -->
    <header class="flex items-start justify-between">
      <div>
        <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-500 uppercase">
          JECA PSYCHOTEST
        </p>
        <h1 class="mt-1 text-lg font-semibold leading-snug">
          Latihan Tes Kraeplin
        </h1>
        <p class="mt-0.5 text-[11px] text-slate-600">
          Durasi latihan: <span class="font-semibold">1 menit</span> •
          Interval skor: <span class="font-semibold">30 detik</span>
        </p>
      </div>
      <div class="text-right">
        <p class="text-[11px] text-slate-500">Sisa waktu</p>
        <p id="kr-timer"
           class="font-mono text-lg font-semibold text-rose-600">
          01:00
        </p>
      </div>
    </header>

    <!-- Info -->
    <section class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-[11px]">
      <p class="font-semibold text-slate-800">Ini hanya latihan</p>
      <p class="mt-1 text-slate-600">
        Latihan ini berlangsung selama <span class="font-semibold">1 menit</span>.
        Jumlahkan dua angka di tengah lalu tekan <span class="font-semibold">digit terakhir</span>
        dari hasil penjumlahan.
      </p>
      <p class="mt-1 text-slate-600">
        Kamu juga bisa pakai keyboard angka (0–9).
      </p>
    </section>

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
      Kerjakan santai. Setelah latihan selesai, kamu bisa memulai tes Kraeplin sebenarnya.
    </p>

    <!-- Overlay selesai trial -->
    <!-- Penting: overlay benar-benar hidden (tidak flex) saat awal, agar tidak menahan klik -->
    <div id="trial-done-overlay"
         class="hidden fixed inset-0 z-20 bg-slate-900/40 backdrop-blur-sm">
      <div class="w-full h-full flex items-center justify-center px-4">
        <div class="bg-white rounded-3xl shadow-xl w-full max-w-xs px-4 py-5 space-y-3">
          <h2 class="text-sm font-semibold text-slate-900">
            Latihan selesai
          </h2>
          <p class="text-[11px] text-slate-600">
            Kamu sudah menyelesaikan latihan selama 1 menit.
            Sekarang saatnya mengerjakan tes Kraeplin yang sebenarnya.
          </p>

          <!-- Ini yang kamu minta: lanjut kalau user klik tombol ini -->
          <a href="index.php?page=user-kraeplin-start"
             class="block w-full text-center rounded-2xl bg-indigo-500 text-white
                    text-sm font-semibold py-2.5 hover:bg-indigo-600
                    active:scale-[0.99] transition">
            Mulai Tes Kraeplin Sekarang
          </a>
        </div>
      </div>
    </div>

  </div>
</main>

<script>
  window.__KR_TRIAL_END_AT__ = <?= (int)$endAtTs ?>;
  window.__KR_TRIAL_TOTAL__  = <?= (int)$trialDurationSec ?>; // 60
  window.__KR_TRIAL_INT__    = <?= (int)$trialIntervalSec ?>; // 30
  window.__KR_SEED__         = <?= json_encode($seed, JSON_UNESCAPED_UNICODE) ?>;

  // Anti BFCache back-button
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) window.location.reload();
  });

  (function () {
    const endAtMs  = window.__KR_TRIAL_END_AT__ * 1000;
    const totalSec = window.__KR_TRIAL_TOTAL__ || 60;
    const intSec   = window.__KR_TRIAL_INT__ || 30;
    const totalIntervals = Math.max(1, Math.floor(totalSec / intSec));

    const timerEl = document.getElementById('kr-timer');
    const topEl   = document.getElementById('digit-top');
    const botEl   = document.getElementById('digit-bottom');
    const nextEl  = document.getElementById('digit-next');

    const overlay = document.getElementById('trial-done-overlay');
    const keys    = Array.from(document.querySelectorAll('.kr-key'));

    // ====== Seeded RNG (deterministik) ======
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
    const seedStr = String(window.__KR_SEED__ || 'trial-seed');
    const rng = mulberry32(xmur3(seedStr)());

    function randDigit() {
      return Math.floor(rng() * 10);
    }

    // ====== State trial ======
    let top = randDigit();
    let bot = randDigit();
    let nxt = randDigit();

    let intervalIdx = 0; // 0..(totalIntervals-1)
    let itItems = 0, itCorrect = 0, itWrong = 0;
    const lines = [];

    let finished = false;

    function renderDigits() {
      if (topEl)  topEl.textContent  = String(top);
      if (botEl)  botEl.textContent  = String(bot);
      if (nextEl) nextEl.textContent = String(nxt);
    }

    function fmt(sec) {
      sec = Math.max(0, sec | 0);
      const m = String(Math.floor(sec / 60)).padStart(2, '0');
      const s = String(sec % 60).padStart(2, '0');
      return `${m}:${s}`;
    }

    function pushInterval() {
      lines.push({
        interval: intervalIdx + 1,
        total_items: itItems,
        correct: itCorrect,
        wrong: itWrong,
      });
      itItems = 0; itCorrect = 0; itWrong = 0;
    }

    function disableKeys() {
      keys.forEach((b) => {
        b.disabled = true;
        b.classList.add('opacity-60');
      });
    }

    function showDoneOverlay() {
      if (!overlay) return;
      overlay.classList.remove('hidden');
      overlay.classList.add('block'); // penting: jangan "flex" di sini (wrapper sudah flex)
    }

    function handleInput(digit) {
      if (finished) return;

      const expected = (top + bot) % 10;
      itItems++;

      if (digit === expected) itCorrect++;
      else itWrong++;

      // shift angka
      top = bot;
      bot = nxt;
      nxt = randDigit();
      renderDigits();
    }

    // Click keypad
    keys.forEach((btn) => {
      btn.addEventListener('click', () => {
        const d = parseInt(btn.dataset.digit, 10);
        if (!Number.isNaN(d)) handleInput(d);
      });
    });

    // Keyboard 0-9
    window.addEventListener('keydown', (e) => {
      if (finished) return;
      if (e.key >= '0' && e.key <= '9') {
        e.preventDefault();
        handleInput(parseInt(e.key, 10));
      }
    });

    // ====== Timer strict (pakai end_at DB) + interval boundary ======
    function tick() {
      const remain = Math.ceil((endAtMs - Date.now()) / 1000);

      if (timerEl) {
        timerEl.textContent = fmt(remain);
        if (remain <= 10) {
          timerEl.classList.remove('text-rose-600');
          timerEl.classList.add('text-red-600');
        }
      }

      // interval boundary berdasarkan start = end - total
      const startMs = endAtMs - (totalSec * 1000);
      const elapsed = Math.max(0, Math.min(totalSec, Math.floor((Date.now() - startMs) / 1000)));
      const newIntervalIdx = Math.min(totalIntervals, Math.floor(elapsed / intSec));

      while (intervalIdx < newIntervalIdx && intervalIdx < totalIntervals) {
        pushInterval();
        intervalIdx++;
      }

      if (remain <= 0 && !finished) {
        finished = true;

        // commit interval terakhir bila belum
        while (intervalIdx < totalIntervals) {
          pushInterval();
          intervalIdx++;
        }

        disableKeys();
        showDoneOverlay();

        // Tidak auto lanjut ke test. User harus klik tombol.
      }
    }

    renderDigits();
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

      // Jika server sudah memaksa logout => 403
      if (res.status === 403) {
        window.location.href = LOGIN_URL;
        return;
      }

      const data = await res.json().catch(() => null);
      if (data && data.active === 0) {
        window.location.href = LOGIN_URL;
      }
    } catch (e) {
      // optional: abaikan error jaringan sementara
    }
  }

  // cek cepat lalu interval
  ping();
  setInterval(ping, 2500); // 2.5 detik (boleh 3000–5000ms)
})();
</script>


</body>
</html>
