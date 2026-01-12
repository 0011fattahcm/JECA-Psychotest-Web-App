<?php
// $data  = row dari kraeplin_results + name, user_code
// $lines = array hasil decode raw_lines

// Safeguard
$userName  = $data['user_name']  ?? 'Tidak diketahui';
$userCode  = $data['user_code']  ?? '-';
$userId    = $data['user_id']    ?? '-';
$createdAt = $data['created_at'] ?? '-';

// Siapkan data untuk grafik
$labels           = [];
$productivityData = [];
$correctData      = [];

if (is_array($lines)) {
    foreach ($lines as $i => $line) {
        $labels[]           = 'Interval ' . ($i + 1);
        $totalItems         = (int)($line['total_items'] ?? 0);
        $correct            = (int)($line['correct'] ?? 0);
        $productivityData[] = $totalItems;
        $correctData[]      = $correct;
    }
}

// JSON untuk Chart.js
$labelsJson      = json_encode($labels);
$productivityJson = json_encode($productivityData);
$correctJson     = json_encode($correctData);

// Angka dasar
$totalProductivity = (int) ($data['total_productivity'] ?? 0);
$totalCorrect      = (int) ($data['total_correct'] ?? 0);
$accuracy          = (float) ($data['accuracy_percentage'] ?? 0);
$stability         = (float) ($data['stability_score'] ?? 0);
$trend             = trim($data['concentration_trend'] ?? '');
$adapt             = (float) ($data['adaptation_score'] ?? 0);
$pattern           = trim($data['work_pattern'] ?? '');

// =========================
// Interpretasi AKURASI
// =========================
if ($accuracy >= 95) {
    $accuracyLevel = 'sangat tinggi';
    $accuracyDesc  = 'Akurasi sangat tinggi. Peserta hampir selalu menjawab dengan benar dan jarang melakukan kesalahan hitung.';
} elseif ($accuracy >= 85) {
    $accuracyLevel = 'baik';
    $accuracyDesc  = 'Akurasi baik. Sebagian besar penjumlahan dikerjakan dengan benar, kesalahan masih dalam batas wajar.';
} elseif ($accuracy >= 70) {
    $accuracyLevel = 'cukup';
    $accuracyDesc  = 'Akurasi cukup. Masih terdapat cukup banyak kesalahan hitung sehingga perlu peningkatan ketelitian.';
} else {
    $accuracyLevel = 'rendah';
    $accuracyDesc  = 'Akurasi rendah. Jumlah kesalahan hitung cukup tinggi, menunjukkan perlunya peningkatan konsentrasi dan ketelitian.';
}

// =========================
// Interpretasi STABILITAS
// (semakin kecil variansi = semakin stabil)
// =========================
if ($stability < 2) {
    $stabilityLevel = 'sangat stabil';
    $stabilityDesc  = 'Tempo kerja sangat konsisten dari awal hingga akhir. Fluktuasi jumlah item per interval hampir tidak terlihat.';
} elseif ($stability < 5) {
    $stabilityLevel = 'cukup stabil';
    $stabilityDesc  = 'Tempo kerja relatif stabil dengan sedikit naik turun jumlah item per interval.';
} elseif ($stability < 10) {
    $stabilityLevel = 'kurang stabil';
    $stabilityDesc  = 'Tempo kerja cukup berfluktuasi. Terdapat beberapa interval dengan penurunan atau kenaikan yang cukup tajam.';
} else {
    $stabilityLevel = 'sangat fluktuatif';
    $stabilityDesc  = 'Tempo kerja sangat berfluktuasi. Jumlah item per interval naik turun tajam, menandakan stabilitas kerja yang kurang.';
}

// =========================
// Interpretasi ADAPTASI
// (adapt = rata-rata tengah – rata-rata awal)
// =========================
if ($adapt >= 1) {
    $adaptLevel = 'adaptasi sangat baik';
    $adaptDesc  = 'Setelah fase awal, produktivitas meningkat cukup signifikan. Peserta cepat menyesuaikan diri dengan pola tes.';
} elseif ($adapt >= 0.3) {
    $adaptLevel = 'adaptasi baik';
    $adaptDesc  = 'Terlihat peningkatan produktivitas setelah beberapa menit pertama, meski tidak terlalu besar.';
} elseif ($adapt > -0.3) {
    $adaptLevel = 'adaptasi stabil';
    $adaptDesc  = 'Tidak terdapat perubahan besar antara fase awal dan fase selanjutnya. Produktivitas cenderung konstan.';
} elseif ($adapt > -1) {
    $adaptLevel = 'adaptasi kurang';
    $adaptDesc  = 'Setelah fase awal, produktivitas sedikit menurun. Bisa mengindikasikan kelelahan atau penurunan fokus.';
} else {
    $adaptLevel = 'adaptasi rendah';
    $adaptDesc  = 'Penurunan produktivitas cukup jelas setelah fase awal, menunjukkan kesulitan mempertahankan performa dalam durasi tes.';
}

