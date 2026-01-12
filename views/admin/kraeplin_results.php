<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';

$qVal    = htmlspecialchars($filters['q'] ?? ($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8');
$fromVal = htmlspecialchars($filters['from'] ?? ($_GET['from'] ?? ''), ENT_QUOTES, 'UTF-8');
$toVal   = htmlspecialchars($filters['to'] ?? ($_GET['to'] ?? ''), ENT_QUOTES, 'UTF-8');

function buildUrl(array $override = []) {
    $params = $_GET;
    $params['page'] = 'admin-kraeplin-results';
    foreach ($override as $k => $v) $params[$k] = $v;
    return 'index.php?' . http_build_query($params);
}

function exportUrl() {
    $params = $_GET;
    $params['page'] = 'admin-kraeplin-export';
    return 'index.php?' . http_build_query($params);
}
?>
<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Header + Filter -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900">Results Kraeplin</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Rekap hasil tes Kraeplin per peserta, mencakup produktivitas, ketelitian,
                        stabilitas kerja, konsentrasi, adaptasi, dan pola kerja.
                    </p>
                </div>

                <form method="GET" action="index.php" class="w-full lg:w-auto">
                    <input type="hidden" name="page" value="admin-kraeplin-results" />
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input
                            type="text" name="q" value="<?= $qVal ?>"
                            placeholder="Cari nama / user code"
                            class="w-full sm:w-64 rounded-xl border border-gray-200 px-4 py-2 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />

                        <input
                            type="date" name="from" value="<?= $fromVal ?>"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />

                        <input
                            type="date" name="to" value="<?= $toVal ?>"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-indigo-300"
                        />

                        <div class="flex gap-2">
                            <button
                                class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2
                                       text-sm font-semibold text-white hover:bg-indigo-700"
                                type="submit"
                            >
                                Terapkan
                            </button>
                            <a
                                href="index.php?page=admin-kraeplin-results"
                                class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2
                                       text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            >
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel hasil Kraeplin -->
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 pt-5 pb-3 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Daftar Hasil Tes Kraeplin</h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Total data: <span class="font-semibold text-gray-900"><?= (int)($total ?? 0) ?></span>
                    </p>
                </div>

                <a href="<?= exportUrl() ?>"
                   class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Download Excel
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                    <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-6 py-3 text-left w-16">ID Tes</th>
                        <th class="px-6 py-3 text-left w-64">ID &amp; Nama Peserta</th>
                        <th class="px-6 py-3 text-center w-28">Produktivitas</th>
                        <th class="px-6 py-3 text-center w-28">Jawaban Benar</th>
                        <th class="px-6 py-3 text-center w-32">Akurasi</th>
                        <th class="px-6 py-3 text-center w-32">Stabilitas</th>
                        <th class="px-6 py-3 text-center w-40">Konsentrasi</th>
                        <th class="px-6 py-3 text-center w-32">Adaptasi</th>
                        <th class="px-6 py-3 text-center w-40">Pola Kerja</th>
                        <th class="px-6 py-3 text-left w-48">Waktu Tes</th>
                        <th class="px-6 py-3 text-center w-24">Detail</th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $attemptNo = (int)($row['attempt_no'] ?? 1);
                                $isRetake  = (!empty($row['is_retake']) || $attemptNo > 1);
                            ?>
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-3 text-gray-600 font-medium"><?= (int)$row['id'] ?></td>

                                <td class="px-6 py-3">
                                    <div class="text-sm text-gray-900 font-semibold flex items-center gap-2 flex-wrap">
                                        <span>
                                            ID: <?= (int)($row['user_id'] ?? 0) ?> ·
                                            <span class="font-mono"><?= htmlspecialchars($row['user_code'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>

                                        <?php if ($isRetake): ?>
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold
                                                         bg-rose-50 text-rose-700 border border-rose-200">
                                                RETAKE<?= ($attemptNo > 1 ? ' #' . $attemptNo : '') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars($row['user_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                                        <?= (int)($row['total_productivity'] ?? 0) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                                        <?= (int)($row['total_correct'] ?? 0) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700">
                                        <?= number_format((float)($row['accuracy_percentage'] ?? 0), 1) ?>%
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700">
                                        <?= number_format((float)($row['stability_score'] ?? 0), 1) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">
                                        <?= htmlspecialchars($row['concentration_trend'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-lime-50 text-lime-700">
                                        <?= number_format((float)($row['adaptation_score'] ?? 0), 1) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        <?= htmlspecialchars($row['work_pattern'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td class="px-6 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($row['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="px-6 py-3 text-center">
                                    <a href="index.php?page=admin-kraeplin-result-detail&id=<?= (int)$row['id'] ?>"
                                       class="inline-flex items-center justify-center rounded-full border border-indigo-200 px-3 py-1 text-[11px] font-medium text-indigo-600 hover:bg-indigo-50">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="px-6 py-8 text-center text-gray-400 text-sm">
                                Belum ada data hasil Kraeplin (sesuai filter).
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    Halaman <span class="font-semibold text-gray-900"><?= (int)($page ?? 1) ?></span> dari
                    <span class="font-semibold text-gray-900"><?= (int)($totalPages ?? 1) ?></span>
                </p>

                <div class="flex gap-2">
                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm
                              <?= ((int)($page ?? 1) <= 1) ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50' ?>"
                       href="<?= buildUrl(['k_page' => (int)($page ?? 1) - 1]) ?>">Prev</a>

                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 text-sm
                              <?= ((int)($page ?? 1) >= (int)($totalPages ?? 1)) ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-50' ?>"
                       href="<?= buildUrl(['k_page' => (int)($page ?? 1) + 1]) ?>">Next</a>
                </div>
            </div>

        </section>

    </div>
</div>
