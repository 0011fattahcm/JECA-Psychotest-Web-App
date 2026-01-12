<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['attempt_id_tpa'])) {
  $_SESSION['attempt_id_tpa'] = bin2hex(random_bytes(16));
}
$attemptId = $_SESSION['attempt_id_tpa'];

$durationMinutes = $durationMinutes ?? 90;
$durationSeconds = (int)$durationMinutes * 60;

// Fallback aman bila controller belum set endAtTs/savedAnswers
$endAtTs = $endAtTs ?? (time() + $durationSeconds);
$savedAnswers = $savedAnswers ?? [];

// Flatten seluruh soal untuk navigasi + tampilan 1-per-1
$flatQuestions = [];
$runningIndex  = 1;

foreach ($sections as $sIndex => $sec) {
  foreach ($sec['questions'] as $q) {
    $flatQuestions[] = [
      'idx'            => $runningIndex,
      'section_index'  => $sIndex,
      'section_title'  => $sec['title'] ?? '',
      'section_sub'    => $sec['subtitle'] ?? '',
      'q'              => $q,
    ];
    $runningIndex++;
  }
}
$totalQuestions = $runningIndex - 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tes Potensi Akademik - JECA Psychotest</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-white text-slate-900 flex justify-center">

<!-- Toast Warning -->
<div id="ac-warn"
     class="hidden fixed top-4 left-1/2 -translate-x-1/2 z-[10001]
            rounded-2xl bg-amber-500 text-white px-4 py-2 text-sm shadow-lg">
</div>

<!-- Fail-Closed -->
<div id="ac-failclosed"
     class="hidden fixed inset-0 z-[10002] bg-slate-900/70 backdrop-blur-sm">
  <div class="w-full h-full flex items-center justify-center px-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-sm p-5 space-y-3">
      <h2 class="text-sm font-semibold text-slate-900">Sistem pengawas tidak aktif</h2>
      <p class="text-[12px] text-slate-600 leading-relaxed" id="ac-fail-msg">
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
  <video id="ac-cam" class="w-full rounded-2xl bg-black aspect-video" autoplay muted playsinline></video>
</div>

<!-- Block Overlay (kamera wajib) -->
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

