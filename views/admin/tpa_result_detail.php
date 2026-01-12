<?php
// Safeguard variabel dari controller
$data             = $data             ?? [];
$breakdown        = $breakdown        ?? [];
$totalCorrect     = $totalCorrect     ?? 0;
$totalQuestions   = $totalQuestions   ?? 0;
$totalPercentage  = $totalPercentage  ?? 0.0;

$userName  = $data['user_name']  ?? 'Tidak diketahui';
$userCode  = $data['user_code']  ?? '-';
$userId    = $data['user_id']    ?? '-';
$createdAt = $data['created_at'] ?? '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Tes TPA - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">

<main class="max-w-6xl mx-auto px-6 py-8 space-y-6">

    <!-- Header -->
    <header class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.25em] text-indigo-500 uppercase">
                JECA PSYCHOTEST – ADMIN
            </p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">
                Detail Tes TPA
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                Ringkasan hasil Tes Potensi Akademik peserta, termasuk skor total dan skor per kategori
                (verbal, kuantitatif, logika, spasial).
            </p>
        </div>

        <a href="index.php?page=admin-results"
           class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1.5
                  text-xs font-medium text-slate-700 hover:bg-slate-100">
            ← Kembali ke daftar
        </a>
    </header>

    <!-- Data Peserta -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    Data Peserta
                </p>
                <h2 class="mt-1 text-base font-semibold text-slate-900">
                    <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p class="text-xs text-slate-600 mt-0.5">
                    ID User:
                    <span class="font-mono font-medium text-slate-800">
                        <?= htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    · Kode:
                    <span class="font-mono font-medium text-slate-800">
                        <?= htmlspecialchars($userCode, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-500">Waktu Tes Terakhir</p>
                <p class="text-sm font-semibold text-slate-900">
                    <?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Skor Total -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">
                Skor TPA Keseluruhan
            </p>
            <p class="mt-1 text-2xl font-semibold text-indigo-600">
                <?= (int)$totalCorrect ?> / <?= (int)$totalQuestions ?>
            </p>
            <p class="mt-1 text-sm text-slate-700">
                Persentase benar:
                <span class="font-semibold">
                    <?= number_format($totalPercentage, 1) ?>%
                </span>
            </p>
            <p class="mt-1 text-[11px] text-slate-500 leading-relaxed">
                Semakin tinggi jumlah jawaban benar, semakin baik kemampuan umum peserta dalam
                memahami soal verbal, berhitung, dan memecahkan masalah. Kombinasi skor verbal,
                kuantitatif, logika, dan spasial memberikan gambaran menyeluruh tentang potensi akademik peserta.
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">
                Interpretasi Singkat
            </p>
            <p class="mt-2 text-[11px] text-slate-500 leading-relaxed">
                - Di atas 80%: kemampuan akademik sangat baik dan konsisten di sebagian besar kategori.<br>
                - 60–79%: kemampuan cukup sampai baik, masih ada ruang perbaikan pada kategori tertentu.<br>
                - Di bawah 60%: perlu pendampingan dan latihan tambahan pada materi dasar dan logika berpikir.
            </p>
        </div>
    </section>

    <!-- Rincian per Kategori -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 px-5 py-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">
                Rincian Skor per Kategori
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                <tr class="border-b border-slate-100 bg-slate-50/60">
                    <th class="text-left py-2 px-2 font-semibold text-slate-600">Kategori</th>
                    <th class="text-center py-2 px-2 font-semibold text-slate-600">Skor</th>
                    <th class="text-center py-2 px-2 font-semibold text-slate-600">% Benar</th>
                    <th class="text-left py-2 px-2 font-semibold text-slate-600">Keterangan</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($breakdown)): ?>
                    <?php foreach ($breakdown as $key => $bd): ?>
                        <?php
                        $label      = $bd['label']      ?? ucfirst($key);
                        $correct    = (int)($bd['correct'] ?? 0);
                        $max        = (int)($bd['max']     ?? 0);
                        $percentage = $max > 0 ? ($correct / $max) * 100 : 0;

                        // Deskripsi singkat per kategori
                        switch ($key) {
                            case 'verbal':
                                $desc = 'Kemampuan memahami kata, sinonim, antonim, dan hubungan antar kalimat.';
                                break;
                            case 'kuantitatif':
                                $desc = 'Kemampuan berhitung, memahami angka, dan penalaran numerik.';
                                break;
                            case 'logika':
                                $desc = 'Kemampuan mengurai pola, sebab-akibat, dan penalaran logis.';
                                break;
                            case 'spasial':
                                $desc = 'Kemampuan membayangkan bentuk/ruang dan hubungan posisi.';
                                break;
                            default:
                                $desc = 'Kategori lain / belum teridentifikasi.';
                                break;
                        }
                        ?>
                        <tr class="border-b border-slate-50 hover:bg-slate-50/60">
                            <td class="py-2 px-2 text-slate-700 font-medium">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="py-2 px-2 text-center font-mono text-slate-800">
                                <?= $correct ?> / <?= $max ?>
                            </td>
                            <td class="py-2 px-2 text-center font-mono text-indigo-600">
                                <?= number_format($percentage, 1) ?>%
                            </td>
                            <td class="py-2 px-2 text-[11px] text-slate-500">
                                <?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="py-3 px-2 text-center text-slate-500">
                            Tidak ada data skor per kategori.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>
</body>
</html>
