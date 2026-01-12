<?php
// views/user/dashboard.php
// Input dari controller:
// $name, $user_code, $has_tpa, $has_tam, $has_kraeplin
// + tambahan kuota retake:
// $tpa_retake_quota, $tam_retake_quota, $kraeplin_retake_quota

$tpa_retake_quota      = (int)($tpa_retake_quota ?? 0);
$tam_retake_quota      = (int)($tam_retake_quota ?? 0);
$kraeplin_retake_quota = (int)($kraeplin_retake_quota ?? 0);

// ---------- STATE PER TES ----------
// DONE?
$tpa_done      = (bool)$has_tpa;
$tam_done      = (bool)$has_tam;
$kraeplin_done = (bool)$has_kraeplin;

// CAN RETAKE?
$tpa_can_retake      = $tpa_done && $tpa_retake_quota > 0;
$tam_can_retake      = $tam_done && $tam_retake_quota > 0;
$kraeplin_can_retake = $kraeplin_done && $kraeplin_retake_quota > 0;

// LOCKED (urutan tes)
$tam_locked      = !$has_tpa; // terkunci kalau TPA belum selesai
$kraeplin_locked = !$has_tam; // terkunci kalau TAM belum selesai

// READY (clickable)
$tpa_ready      = (!$tpa_done) || $tpa_can_retake;
$tam_ready      = (!$tam_locked) && ( (!$tam_done) || $tam_can_retake );
$kraeplin_ready = (!$kraeplin_locked) && ( (!$kraeplin_done) || $kraeplin_can_retake );

// ---------- LABEL STATUS ----------
$tpa_status_label = !$tpa_done
    ? 'Belum dikerjakan'
    : ($tpa_can_retake ? ("Ulangi (Sisa {$tpa_retake_quota})") : 'Selesai');

$tam_status_label = $tam_locked
    ? 'Terkunci'
    : ( !$tam_done ? 'Belum dikerjakan' : ($tam_can_retake ? ("Ulangi (Sisa {$tam_retake_quota})") : 'Selesai') );

$kraeplin_status_label = $kraeplin_locked
    ? 'Terkunci'
    : ( !$kraeplin_done ? 'Belum dikerjakan' : ($kraeplin_can_retake ? ("Ulangi (Sisa {$kraeplin_retake_quota})") : 'Selesai') );

// ---------- URL START (ack ditambah via JS setelah klik "Mengerti") ----------
$tpa_start_url      = "index.php?page=user-tpa-start"      . ($tpa_can_retake ? "&retake=1" : "");
$tam_start_url      = "index.php?page=user-tam-start"      . ($tam_can_retake ? "&retake=1" : "");
$kraeplin_start_url = "index.php?page=user-kraeplin-start" . ($kraeplin_can_retake ? "&retake=1" : "");

// ---------- HREF untuk kartu (dibuat # agar selalu lewat modal panduan) ----------
$tpa_href      = "#";
$tam_href      = "#";
$kraeplin_href = "#";

// Helper escape
if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Peserta - JECA Psychotest</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900 flex justify-center">