<main class="w-full max-w-sm px-4 py-4 space-y-4">

  <!-- Header -->
  <header class="flex items-start justify-between">
    <div>
      <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-400 uppercase">
        JECA PSYCHOTEST
      </p>
      <h1 class="mt-1 text-base font-semibold text-slate-900">Tes Potensi Akademik</h1>
      <p class="text-[11px] text-slate-500">
        Total soal: <span class="font-semibold"><?= (int)$totalQuestions ?></span>
        • Waktu: <span class="font-semibold"><?= (int)$durationMinutes ?> menit</span>
      </p>
    </div>

    <div class="text-right">
      <p class="text-[11px] text-slate-500">Sisa waktu</p>
      <p id="timer-text" class="font-mono text-lg font-semibold text-rose-600">
        <?= sprintf('%02d:00', (int)$durationMinutes) ?>
      </p>
    </div>
  </header>

  <!-- Navigasi Soal (collapsible) -->
  <section class="bg-slate-50 border border-slate-200 rounded-2xl p-3">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-[11px] font-semibold text-slate-800">Navigasi Soal</p>
        <p class="text-[10px] text-slate-500">Sentuh nomor untuk lompat ke soal.</p>
      </div>
      <button type="button"
              id="toggle-nav"
              class="text-[11px] px-2 py-1 rounded-full border border-slate-300 text-slate-700
                     hover:bg-slate-100 active:scale-95 transition">
        Tampil / Sembunyikan
      </button>
    </div>

    <div id="nav-container" class="mt-3 grid grid-cols-8 gap-1 max-h-40 overflow-y-auto pr-1">
      <?php foreach ($flatQuestions as $fq): ?>
        <button type="button"
                data-qindex="<?= (int)$fq['idx'] ?>"
                data-question-id="<?= (int)$fq['q']['id'] ?>"
                id="nav-q-<?= (int)$fq['q']['id'] ?>"
                class="nav-item inline-flex items-center justify-center
                       text-[11px] w-8 h-8 rounded-full border border-slate-300
                       bg-white text-slate-700 hover:bg-slate-100">
          <?= (int)$fq['idx'] ?>
        </button>
      <?php endforeach; ?>
    </div>
    <p class="mt-2 text-[10px] text-slate-500">
      Nomor akan berubah <span class="text-emerald-600 font-semibold">hijau</span> bila sudah dijawab.
    </p>
  </section>

  <!-- FORM TPA -->
  <form id="tpa-form"
        action="index.php?page=user-tpa-submit"
        method="post"
        class="space-y-4 pb-6">

    <input type="hidden" name="category" value="tpa">
    <input type="hidden" name="session" value="all">
    <input type="hidden" name="total_questions" value="<?= (int)$totalQuestions ?>">

    <?php foreach ($flatQuestions as $fq): ?>
      <?php
        $idx = (int)$fq['idx'];
        $q   = $fq['q'];
        $qid = (int)$q['id'];
      ?>
      <section
        id="q-card-<?= $qid ?>"
        class="question-card bg-white border border-slate-200 rounded-2xl p-3 space-y-3 <?= $idx === 1 ? '' : 'hidden' ?>"
        data-qindex="<?= $idx ?>">

        <!-- Info Bagian -->
        <div class="mb-1">
          <p class="text-[11px] font-semibold text-indigo-500 uppercase">
            Bagian <?= (int)$fq['section_index'] + 1 ?>
          </p>
          <h2 class="text-sm font-semibold text-slate-900">
            <?= htmlspecialchars($fq['section_title'], ENT_QUOTES, 'UTF-8') ?>
          </h2>
          <?php if (!empty($fq['section_sub'])): ?>
            <p class="mt-0.5 text-[11px] text-slate-500">
              <?= htmlspecialchars($fq['section_sub'], ENT_QUOTES, 'UTF-8') ?>
            </p>
          <?php endif; ?>
        </div>

        <!-- Soal -->
        <article class="mt-1 rounded-2xl border border-slate-200 bg-slate-50/60 p-3 space-y-2">
          <div class="flex items-start gap-3">
            <div class="flex-shrink-0 mt-0.5 w-6 h-6 rounded-full bg-indigo-500
                        text-white flex items-center justify-center text-[11px] font-semibold">
              <?= $idx ?>
            </div>
            <div class="flex-1">
              <p class="text-[13px] leading-snug text-slate-900">
                <?= nl2br(htmlspecialchars($q['question_text'] ?? '', ENT_QUOTES, 'UTF-8')) ?>
              </p>

              <?php if (!empty($q['question_image'])): ?>
                <div class="mt-2">
                  <img src="<?= htmlspecialchars($q['question_image'], ENT_QUOTES, 'UTF-8') ?>"
                       alt="Gambar soal"
                       class="w-full rounded-xl border border-slate-200 object-contain">
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Opsi Jawaban -->
          <div class="mt-2 space-y-2">
            <?php foreach (['A','B','C','D'] as $optKey): ?>
              <?php
                $textKey  = 'option_' . strtolower($optKey) . '_text';
                $imageKey = 'option_' . strtolower($optKey) . '_image';

                $optText  = $q[$textKey]  ?? '';
                $optImage = $q[$imageKey] ?? null;

                if ($optText === '' && empty($optImage)) continue;
              ?>
              <label class="option-btn flex items-center gap-2 rounded-xl border border-slate-200
                            bg-white px-3 py-2 text-[13px] text-slate-800 cursor-pointer
                            hover:border-indigo-300 hover:bg-indigo-50/40 transition"
                     data-question-id="<?= $qid ?>">
                <input type="radio"
                       class="answer-input peer hidden"
                       name="answer[<?= $qid ?>]"
                       value="<?= htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') ?>"
                       data-question-id="<?= $qid ?>">
                <span class="flex-shrink-0 w-6 h-6 rounded-full border border-slate-300
                             flex items-center justify-center text-[11px]
                             text-slate-600 peer-checked:border-emerald-500">
                  <?= htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') ?>.
                </span>
                <span class="flex-1">
                  <?php if ($optText): ?>
                    <span><?= htmlspecialchars($optText, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                  <?php if ($optImage): ?>
                    <img src="<?= htmlspecialchars($optImage, ENT_QUOTES, 'UTF-8') ?>"
                         alt="Opsi <?= htmlspecialchars($optKey, ENT_QUOTES, 'UTF-8') ?>"
                         class="mt-1 w-full rounded-lg border border-slate-200 object-contain">
                  <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </article>

        <!-- Tombol Sebelumnya / Selanjutnya -->
        <div class="flex items-center justify-between pt-2">
          <button type="button"
                  class="nav-prev inline-flex items-center justify-center px-3 py-1.5 rounded-full
                         border border-slate-300 text-[12px] text-slate-700
                         bg-white hover:bg-slate-100 active:scale-95 transition"
                  data-qindex="<?= $idx ?>">
            ‹ Sebelumnya
          </button>
          <button type="button"
                  class="nav-next inline-flex items-center justify-center px-3 py-1.5 rounded-full
                         border border-indigo-400 bg-indigo-500 text-white text-[12px]
                         hover:bg-indigo-600 active:scale-95 transition"
                  data-qindex="<?= $idx ?>">
            Selanjutnya ›
          </button>
        </div>
      </section>
    <?php endforeach; ?>

    <!-- Tombol Submit Manual -->
    <div class="pt-2">
      <p class="text-[11px] text-slate-500 mb-2">
        Pastikan semua jawaban sudah diisi. Tes akan otomatis dikirim jika waktu habis.
      </p>
      <button type="button"
              id="submit-btn"
              disabled
              class="w-full rounded-xl bg-slate-300 text-white text-sm font-semibold
                     py-2.5 shadow-sm cursor-not-allowed hover:bg-slate-300
                     active:scale-[0.99] transition">
        Selesaikan Tes TPA
      </button>
    </div>
  </form>

  <!-- Modal Konfirmasi -->
  <div id="submit-modal"
       class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm">
    <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl border border-slate-200 p-4 space-y-3 mx-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Konfirmasi</p>
          <h2 class="text-sm font-semibold text-slate-900 mt-1">Selesaikan Tes TPA?</h2>
        </div>
        <button type="button" id="modal-close"
                class="inline-flex items-center justify-center w-7 h-7 rounded-full border border-slate-200
                       text-slate-400 hover:text-slate-600 hover:bg-slate-100 text-xs">
          ✕
        </button>
      </div>

      <p class="text-[12px] text-slate-600 leading-snug">
        Pastikan semua jawaban sudah diisi. Setelah dikirim,
        jawaban <span class="font-semibold">tidak bisa diubah lagi</span>.
      </p>

      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" id="modal-cancel"
                class="px-3 py-1.5 rounded-full text-[12px] border border-slate-200 text-slate-600
                       bg-white hover:bg-slate-50">
          Kembali ke soal
        </button>
        <button type="button" id="modal-confirm"
                class="px-3 py-1.5 rounded-full text-[12px] font-semibold bg-emerald-600 text-white
                       hover:bg-emerald-700 shadow-sm shadow-emerald-500/30">
          Ya, kirim sekarang
        </button>
      </div>
    </div>
  </div>

  <footer class="pt-3 text-[10px] text-center text-slate-400">
    JECA Psychotest App • Dirancang untuk tampilan mobile
  </footer>

</main>

<script>
window.__TPA_END_AT__ = <?= (int)$endAtTs ?>;
window.__TPA_SAVED__  = <?= json_encode($savedAnswers, JSON_UNESCAPED_UNICODE) ?>;
window.__TPA_ATTEMPT_ID__ = <?= json_encode($attemptId, JSON_UNESCAPED_UNICODE) ?>;

// Anti BFCache back-button
window.addEventListener('pageshow', function(e){
  if (e.persisted) window.location.reload();
});

(function () {
  const formEl         = document.getElementById('tpa-form');
  const submitBtn      = document.getElementById('submit-btn');
  const totalQuestions = <?= (int)$totalQuestions ?>;

  const timerEl = document.getElementById('timer-text');
  const endAtMs = (window.__TPA_END_AT__ || 0) * 1000;

  const modalEl     = document.getElementById('submit-modal');
  const modalClose  = document.getElementById('modal-close');
  const modalCancel = document.getElementById('modal-cancel');
  const modalOk     = document.getElementById('modal-confirm');

  function openSubmitModal() {
    if (!modalEl) return;
    modalEl.classList.remove('hidden');
    modalEl.classList.add('flex');
  }
  function closeSubmitModal() {
    if (!modalEl) return;
    modalEl.classList.add('hidden');
    modalEl.classList.remove('flex');
  }

  // FORCE SUBMIT untuk anti-cheat & timer (tanpa modal)
  window.__TPA_FORCE_SUBMIT__ = function () {
    if (!formEl || formEl.dataset.submitted) return;
    closeSubmitModal();
    formEl.dataset.submitted = '1';
    formEl.submit();
  };

  function setSubmitState(isLastQuestion) {
    if (!submitBtn) return;

    if (isLastQuestion) {
      submitBtn.disabled = false;
      submitBtn.classList.remove('bg-slate-300', 'cursor-not-allowed', 'hover:bg-slate-300');
      submitBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
    } else {
      submitBtn.disabled = true;
      submitBtn.classList.add('bg-slate-300', 'cursor-not-allowed', 'hover:bg-slate-300');
      submitBtn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
    }
  }

  if (submitBtn && formEl) {
    submitBtn.addEventListener('click', function () {
      if (submitBtn.disabled || formEl.dataset.submitted) return;
      openSubmitModal();
    });
  }

  if (modalClose)  modalClose.addEventListener('click', closeSubmitModal);
  if (modalCancel) modalCancel.addEventListener('click', closeSubmitModal);

  if (modalOk) {
    modalOk.addEventListener('click', function () {
      window.__TPA_FORCE_SUBMIT__();
    });
  }

  function formatTime(sec) {
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
  }

  function tick() {
    if (!formEl || !timerEl) return;

    let remaining = Math.ceil((endAtMs - Date.now()) / 1000);
    if (remaining < 0) remaining = 0;

    timerEl.textContent = formatTime(remaining);

    if (remaining <= 300) {
      timerEl.classList.remove('text-rose-600');
      timerEl.classList.add('text-red-600');
    }

    if (remaining <= 0) {
      window.__TPA_FORCE_SUBMIT__();
    }
  }

  tick();
  setInterval(tick, 1000);

  // SHOW/HIDE QUESTION + restore last index (scoped by attempt)
  const cards = document.querySelectorAll('.question-card');
  const indexKey = 'tpa_current_index_' + String(window.__TPA_ATTEMPT_ID__ || 'x');

  let currentIndex = parseInt(localStorage.getItem(indexKey) || '1', 10);
  if (!Number.isFinite(currentIndex)) currentIndex = 1;

  function showQuestion(idx) {
    if (idx < 1) idx = 1;
    if (idx > totalQuestions) idx = totalQuestions;
    currentIndex = idx;

    localStorage.setItem(indexKey, String(idx));

    cards.forEach(card => {
      const ci = parseInt(card.dataset.qindex, 10);
      if (ci === idx) card.classList.remove('hidden');
      else card.classList.add('hidden');
    });

    document.querySelectorAll('.nav-item').forEach(btn => {
      const bi = parseInt(btn.dataset.qindex, 10);
      btn.classList.remove('ring', 'ring-indigo-400');
      if (bi === idx) btn.classList.add('ring', 'ring-indigo-400');
    });

    setSubmitState(idx === totalQuestions);
  }

  document.querySelectorAll('.nav-item').forEach(btn => {
    btn.addEventListener('click', function () {
      const idx = parseInt(this.dataset.qindex, 10);
      showQuestion(idx);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  document.querySelectorAll('.nav-next').forEach(btn => {
    btn.addEventListener('click', function () {
      const idx = parseInt(this.dataset.qindex, 10);
      showQuestion(idx + 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  document.querySelectorAll('.nav-prev').forEach(btn => {
    btn.addEventListener('click', function () {
      const idx = parseInt(this.dataset.qindex, 10);
      showQuestion(idx - 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  const toggleBtn = document.getElementById('toggle-nav');
  const nav       = document.getElementById('nav-container');
  if (toggleBtn && nav) {
    toggleBtn.addEventListener('click', function () {
      nav.classList.toggle('hidden');
    });
  }

  function applySelectionStyles(inputEl) {
    if (!inputEl) return;
    const qid = inputEl.dataset.questionId;

    const navBtn = document.getElementById('nav-q-' + qid);
    if (navBtn) {
      navBtn.classList.remove('bg-white', 'text-slate-700', 'border-slate-300');
      navBtn.classList.add('bg-emerald-500', 'text-white', 'border-emerald-500');
    }

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

  document.querySelectorAll('.answer-input').forEach(input => {
    input.addEventListener('change', function () {
      applySelectionStyles(this);
      scheduleSave();
    });
  });

  // Restore
  const saved = window.__TPA_SAVED__ || {};
  Object.keys(saved).forEach((qid) => {
    const val = String(saved[qid]);
    const el = document.querySelector(`input[type="radio"][name="answer[${qid}]"][value="${val}"]`);
    if (el) {
      el.checked = true;
      applySelectionStyles(el);
    }
  });

  showQuestion(currentIndex);

  function collectAnswers(){
    const out = {};
    document.querySelectorAll('input[type="radio"]:checked').forEach((r) => {
      const name = r.getAttribute('name') || '';
      const m = name.match(/^answer\[(\d+)\]$/);
      if (!m) return;
      out[m[1]] = r.value;
    });
    return out;
  }

  let saveTimer = null;
  function scheduleSave(){
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(doSave, 400);
  }

  async function doSave(){
    if (!formEl || formEl.dataset.submitted) return;
    const payload = collectAnswers();
    try {
      await fetch('index.php?page=ajax-tpa-progress', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({answers_json: JSON.stringify(payload)}).toString(),
        keepalive: true
      });
    } catch (e) {}
  }

  setInterval(doSave, 10000);

  window.addEventListener('beforeunload', function(){
    if (!formEl || formEl.dataset.submitted) return;

    const payload = collectAnswers();
    const dataStr = new URLSearchParams({answers_json: JSON.stringify(payload)}).toString();

    if (navigator.sendBeacon) {
      const blob = new Blob([dataStr], { type: 'application/x-www-form-urlencoded' });
      navigator.sendBeacon('index.php?page=ajax-tpa-progress', blob);
    }
  });

})();
</script>

<script>
(function () {
  const LOGIN_URL = "index.php?page=user-login&error=disabled&msg=" + encodeURIComponent("Akun Anda dinonaktifkan oleh admin.");

  async function ping() {
    try {
      const res = await fetch("index.php?page=ajax-user-status&test=TPA", {
        cache: "no-store",
        headers: { "Accept": "application/json" }
      });

      if (res.status === 403) {
        window.location.href = LOGIN_URL;
        return;
      }

      const data = await res.json().catch(() => null);
      if (data && data.active === 0) {
        window.location.href = LOGIN_URL;
      }
    } catch (e) {}
  }

  ping();
  setInterval(ping, 2500);
})();
</script>

<!-- AntiCheatLite -->
<script src="assets/JS/antiCheatLite.js?v=<?= time() ?>"></script>
<script>
(function () {
  const warnEl   = document.getElementById('ac-warn');
  const blockEl  = document.getElementById('ac-block');
  const blockMsg = document.getElementById('ac-block-msg');
  const failEl   = document.getElementById('ac-failclosed');
  const failMsg  = document.getElementById('ac-fail-msg');

  function showWarn(count, limit) {
    if (!warnEl) return;
    warnEl.textContent = `Peringatan ${count}/${limit}: Jangan pindah tab / minimize. Lebih dari ${limit}x akan otomatis submit.`;
    warnEl.classList.remove('hidden');
    clearTimeout(warnEl.__t);
    warnEl.__t = setTimeout(() => warnEl.classList.add('hidden'), 1800);
  }

  function failClosed(reason) {
    if (failMsg) failMsg.textContent = "AntiCheat tidak aktif: " + reason + ". Silakan refresh halaman.";
    if (failEl) failEl.classList.remove('hidden');
    console.error('[AntiCheat] NOT RUNNING:', reason);
  }

  if (!window.AntiCheatLite || !window.AntiCheatLite.init) {
    failClosed('antiCheatLite.js gagal dimuat (cek path/case assets/JS)');
    return;
  }

  const ac = window.AntiCheatLite.init({
    attemptId: <?= json_encode($attemptId, JSON_UNESCAPED_UNICODE) ?>,
    testCode: "TPA",
    postUrl: "index.php?page=ajax-anti-cheat",

    // 1-3 warning, ke-4 invalidate => autosubmit
    warningLimit: 3,
    maxViolations: 4,
    strikeCooldownMs: 1500,

    lockStaleMs: 5000,

    cameraRequired: true,
    cameraVideoEl: document.getElementById('ac-cam'),
    cameraIndicatorEl: document.getElementById('ac-badge'),

    onViolation: ({count, warningLimit, willInvalidate}) => {
      if (!willInvalidate && count <= warningLimit) showWarn(count, warningLimit);
    },

    onBlocked: ({reason}) => {
      if (blockMsg) {
        if (reason === "INSECURE_CONTEXT") {
          blockMsg.textContent = "Izin kamera wajib. Gunakan HTTPS/localhost lalu refresh halaman.";
        } else if (reason === "CAMERA_DENIED") {
          blockMsg.textContent = "Izin kamera ditolak. Izinkan kamera pada browser lalu refresh halaman.";
        }
      }
      if (blockEl) blockEl.classList.remove('hidden');

      // disable semua input
      document.querySelectorAll('form input, form button, form select, form textarea').forEach(el => el.disabled = true);
    },

    onInvalidate: () => {
      if (typeof window.__TPA_FORCE_SUBMIT__ === "function") return window.__TPA_FORCE_SUBMIT__();
      location.href = "index.php?page=user-dashboard";
    }
  });

  ac.start();
})();
</script>

</body>
</html>