// =========================
// Interpretasi KONSENTRASI (trend)
// =========================
if ($trend === 'meningkat') {
    $concentrationDesc = 'Konsentrasi cenderung meningkat. Produktivitas pada akhir tes lebih tinggi dibandingkan awal.';
} elseif ($trend === 'menurun') {
    $concentrationDesc = 'Konsentrasi cenderung menurun. Produktivitas pada akhir tes lebih rendah dibandingkan awal.';
} else { // 'stabil' atau kosong
    $concentrationDesc = 'Konsentrasi cenderung stabil. Tidak ada perbedaan mencolok antara produktivitas awal dan akhir tes.';
}

// =========================
// Interpretasi POLA KERJA (pattern)
// =========================
switch ($pattern) {
    case 'naik':
        $patternDesc = 'Pola kerja cenderung meningkat. Setiap interval menunjukkan jumlah item yang makin banyak.';
        break;
    case 'menurun':
        $patternDesc = 'Pola kerja cenderung menurun. Terjadi penurunan jumlah item dari waktu ke waktu.';
        break;
    case 'zig-zag':
    default:
        $patternDesc = 'Pola kerja zig-zag. Jumlah item per interval naik turun, menunjukkan fluktuasi performa dari waktu ke waktu.';
        break;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Tes Kraeplin - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                Detail Tes Kraeplin
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                Ringkasan lengkap hasil tes Kraeplin peserta, termasuk produktivitas, akurasi,
                stabilitas, konsentrasi, adaptasi, dan pola kerja.
            </p>
        </div>

        <a href="index.php?page=admin-kraeplin-results"
           class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1.5
                  text-xs font-medium text-slate-700 hover:bg-slate-100">
            ← Kembali ke daftar
        </a>
    </header>

    <!-- Info Peserta -->
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
                <p class="text-xs text-slate-500">Waktu Tes</p>
                <p class="text-sm font-semibold text-slate-900">
                    <?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Ringkasan Angka Utama -->
    <section class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Produktivitas -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">Produktivitas</p>
            <p class="mt-1 text-2xl font-semibold text-indigo-600">
                <?= (int)$data['total_productivity'] ?>
            </p>
            <p class="text-[11px] text-slate-500 mt-1">
                Total item yang dikerjakan.
            </p>
        </div>

        <!-- Jawaban Benar -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
          <p class="text-xs font-semibold text-slate-500 uppercase">Jawaban Benar</p>
    <p class="mt-1 text-3xl font-semibold text-emerald-600">
        <?= $totalCorrect ?>
    </p>
    <p class="mt-1 text-xs text-slate-500">
        Akurasi: <?= number_format($accuracy, 1) ?>% (<?= $accuracyLevel ?>)
    </p>
    <p class="mt-0.5 text-[11px] text-slate-500 leading-snug">
        <?= $accuracyDesc ?>
    </p>
        </div>

        <!-- Stabilitas -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
       <p class="text-xs font-semibold text-slate-500 uppercase">Stabilitas</p>
    <p class="mt-1 text-3xl font-semibold text-fuchsia-600">
        <?= number_format($stability, 1) ?>
    </p>
    <p class="mt-1 text-xs text-slate-500">
        Level: <?= $stabilityLevel ?>
    </p>
    <p class="mt-0.5 text-[11px] text-slate-500 leading-snug">
        <?= $stabilityDesc ?>
    </p>
        </div>

        <!-- Adaptasi -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
           <p class="text-xs font-semibold text-slate-500 uppercase">Adaptasi</p>
    <p class="mt-1 text-3xl font-semibold text-amber-600">
        <?= number_format($adapt, 1) ?>
    </p>
    <p class="mt-1 text-xs text-slate-500">
        Level: <?= $adaptLevel ?>
    </p>
    <p class="mt-0.5 text-[11px] text-slate-500 leading-snug">
        <?= $adaptDesc ?>
    </p>
        </div>
    </section>

    <!-- Konsentrasi & Pola Kerja -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- KONSENTRASI -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">Konsentrasi</p>

            <?php
            // Pilih warna badge berdasarkan trend
            $trendClass = 'bg-amber-50 border-amber-200 text-amber-700';
            if ($trend === 'meningkat') {
                $trendClass = 'bg-emerald-50 border-emerald-200 text-emerald-700';
            } elseif ($trend === 'menurun') {
                $trendClass = 'bg-rose-50 border-rose-200 text-rose-700';
            }
            ?>

            <p class="mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-medium <?= $trendClass ?>">
                <?= htmlspecialchars($trend ?: '-', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p class="mt-2 text-[11px] text-slate-500 leading-relaxed">
                <?= htmlspecialchars($concentrationDesc, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <!-- POLA KERJA -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">Pola Kerja</p>

            <p class="mt-2 inline-flex items-center rounded-full bg-indigo-50 border border-indigo-200
                      px-3 py-1 text-xs font-medium text-indigo-700">
                <?= htmlspecialchars($pattern ?: '-', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p class="mt-2 text-[11px] text-slate-500 leading-relaxed">
                <?= htmlspecialchars($patternDesc, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
    </section>


    <!-- Grafik -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 px-5 py-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <p class="text-[11px] font-semibold text-slate-500 uppercase">
                    Grafik Produktivitas per Interval
                </p>
                <p class="text-xs text-slate-600 mt-1">
                    Menampilkan jumlah item yang dikerjakan dan jawaban benar di setiap interval tes.
                </p>
            </div>
        </div>

        <div class="h-64">
            <canvas id="productivityChart"></canvas>
        </div>
    </section>

    <!-- Tabel Rincian Per Interval -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 px-5 py-4">
        <div class="flex items-center justify-between mb-3">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">
                Rincian Per Interval
            </p>
            <p class="text-[11px] text-slate-500">
                Total interval: <?= count($lines) ?>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                <tr class="border-b border-slate-100 bg-slate-50/60">
                    <th class="text-left py-2 px-2 font-semibold text-slate-600">Interval</th>
                    <th class="text-right py-2 px-2 font-semibold text-slate-600">Total item</th>
                    <th class="text-right py-2 px-2 font-semibold text-slate-600">Jawaban benar</th>
                    <th class="text-right py-2 px-2 font-semibold text-slate-600">Jawaban salah</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($lines)): ?>
                    <?php foreach ($lines as $i => $line): ?>
                        <?php
                        $ti = (int)($line['total_items'] ?? 0);
                        $co = (int)($line['correct'] ?? 0);
                        $wr = max($ti - $co, 0);
                        ?>
                        <tr class="border-b border-slate-50 hover:bg-slate-50/60">
                            <td class="py-1.5 px-2 text-slate-700">Interval <?= $i + 1 ?></td>
                            <td class="py-1.5 px-2 text-right font-mono text-slate-800"><?= $ti ?></td>
                            <td class="py-1.5 px-2 text-right font-mono text-emerald-700"><?= $co ?></td>
                            <td class="py-1.5 px-2 text-right font-mono text-rose-600"><?= $wr ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="py-3 px-2 text-center text-slate-500">
                            Tidak ada data interval yang tersimpan.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<!-- Script Chart -->
<script>
    (function () {
        const labels = <?= $labelsJson ?>;
        const productivityData = <?= $productivityJson ?>;
        const correctData = <?= $correctJson ?>;

        const ctx = document.getElementById('productivityChart').getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total item dikerjakan',
                        data: productivityData,
                        borderColor: 'rgba(79, 70, 229, 1)',      // indigo
                        backgroundColor: 'rgba(79, 70, 229, 0.15)',
                        tension: 0.25,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 3
                    },
                    {
                        label: 'Jawaban benar',
                        data: correctData,
                        borderColor: 'rgba(16, 185, 129, 1)',    // emerald
                        backgroundColor: 'rgba(16, 185, 129, 0.15)',
                        tension: 0.25,
                        fill: false,
                        borderWidth: 2,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            boxWidth: 10,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Interval (garis Kraeplin)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Jumlah item'
                        },
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    })();
</script>

</body>
</html>
