<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (empty($_SESSION['attempt_id_tam'])) {
  $_SESSION['attempt_id_tam'] = bin2hex(random_bytes(16));
}
$attemptId = $_SESSION['attempt_id_tam'];

$answerMinutes = isset($package['duration_answer']) ? (int)$package['duration_answer'] : 15;
if ($answerMinutes <= 0) $answerMinutes = 15;

// WAJIB: dari controller (fallback aman)
$endAtTs = $endAtTs ?? (time() + ($answerMinutes * 60));
$savedAnswers = $savedAnswers ?? [];

// Controller kirim $questions sebagai array stabil (berdasarkan question_ids)
$questionsArray = is_array($questions) ? $questions : [];

// TAM max 24 soal
$questionsArray = array_slice($questionsArray, 0, 24);
$totalQuestions = count($questionsArray);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tes Aspek Memori - Soal</title>
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

<!-- Block overlay (fail-closed + camera required) -->
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
              class="w-full rounded-2xl bg-indigo-600 text-white text-sm font-semibold py-2.5 hover:bg-indigo-700">
        Refresh
      </button>
    </div>
  </div>
</div>

<main class="w-full max-w-2xl px-4 py-6 space-y-4">

  <!-- Header -->
  <header class="flex items-start justify-between">
    <div>
      <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-500 uppercase">
        JECA PSYCHOTEST
      </p>
      <h1 class="mt-1 text-lg font-semibold text-slate-900">
        Tes Aspek Memori
      </h1>
      <p class="text-[11px] text-slate-600 mt-0.5">
        Tahap soal • Jumlah soal:
        <span class="font-semibold text-slate-900"><?= (int)$totalQuestions ?></span>
        • Durasi jawab:
        <span class="font-semibold text-slate-900"><?= (int)$answerMinutes ?> menit</span>
      </p>
    </div>
    <div class="text-right">
      <p class="text-[11px] text-slate-500">Sisa waktu</p>
      <p id="timer-text" class="font-mono text-lg font-semibold text-rose-600">--</p>
    </div>
  </header>

  <!-- Info -->
  <section class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px]">
    <p class="text-slate-800 font-semibold">Petunjuk pengerjaan</p>
    <p class="mt-1 text-slate-600 leading-relaxed">
      Jawablah seluruh soal berdasarkan stimulus yang telah Anda lihat sebelumnya.
      Pilih satu jawaban yang paling tepat untuk setiap soal.
    </p>
    <p class="mt-1 text-slate-600 leading-relaxed">
      Jika waktu habis, jawaban Anda akan <span class="font-semibold text-slate-900">tersimpan otomatis</span>
      dan tes dianggap selesai.
    </p>
  </section>

  <!-- FORM TAM -->
  <form id="tam-form"
        action="index.php?page=user-tam-submit"
        method="post"
        class="space-y-4 pb-6">

    <?php
    $number = 1;
    foreach ($questionsArray as $q):
      $qid = (int)($q['id'] ?? 0);
    ?>
      <section class="bg-white border border-slate-200 rounded-2xl p-4 space-y-3">
        <div class="flex items-start gap-3">
          <div class="flex-shrink-0 mt-0.5 w-6 h-6 rounded-full bg-indigo-500 text-white
                      flex items-center justify-center text-[11px] font-semibold">
            <?= $number ?>
          </div>
          <div class="flex-1">
            <p class="text-[13px] leading-snug text-slate-900">
              <?= nl2br(htmlspecialchars($q['question'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
            </p>
          </div>
        </div>

        <div class="mt-2 space-y-2">
          <?php
          $options = [
            'A' => $q['option_a'] ?? '',
            'B' => $q['option_b'] ?? '',
            'C' => $q['option_c'] ?? '',
            'D' => $q['option_d'] ?? '',
          ];
          foreach ($options as $optKey => $optText):
            if ($optText === '' || $optText === null) continue;
          ?>
            <label class="option-btn flex items-center gap-2 rounded-xl border border-slate-200
                          bg-white px-3 py-2 text-[13px] text-slate-800 cursor-pointer
                          hover:border-indigo-300 hover:bg-indigo-50/40 transition"
                   data-question-id="<?= (int)$qid ?>">
              <input type="radio"
                     class="answer-input peer hidden"
                     name="answer[<?= (int)$qid ?>]"
                     value="<?= htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') ?>"
                     data-question-id="<?= (int)$qid ?>">
              <span class="flex-shrink-0 w-6 h-6 rounded-full border border-slate-300
                           flex items-center justify-center text-[11px]
                           text-slate-600 peer-checked:border-emerald-500">
                <?= htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') ?>.
              </span>
              <span class="flex-1">
                <?= htmlspecialchars($optText, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>
    <?php
      $number++;
    endforeach;
    ?>

    <div class="pt-2">
      <p class="text-[11px] text-slate-500 mb-2">
        Pastikan semua jawaban sudah terisi. Tes akan otomatis dikirim jika waktu habis.
      </p>
      <button type="button"
              id="submit-btn"
              class="w-full rounded-xl bg-emerald-600 text-white text-sm font-semibold
                     py-2.5 shadow-sm shadow-emerald-500/30
                     hover:bg-emerald-700 active:scale-[0.99] transition">
        Selesaikan Tes Aspek Memori
      </button>
    </div>
  </form>

  <!-- Popup Konfirmasi -->
  <div id="confirm-overlay"
       class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden items-center justify-center z-40">
    <div class="bg-white rounded-2xl shadow-lg max-w-sm w-[90%] p-4">
      <h2 class="text-sm font-semibold text-slate-900">Selesaikan tes sekarang?</h2>
      <p class="mt-1 text-[12px] text-slate-600 leading-relaxed">
        Setelah dikirim, jawaban Tes Aspek Memori tidak dapat diubah lagi.
        Pastikan Anda sudah memeriksa seluruh jawaban.
      </p>
      <div class="mt-4 flex items-center justify-end gap-2">
        <button type="button"
                id="cancel-submit"
                class="px-3 py-1.5 rounded-full border border-slate-300 text-[12px] text-slate-700
                       bg-white hover:bg-slate-100">
          Kembali mengerjakan
        </button>
        <button type="button"
                id="confirm-submit"
                class="px-3 py-1.5 rounded-full bg-emerald-600 text-[12px] text-white
                       hover:bg-emerald-700">
          Ya, kirim jawaban
        </button>
      </div>
    </div>
  </div>

</main>

<script>
  window.__TAM_END_AT__ = <?= (int)$endAtTs ?>;
  window.__TAM_SAVED__  = <?= json_encode($savedAnswers, JSON_UNESCAPED_UNICODE) ?>;

  // Anti BFCache back-button
  window.addEventListener('pageshow', function(e){
    if (e.persisted) window.location.reload();
  });

  (function(){
    const formEl    = document.getElementById('tam-form');
    const timerEl   = document.getElementById('timer-text');

    const submitBtn = document.getElementById('submit-btn');
    const confirmOverlay = document.getElementById('confirm-overlay');
    const cancelSubmit   = document.getElementById('cancel-submit');
    const confirmSubmit  = document.getElementById('confirm-submit');

    // SINGLE SOURCE: FORCE SUBMIT (dipakai timer & anti-cheat)
    window.__TAM_FORCE_SUBMIT__ = function () {
      if (!formEl || formEl.dataset.submitted) return;
      if (confirmOverlay) {
        confirmOverlay.classList.add('hidden');
        confirmOverlay.classList.remove('flex');
      }
      formEl.dataset.submitted = '1';
      formEl.submit();
    };

    // Modal submit manual
    if (submitBtn && confirmOverlay) {
      submitBtn.addEventListener('click', function(){
        if (formEl && formEl.dataset.submitted) return;
        confirmOverlay.classList.remove('hidden');
        confirmOverlay.classList.add('flex');
      });
    }
    if (cancelSubmit && confirmOverlay) {
      cancelSubmit.addEventListener('click', function(){
        confirmOverlay.classList.add('hidden');
        confirmOverlay.classList.remove('flex');
      });
    }
    if (confirmSubmit && formEl) {
      confirmSubmit.addEventListener('click', function(){
        if (formEl.dataset.submitted) return;
        formEl.dataset.submitted = '1';
        formEl.submit();
      });
    }

    function applySelectionStyles(inputEl){
      const qid = inputEl.dataset.questionId;
      document.querySelectorAll('.option-btn[data-question-id="' + qid + '"]').forEach(label => {
        label.classList.remove('border-emerald-500','bg-emerald-50','ring-2','ring-emerald-300','text-emerald-900');
        label.classList.add('border-slate-200','bg-white','text-slate-800');
      });
      const selectedLabel = inputEl.closest('.option-btn');
      if (selectedLabel) {
        selectedLabel.classList.remove('border-slate-200','bg-white','text-slate-800');
        selectedLabel.classList.add('border-emerald-500','bg-emerald-50','ring-2','ring-emerald-300','text-emerald-900');
      }
    }

    function collectAnswers(){
      const out = {};
      document.querySelectorAll('input[type="radio"]:checked').forEach(r => {
        const name = r.getAttribute('name') || '';
        const m = name.match(/^answer\[(\d+)\]$/);
        if (!m) return;
        out[m[1]] = r.value;
      });
      return out;
    }

    // Restore jawaban
    const saved = window.__TAM_SAVED__ || {};
    Object.keys(saved).forEach((qid) => {
      const val = String(saved[qid]);
      const el = document.querySelector(`input[type="radio"][name="answer[${qid}]"][value="${val}"]`);
      if (el) { el.checked = true; applySelectionStyles(el); }
    });

    // Autosave
    let saveTimer = null;
    function scheduleSave(){
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(doSave, 400);
    }

    async function doSave(){
      if (!formEl || formEl.dataset.submitted) return;
      const payload = collectAnswers();
      try{
        await fetch('index.php?page=ajax-tam-progress', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({answers_json: JSON.stringify(payload)}).toString(),
          keepalive: true
        });
      } catch(e){}
    }

    document.querySelectorAll('.answer-input').forEach(input => {
      input.addEventListener('change', function(){
        applySelectionStyles(this);
        scheduleSave();
      });
    });

    setInterval(doSave, 10000);

    window.addEventListener('beforeunload', function(){
      if (!formEl || formEl.dataset.submitted) return;
      const payload = collectAnswers();
      const dataStr = new URLSearchParams({answers_json: JSON.stringify(payload)}).toString();
      if (navigator.sendBeacon) {
        const blob = new Blob([dataStr], {type:'application/x-www-form-urlencoded'});
        navigator.sendBeacon('index.php?page=ajax-tam-progress', blob);
      }
    });

    // Timer resume (server end_at)
    const endAtMs = (window.__TAM_END_AT__ || 0) * 1000;

    function fmt(sec){
      const m = Math.floor(sec/60);
      const s = sec%60;
      return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    }

    function tick(){
      if (!timerEl || !formEl) return;
      let remaining = Math.ceil((endAtMs - Date.now()) / 1000);
      if (remaining < 0) remaining = 0;

      timerEl.textContent = fmt(remaining);

      if (remaining <= 300) {
        timerEl.classList.remove('text-rose-600');
        timerEl.classList.add('text-red-600');
      }

      if (remaining <= 0) window.__TAM_FORCE_SUBMIT__();
    }

    tick();
    setInterval(tick, 1000);
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

<!-- AntiCheatLite: PATH RELATIVE, SINGLE INIT -->
<script src="assets/JS/antiCheatLite.js?v=<?= time() ?>"></script>
<script>
(function () {
  const warnEl   = document.getElementById('ac-warn');
  const blockEl  = document.getElementById('ac-block');
  const blockMsg = document.getElementById('ac-block-msg');

  function showWarn(count, limit) {
    if (!warnEl) return;
    warnEl.textContent = `Peringatan ${count}/${limit}: Jangan pindah tab / minimize. Lebih dari ${limit}x akan otomatis submit.`;
    warnEl.classList.remove('hidden');
    clearTimeout(warnEl.__t);
    warnEl.__t = setTimeout(() => warnEl.classList.add('hidden'), 1800);
  }

  function hardDisableForm() {
    const form = document.getElementById('tam-form');
    if (!form) return;
    form.querySelectorAll('input, button, select, textarea').forEach(el => el.disabled = true);
    form.querySelectorAll('label').forEach(el => el.style.pointerEvents = 'none');
  }

  function failClosed(reason) {
    if (blockMsg) blockMsg.textContent = "Sistem pengawas tidak aktif: " + reason + ". Silakan refresh halaman.";
    if (blockEl) blockEl.classList.remove('hidden');
    hardDisableForm();
  }

  if (!window.AntiCheatLite || !window.AntiCheatLite.init) {
    failClosed('antiCheatLite.js gagal dimuat (cek assets/JS/antiCheatLite.js)');
    return;
  }

  const ac = window.AntiCheatLite.init({
    attemptId: <?= json_encode($attemptId, JSON_UNESCAPED_UNICODE) ?>,
    testCode: "TAM",
    postUrl: "index.php?page=ajax-anti-cheat",

    // 1–3 warning, strike ke-4 => invalidate => autosubmit
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
        if (reason === "INSECURE_CONTEXT") {
          blockMsg.textContent = "Izin kamera wajib. Gunakan HTTPS/localhost lalu refresh.";
        } else if (reason === "CAMERA_DENIED") {
          blockMsg.textContent = "Izin kamera ditolak. Izinkan kamera pada browser lalu refresh.";
        } else if (reason === "CAMERA_UNSUPPORTED") {
          blockMsg.textContent = "Browser tidak mendukung kamera. Gunakan browser lain / update browser.";
        } else {
          blockMsg.textContent = "Tes diblokir: " + String(reason || "CAMERA_REQUIRED") + ". Refresh setelah memperbaiki izin.";
        }
      }
      if (blockEl) blockEl.classList.remove('hidden');
      hardDisableForm();
    },

    onInvalidate: () => {
      if (typeof window.__TAM_FORCE_SUBMIT__ === "function") return window.__TAM_FORCE_SUBMIT__();
      // fallback keras
      const form = document.getElementById('tam-form');
      if (form && !form.dataset.submitted) { form.dataset.submitted = '1'; form.submit(); }
    }
  });

  ac.start();
})();
</script>

</body>
</html>