<main class="w-full max-w-sm px-4 py-6">
    <div class="bg-white rounded-3xl shadow-lg shadow-slate-200/80 px-4 py-5 space-y-6">

        <!-- Header -->
        <header class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-500 uppercase">
                    JECA Psychotest
                </p>
                <h1 class="mt-1 text-lg font-semibold text-slate-900 leading-snug">
                    Halo, <?= e($name) ?>
                </h1>
                <p class="text-[11px] text-slate-600 mt-0.5">
                    ID:
                    <span class="font-mono font-medium text-slate-800">
                        <?= e($user_code) ?>
                    </span>
                </p>
            </div>

            <form id="logout-form" action="index.php" method="get">
                <input type="hidden" name="page" value="user-logout">
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-full border border-slate-300
                               px-3 py-1 text-[11px] font-medium text-slate-700
                               hover:bg-slate-900 hover:text-white hover:border-slate-900 transition">
                    Logout
                </button>
            </form>
        </header>

        <!-- Info Box -->
        <section class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px]">
            <p class="text-slate-800 font-semibold">Urutan Tes</p>
            <p class="mt-1 text-slate-600 leading-relaxed">
                Kerjakan tes sesuai urutan:
                <span class="font-semibold text-slate-900">TPA → TAM → Kraeplin</span>.
                Menu berikutnya akan terbuka otomatis setelah tes sebelumnya selesai.
            </p>
            <p class="mt-2 text-slate-600 leading-relaxed">
                Jika tersedia kuota, badge akan berubah menjadi <span class="font-semibold text-slate-900">Ulangi</span>.
            </p>
        </section>

        <!-- Menu Tes -->
        <section class="space-y-3">

            <!-- Tahap 1 - TPA -->
            <a href="<?= $tpa_href ?>"
               class="block rounded-2xl bg-gradient-to-r from-indigo-500 to-indigo-600 px-4 py-3
                      shadow-md shadow-indigo-500/30 active:scale-[0.99] transition
                      <?= (!$tpa_ready ? 'opacity-60 cursor-default pointer-events-none' : '') ?>"
               data-guide-trigger="1"
               data-test="tpa"
               data-ready="<?= $tpa_ready ? '1' : '0' ?>"
               data-url="<?= e($tpa_start_url) ?>">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide text-indigo-100/90 uppercase">
                            Tahap 1
                        </p>
                        <h2 class="text-sm font-semibold text-white">
                            Tes Potensi Akademik
                        </h2>
                        <p class="mt-0.5 text-[11px] text-indigo-100/85 leading-snug">
                            Mengukur kemampuan verbal, numerik, logika, dan spasial.
                        </p>

                        <?php if ($tpa_can_retake): ?>
                            <p class="mt-1 text-[10px] text-white/90">
                                Kuota ulang tersedia: <span class="font-semibold"><?= (int)$tpa_retake_quota ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="inline-flex items-center justify-center rounded-full bg-white/15
                                     px-2 py-0.5 text-[10px] text-white font-medium">
                            <?= e($tpa_status_label) ?>
                        </span>
                        <span class="mt-2 text-[18px] text-white">➜</span>
                    </div>
                </div>
            </a>

            <!-- Tahap 2 - TAM -->
            <?php
            // kelas kartu TAM berdasar state
            if ($tam_locked) {
                $tam_card_class  = 'bg-slate-100 border-slate-200 cursor-not-allowed pointer-events-none opacity-70';
                $tam_badge_class = 'bg-slate-200 text-slate-600';
                $tam_title_color = 'text-slate-600';
                $tam_label_color = 'text-slate-500';
                $tam_arrow_color = 'text-slate-400';
            } elseif ($tam_done && !$tam_can_retake) {
                $tam_card_class  = 'bg-emerald-50 border-emerald-200 opacity-70 cursor-default pointer-events-none';
                $tam_badge_class = 'bg-emerald-500 text-white';
                $tam_title_color = 'text-emerald-800';
                $tam_label_color = 'text-emerald-700/90';
                $tam_arrow_color = 'text-emerald-500';
            } elseif ($tam_done && $tam_can_retake) {
                $tam_card_class  = 'bg-emerald-50 border-emerald-300 hover:border-emerald-400 hover:bg-emerald-50/80 transition';
                $tam_badge_class = 'bg-emerald-600 text-white';
                $tam_title_color = 'text-emerald-900';
                $tam_label_color = 'text-emerald-800/90';
                $tam_arrow_color = 'text-emerald-600';
            } else { // ready first time
                $tam_card_class  = 'bg-emerald-50 border-emerald-200 hover:border-emerald-300 hover:bg-emerald-50/80 transition';
                $tam_badge_class = 'bg-emerald-100 text-emerald-800';
                $tam_title_color = 'text-emerald-800';
                $tam_label_color = 'text-emerald-700/90';
                $tam_arrow_color = 'text-emerald-500';
            }
            ?>
            <a href="<?= $tam_href ?>"
               class="block rounded-2xl px-4 py-3 border <?= $tam_card_class ?>"
               data-guide-trigger="1"
               data-test="tam"
               data-ready="<?= $tam_ready ? '1' : '0' ?>"
               data-url="<?= e($tam_start_url) ?>">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide uppercase
                                  <?= $tam_locked ? 'text-slate-500' : 'text-emerald-600' ?>">
                            Tahap 2
                        </p>
                        <h2 class="text-sm font-semibold <?= $tam_title_color ?>">
                            Tes Aspek Memori
                        </h2>
                        <p class="mt-0.5 text-[11px] leading-snug <?= $tam_label_color ?>">
                            Mengukur daya ingat dan perhatian berdasarkan stimulus gambar.
                        </p>

                        <?php if ($tam_locked): ?>
                            <p class="mt-1 text-[10px] text-amber-600 font-medium">
                                Selesaikan <span class="font-semibold">TPA</span> terlebih dahulu.
                            </p>
                        <?php endif; ?>

                        <?php if (!$tam_locked && $tam_can_retake): ?>
                            <p class="mt-1 text-[10px] text-emerald-800/90">
                                Kuota ulang tersedia: <span class="font-semibold"><?= (int)$tam_retake_quota ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="inline-flex items-center justify-center rounded-full px-2 py-0.5 text-[10px] font-medium <?= $tam_badge_class ?>">
                            <?= e($tam_status_label) ?>
                        </span>
                        <span class="mt-2 text-[18px] <?= $tam_arrow_color ?>">➜</span>
                    </div>
                </div>
            </a>

            <!-- Tahap 3 - Kraeplin -->
            <?php
            if ($kraeplin_locked) {
                $kr_card_class   = 'bg-slate-100 border-slate-200 cursor-not-allowed pointer-events-none opacity-70';
                $kr_badge_class  = 'bg-slate-200 text-slate-600';
                $kr_title_color  = 'text-slate-600';
                $kr_label_color  = 'text-slate-500';
                $kr_arrow_color  = 'text-slate-400';
            } elseif ($kraeplin_done && !$kraeplin_can_retake) {
                $kr_card_class   = 'bg-cyan-50 border-cyan-200 opacity-70 cursor-default pointer-events-none';
                $kr_badge_class  = 'bg-cyan-500 text-white';
                $kr_title_color  = 'text-cyan-800';
                $kr_label_color  = 'text-cyan-700/90';
                $kr_arrow_color  = 'text-cyan-500';
            } elseif ($kraeplin_done && $kraeplin_can_retake) {
                $kr_card_class   = 'bg-cyan-50 border-cyan-300 hover:border-cyan-400 hover:bg-cyan-50/80 transition';
                $kr_badge_class  = 'bg-cyan-600 text-white';
                $kr_title_color  = 'text-cyan-900';
                $kr_label_color  = 'text-cyan-800/90';
                $kr_arrow_color  = 'text-cyan-600';
            } else {
                $kr_card_class   = 'bg-cyan-50 border-cyan-200 hover:border-cyan-300 hover:bg-cyan-50/80 transition';
                $kr_badge_class  = 'bg-cyan-100 text-cyan-800';
                $kr_title_color  = 'text-cyan-800';
                $kr_label_color  = 'text-cyan-700/90';
                $kr_arrow_color  = 'text-cyan-500';
            }
            ?>
            <a href="<?= $kraeplin_href ?>"
               class="block rounded-2xl px-4 py-3 border <?= $kr_card_class ?>"
               data-guide-trigger="1"
               data-test="kraeplin"
               data-ready="<?= $kraeplin_ready ? '1' : '0' ?>"
               data-url="<?= e($kraeplin_start_url) ?>">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wide uppercase
                                  <?= $kraeplin_locked ? 'text-slate-500' : 'text-cyan-600' ?>">
                            Tahap 3
                        </p>
                        <h2 class="text-sm font-semibold <?= $kr_title_color ?>">
                            Tes Kraeplin
                        </h2>
                        <p class="mt-0.5 text-[11px] leading-snug <?= $kr_label_color ?>">
                            Mengukur kecepatan, ketelitian, dan ketahanan kerja
                            melalui penjumlahan angka.
                        </p>

                        <?php if ($kraeplin_locked): ?>
                            <p class="mt-1 text-[10px] text-amber-600 font-medium">
                                Selesaikan <span class="font-semibold">Tes Aspek Memori</span> terlebih dahulu.
                            </p>
                        <?php endif; ?>

                        <?php if (!$kraeplin_locked && $kraeplin_can_retake): ?>
                            <p class="mt-1 text-[10px] text-cyan-800/90">
                                Kuota ulang tersedia: <span class="font-semibold"><?= (int)$kraeplin_retake_quota ?></span>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="inline-flex items-center justify-center rounded-full px-2 py-0.5 text-[10px] font-medium <?= $kr_badge_class ?>">
                            <?= e($kraeplin_status_label) ?>
                        </span>
                        <span class="mt-2 text-[18px] <?= $kr_arrow_color ?>">➜</span>
                    </div>
                </div>
            </a>

        </section>

        <footer class="pt-2 text-[10px] text-center text-slate-500 border-t border-slate-100 mt-2">
            JECA Psychotest App • Dioptimalkan untuk tampilan mobile
        </footer>
    </div>

    <!-- Modal Panduan Tes -->
    <div id="guide-modal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm">
        <div class="w-full max-w-sm mx-4 rounded-2xl bg-white shadow-xl shadow-slate-900/20 p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold tracking-[0.2em] text-indigo-500 uppercase">Panduan</p>
                    <h2 id="guide-title" class="mt-1 text-sm font-semibold text-slate-900">Panduan Tes</h2>
                </div>
                <button type="button" id="guide-close"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-full
                               text-slate-400 hover:bg-slate-100 hover:text-slate-700 text-sm">✕</button>
            </div>

            <div id="guide-body" class="mt-3 text-xs text-slate-700 leading-relaxed space-y-2"></div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" id="guide-cancel"
                        class="inline-flex items-center justify-center rounded-full border border-slate-200
                               px-4 py-1.5 text-xs font-medium text-slate-700
                               hover:bg-slate-50 active:scale-95 transition">Batal</button>
                <button type="button" id="guide-agree"
                        class="inline-flex items-center justify-center rounded-full bg-emerald-500
                               px-4 py-1.5 text-xs font-semibold text-white shadow-sm
                               hover:bg-emerald-600 active:scale-95 transition">Mengerti</button>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Logout -->
    <div id="logout-modal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm">
        <div class="w-full max-w-sm mx-4 rounded-2xl bg-white shadow-xl shadow-slate-900/20 p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold tracking-[0.2em] text-indigo-500 uppercase">Konfirmasi</p>
                    <h2 class="mt-1 text-sm font-semibold text-slate-900">Keluar dari JECA Psychotest?</h2>
                </div>
                <button type="button" id="logout-close"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-full
                               text-slate-400 hover:bg-slate-100 hover:text-slate-700 text-sm">✕</button>
            </div>

            <p class="mt-3 text-xs text-slate-600 leading-relaxed">
                Progres tes yang sudah <span class="font-semibold text-slate-900">tersimpan</span> tidak akan hilang.
                Namun Anda perlu login kembali jika ingin melanjutkan.
            </p>

            <div class="mt-4 flex items-center justify-end gap-2">
                <button type="button" id="logout-cancel"
                        class="inline-flex items-center justify-center rounded-full border border-slate-200
                               px-4 py-1.5 text-xs font-medium text-slate-700
                               hover:bg-slate-50 active:scale-95 transition">Batal</button>
                <button type="button" id="logout-confirm"
                        class="inline-flex items-center justify-center rounded-full bg-rose-500
                               px-4 py-1.5 text-xs font-semibold text-white shadow-sm
                               hover:bg-rose-600 active:scale-95 transition">Ya, keluar</button>
            </div>
        </div>
    </div>

