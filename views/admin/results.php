<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';

$qVal    = htmlspecialchars($filters['q'] ?? ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8');
$fromVal = htmlspecialchars($filters['from'] ?? ($_GET['from'] ?? ''), ENT_QUOTES, 'UTF-8');
$toVal   = htmlspecialchars($filters['to'] ?? ($_GET['to'] ?? ''), ENT_QUOTES, 'UTF-8');

function buildUrl(array $override = []) {
    $params = $_GET;
    $params['page'] = 'admin-results';
    foreach ($override as $k => $v) $params[$k] = $v;
    return 'index.php?' . http_build_query($params);
}

// opsional: kalau masih dipakai di tempat lain
function exportUrl(string $type) {
    $params = $_GET;
    $params['page'] = 'admin-results-export';
    $params['type'] = $type;
    return 'index.php?' . http_build_query($params);
}

function nbHyphen(string $s): string {
    // U+2011 NON-BREAKING HYPHEN
    return str_replace('-', "-", $s);
}

function fmtDateTime(?string $s): string {
    $s = (string)$s;
    if ($s === '') return '-';
    $ts = strtotime($s);
    if ($ts === false) return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return date('d M Y H:i', $ts);
}
?>
<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Header + Filter -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900">Results TPA &amp; TAM</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Rekap hasil peserta. Tampilan diurutkan berdasarkan ranking (skor tertinggi di atas).
                    </p>
                </div>

                <form method="GET" action="index.php" class="w-full lg:w-auto">
                    <input type="hidden" name="page" value="admin-results" />
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input
                            id="filter_q"
                            type="text" name="q" value="<?= $qVal ?>"
                            placeholder="Cari nama / user code"
                            class="w-full sm:w-64 rounded-xl border border-gray-200 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />

                        <input
                            id="filter_from"
                            type="date" name="from" value="<?= $fromVal ?>"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />

                        <input
                            id="filter_to"
                            type="date" name="to" value="<?= $toVal ?>"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />

                        <div class="flex gap-2">
                            <button
                                class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                                type="submit"
                            >
                                Terapkan
                            </button>
                            <a
                                href="index.php?page=admin-results"
                                class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            >
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ==================== TPA RESULTS ==================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 pt-5 pb-3 border-b border-gray-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Hasil Tes TPA (Ranking)</h2>
                    <p class="text-xs text-gray-500 mt-1">
                        1 baris per user (skor terbaik). Total user terdata: <span class="font-semibold text-gray-900"><?= (int)$tpaTotal ?></span>
                    </p>
                </div>

                <!-- EXPORT TPA -->
                <form id="exportTpaForm" method="GET" action="index.php" class="inline shrink-0">
                    <input type="hidden" name="page" value="admin-results-export">
                    <input type="hidden" name="type" value="tpa">

                    <input type="hidden" name="q"    id="export_tpa_q" value="">
                    <input type="hidden" name="from" id="export_tpa_from" value="">
                    <input type="hidden" name="to"   id="export_tpa_to" value="">

                    <button type="submit"
                        class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Download Excel
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-sm">
                    <thead>
                    <tr class="text-xs text-slate-500 uppercase tracking-wide bg-gray-50">
                        <th class="px-4 py-3 text-left whitespace-nowrap">Rank</th>
                        <th class="px-4 py-3 text-left">ID &amp; Nama Peserta</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">Skor</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">Persentase</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">Waktu Tes</th>
                        <th class="px-4 py-3 text-right pr-4 whitespace-nowrap">Detail</th>
                    </tr>
                    </thead>

                    <tbody class="text-sm text-slate-800">
                    <?php if (!empty($tpaRows)): ?>
                        <?php foreach ($tpaRows as $i => $row): ?>
                            <?php
                                $rank = (($tpaPage - 1) * $tpaPerPage) + $i + 1;
                                $score = (int)($row['score'] ?? 0);
                                $pct = 60 > 0 ? round(($score / 60) * 100, 1) : 0;

                                $codeRaw = (string)($row['user_code'] ?? '-');
                                $codeNb  = nbHyphen($codeRaw);
                            ?>
                            <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-semibold text-slate-900 whitespace-nowrap"><?= $rank ?></td>

                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-slate-900">
                                        <?= htmlspecialchars($row['user_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($row['is_retake']) || ((int)($row['attempt_no'] ?? 1) > 1)): ?>
                                            <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 border border-amber-200">retake</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-500 whitespace-nowrap">
                                        ID: <?= (int)($row['user_id'] ?? 0) ?> ·
                                        <span class="font-mono"><?= htmlspecialchars($codeNb, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold px-3 py-1">
                                        <?= $score ?> / 60
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold px-3 py-1">
                                        <?= $pct ?>%
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-center text-xs text-slate-500 whitespace-nowrap">
                                    <?= fmtDateTime($row['created_at'] ?? '-') ?>
                                </td>

                                <td class="px-4 py-3 text-right pr-4 whitespace-nowrap">
                                    <a href="index.php?page=admin-tpa-result-detail&id=<?= (int)$row['id'] ?>"
                                       class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">
                                Belum ada data hasil TPA (sesuai filter).
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination TPA -->
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    Halaman <span class="font-semibold text-gray-900"><?= (int)$tpaPage ?></span> dari
                    <span class="font-semibold text-gray-900"><?= (int)$tpaTotalPages ?></span>
                </p>

                <div class="flex gap-2">
                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm <?= $tpaPage <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50' ?>"
                       href="<?= buildUrl(['tpa_page' => $tpaPage - 1]) ?>">Prev</a>
                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm <?= $tpaPage >= $tpaTotalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50' ?>"
                       href="<?= buildUrl(['tpa_page' => $tpaPage + 1]) ?>">Next</a>
                </div>
            </div>
        </section>

        <!-- ==================== TAM RESULTS ==================== -->
        <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 pt-5 pb-3 border-b border-gray-100 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Hasil Tes TAM (Ranking)</h2>
                    <p class="text-xs text-gray-500 mt-1">
                        1 baris per user (skor terbaik). Total user terdata: <span class="font-semibold text-gray-900"><?= (int)$tamTotal ?></span>
                    </p>
                </div>

                <!-- EXPORT TAM -->
                <form id="exportTamForm" method="GET" action="index.php" class="inline shrink-0">
                    <input type="hidden" name="page" value="admin-results-export">
                    <input type="hidden" name="type" value="tam">

                    <input type="hidden" name="q"    id="export_tam_q" value="">
                    <input type="hidden" name="from" id="export_tam_from" value="">
                    <input type="hidden" name="to"   id="export_tam_to" value="">

                    <button type="submit"
                        class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Download Excel
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-sm">
                    <thead>
                    <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-6 py-3 text-left whitespace-nowrap">Rank</th>
                        <th class="px-6 py-3 text-left">ID &amp; Nama Peserta</th>
                        <th class="px-6 py-3 text-center whitespace-nowrap">Benar</th>
                        <th class="px-6 py-3 text-center whitespace-nowrap">Salah</th>
                        <th class="px-6 py-3 text-center whitespace-nowrap">Skor</th>
                        <th class="px-6 py-3 text-center whitespace-nowrap">Waktu Tes</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php if (!empty($tamRows)): ?>
                        <?php foreach ($tamRows as $i => $row): ?>
                            <?php
                                $rank = (($tamPage - 1) * $tamPerPage) + $i + 1;
                                $codeRaw = (string)($row['user_code'] ?? '-');
                                $codeNb  = nbHyphen($codeRaw);
                            ?>
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-3 font-semibold text-gray-900 whitespace-nowrap"><?= $rank ?></td>

                                <td class="px-6 py-3">
                                    <div class="text-sm font-semibold text-slate-900">
                                        <?= htmlspecialchars($row['user_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($row['is_retake']) || ((int)($row['attempt_no'] ?? 1) > 1)): ?>
                                            <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 border border-amber-200">retake</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-500 whitespace-nowrap">
                                        ID: <?= (int)($row['user_id'] ?? 0) ?> ·
                                        <span class="font-mono"><?= htmlspecialchars($codeNb, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>

                                <td class="px-6 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                                        <?= (int)($row['total_correct'] ?? 0) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700">
                                        <?= (int)($row['total_wrong'] ?? 0) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700">
                                        <?= (int)($row['score'] ?? 0) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-sm text-gray-600 text-center whitespace-nowrap">
                                    <?= fmtDateTime($row['created_at'] ?? '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400 text-sm">
                                Belum ada data hasil TAM (sesuai filter).
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination TAM -->
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    Halaman <span class="font-semibold text-gray-900"><?= (int)$tamPage ?></span> dari
                    <span class="font-semibold text-gray-900"><?= (int)$tamTotalPages ?></span>
                </p>

                <div class="flex gap-2">
                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm <?= $tamPage <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50' ?>"
                       href="<?= buildUrl(['tam_page' => $tamPage - 1]) ?>">Prev</a>
                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm <?= $tamPage >= $tamTotalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50' ?>"
                       href="<?= buildUrl(['tam_page' => $tamPage + 1]) ?>">Next</a>
                </div>
            </div>
        </section>

    </div>
</div>

<!-- JS: sync nilai input filter ke hidden export saat klik Download -->
<script>
(function () {
  function getVal(id) {
    const el = document.getElementById(id);
    return el ? (el.value || '') : '';
  }

  function sync(prefix) {
    const q = getVal('filter_q');
    const from = getVal('filter_from');
    const to = getVal('filter_to');

    const qEl = document.getElementById(`export_${prefix}_q`);
    const fEl = document.getElementById(`export_${prefix}_from`);
    const tEl = document.getElementById(`export_${prefix}_to`);

    if (qEl) qEl.value = q;
    if (fEl) fEl.value = from;
    if (tEl) tEl.value = to;
  }

  const tpaForm = document.getElementById('exportTpaForm');
  if (tpaForm) tpaForm.addEventListener('submit', function () { sync('tpa'); });

  const tamForm = document.getElementById('exportTamForm');
  if (tamForm) tamForm.addEventListener('submit', function () { sync('tam'); });
})();
</script>