</main>

<script>
(function () {
    // ====== (Opsional) blok back ======
    if (window.history && window.history.pushState) {
        window.history.pushState({ noBack: true }, '', window.location.href);
        window.addEventListener('popstate', function (event) {
            if (event.state && event.state.noBack) {
                window.history.pushState({ noBack: true }, '', window.location.href);
            }
        });
    }

    // ====== Modal Panduan ======
    const guideModal  = document.getElementById('guide-modal');
    const guideTitle  = document.getElementById('guide-title');
    const guideBody   = document.getElementById('guide-body');
    const guideClose  = document.getElementById('guide-close');
    const guideCancel = document.getElementById('guide-cancel');
    const guideAgree  = document.getElementById('guide-agree');

    let nextUrl = null;

    // === Notice Anti-Cheating (muncul di semua popup) ===
    const antiCheatNoticeHtml = `
        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
            <p class="text-[11px] font-semibold text-amber-800">Kebijakan Anti-Cheating</p>
            <ul class="mt-1 list-disc pl-4 text-[11px] text-amber-800/90 space-y-0.5">
                <li>Tes diawasi melalui kamera (izin akses akan diminta saat mulai).</li>
                <li>Jika pindah tab atau minimize browser lebih dari <b>2 kali</b>, tes akan <b>tersubmit otomatis</b>.</li>
            </ul>
        </div>
    `;

    const guides = {
        tpa: {
            title: 'Panduan Tes TPA',
            bodyHtml: `
                <ol class="list-decimal pl-4 space-y-1">
                    <li>Tes terdiri dari 4 sesi: <b>Verbal</b>, <b>Kuantitatif</b>, <b>Logika</b>, <b>Spasial</b>.</li>
                    <li>Jawablah soal dengan pilihan yang benar.</li>
                    <li>Diperbolehkan menghitung di selembar kertas.</li>
                </ol>
                ${antiCheatNoticeHtml}
            `
        },
        tam: {
            title: 'Panduan Tes TAM',
            bodyHtml: `
                <ol class="list-decimal pl-4 space-y-1">
                    <li>Tes ini menguji daya ingat.</li>
                    <li>Anda diberi waktu untuk menghafal stimulus.</li>
                    <li>Setelah waktu habis, Anda mengerjakan soal tanpa melihat stimulus lagi.</li>
                </ol>
                ${antiCheatNoticeHtml}
            `
        },
        kraeplin: {
            title: 'Panduan Tes Kraeplin',
            bodyHtml: `
                <ol class="list-decimal pl-4 space-y-1">
                    <li>Jumlahkan 2 angka.</li>
                    <li>Jika hasil 2 digit, tulis <b>angka satuan</b> (digit terakhir).</li>
                    <li>Anda akan masuk <b>latihan 1 menit</b> sebelum tes utama.</li>
                </ol>
                ${antiCheatNoticeHtml}
            `
        }
    };

    function openGuide(testKey, url) {
        const g = guides[testKey];
        if (!g) return;

        nextUrl = url;
        guideTitle.textContent = g.title;
        guideBody.innerHTML = g.bodyHtml;

        guideModal.classList.remove('hidden');
        guideModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeGuide() {
        guideModal.classList.add('hidden');
        guideModal.classList.remove('flex');
        document.body.style.overflow = '';
        nextUrl = null;
    }

    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[data-guide-trigger="1"][data-test][data-url]');
        if (!a) return;

        const isReady = a.getAttribute('data-ready') === '1';
        if (!isReady) {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        openGuide(a.getAttribute('data-test'), a.getAttribute('data-url'));
    });

    if (guideClose)  guideClose.addEventListener('click', closeGuide);
    if (guideCancel) guideCancel.addEventListener('click', closeGuide);
    if (guideModal) {
        guideModal.addEventListener('click', function (e) {
            if (e.target === guideModal) closeGuide();
        });
    }
    if (guideAgree) {
        guideAgree.addEventListener('click', function () {
            if (!nextUrl) return;
            const join = nextUrl.includes('?') ? '&' : '?';
            window.location.href = nextUrl + join + 'ack=1';
        });
    }

    // ====== Poll status aktif (SATU versi saja, tidak dobel) ======
    async function checkActive() {
        try {
            const res = await fetch('index.php?page=ajax-user-status', { cache: 'no-store' });
            const data = await res.json();

            // dukung beberapa bentuk payload
            const activeVal =
                (data && typeof data.active !== 'undefined') ? String(data.active) :
                (data && typeof data.is_active !== 'undefined') ? String(data.is_active) :
                null;

            const isDisabled =
                (data && data.is_disabled === true) ||
                (activeVal !== null && activeVal !== "1") ||
                (data && data.ok === false);

            if (isDisabled) {
                window.location.replace('index.php?page=user-logout&reason=disabled');
            }
        } catch (e) {
            // silent
        }
    }
    checkActive();
    setInterval(checkActive, 4000);

    // ====== Modal Logout ======
    const logoutForm  = document.getElementById('logout-form');
    const logoutModal = document.getElementById('logout-modal');
    const btnCancel   = document.getElementById('logout-cancel');
    const btnConfirm  = document.getElementById('logout-confirm');
    const btnClose    = document.getElementById('logout-close');

    let allowSubmit = false;

    function openLogoutModal() {
        if (!logoutModal) return;
        logoutModal.classList.remove('hidden');
        logoutModal.classList.add('flex');
    }

    function closeLogoutModal() {
        if (!logoutModal) return;
        logoutModal.classList.add('hidden');
        logoutModal.classList.remove('flex');
    }

    if (logoutForm && logoutModal) {
        logoutForm.addEventListener('submit', function (e) {
            if (allowSubmit) return;
            e.preventDefault();
            openLogoutModal();
        });
    }
    if (btnCancel) btnCancel.addEventListener('click', closeLogoutModal);
    if (btnClose)  btnClose.addEventListener('click', closeLogoutModal);

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function () {
            allowSubmit = true;
            closeLogoutModal();
            if (logoutForm) logoutForm.submit();
        });
    }
})();
</script>

</body>
</html>
