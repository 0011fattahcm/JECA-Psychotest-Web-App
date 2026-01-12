<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';

$duration        = (int)($settings['duration'] ?? 20);
$intervalSeconds = (int)($settings['interval_seconds'] ?? 10);
$currentDuration = isset($settings['duration']) ? (int)$settings['duration'] : 15;
if ($currentDuration < 5)  $currentDuration = 5;
if ($currentDuration > 30) $currentDuration = 30;

// Mapping durasi ke lebar bar supaya "pas" dengan label 5, 15, 20, 30
$duration = $currentDuration;

if ($duration <= 15) {
    // 5–15 menit → 0–33.33%
    $progressWidth = ($duration - 5) / 10 * 33.33;
} elseif ($duration <= 20) {
    // 15–20 menit → 33.33–66.66%
    $progressWidth = 33.33 + ($duration - 15) / 5 * 33.33;
} else {
    // 20–30 menit → 66.66–100%
    $progressWidth = 66.66 + ($duration - 20) / 10 * 33.34;
}

// Clamp aja buat aman
$progressWidth = max(0, min(100, $progressWidth));
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900">
                Pengaturan Tes Kraeplin
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Atur durasi tes Kraeplin yang akan digunakan untuk semua peserta.
                Rekomendasi: antara <span class="font-semibold">15–20 menit</span>.
            </p>
        </div>

        <!-- Card utama -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
            <!-- Info durasi saat ini -->
            <div class="flex items-start justify-between gap-6">
                <div class="space-y-2">
                    <h2 class="text-sm font-semibold text-gray-700">
                        Konfigurasi Saat Ini
                    </h2>
                    <p class="text-sm text-gray-600">
                        Durasi tes:
                        <span class="font-bold text-indigo-600">
                            <?= $currentDuration ?> menit
                        </span>
                    </p>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Durasi mempengaruhi jumlah baris penjumlahan yang bisa dikerjakan peserta,
                        yang kemudian dipakai untuk menilai produktivitas, ketelitian, stabilitas,
                        konsentrasi, dan adaptasi.
                    </p>
                </div>

                <div class="hidden md:block">
                    <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3 text-xs text-indigo-700 max-w-xs">
                        <div class="font-semibold mb-1">Tips penentuan durasi</div>
                        <ul class="list-disc list-inside space-y-1">
                            <li><span class="font-medium">5–10 menit</span>: screening cepat.</li>
                            <li><span class="font-medium">15–20 menit</span>: tes standar.</li>
                            <li><span class="font-medium">>20 menit</span>: untuk observasi daya tahan.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Form pengaturan -->
            <form action="index.php?page=admin-kraeplin-settings-save" method="POST"
                  class="space-y-5 pt-4 border-t border-gray-100">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Durasi Tes (menit)
                    </label>

                    <div class="flex items-center gap-4">
                        <input type="number" name="duration"
                               min="5" max="30"
                               value="<?= $currentDuration ?>"
                               class="w-32 border rounded-lg px-3 py-2 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">

                        <div class="flex-1">
                            <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                <div class="bg-indigo-500 h-2 rounded-full"
                                     style="width: <?= $progressWidth ?>%">
                                </div>
                            </div>
                            <div class="flex justify-between text-[11px] text-gray-400">
                                <span>5</span>
                                <span>15</span>
                                <span>20</span>
                                <span>30</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 mt-2">
                        Nilai minimal <span class="font-semibold">5 menit</span> dan maksimal
                        <span class="font-semibold">30 menit</span>.
                        Angka di luar range akan otomatis disesuaikan.
                    </p>
                </div>

                <form action="index.php?page=admin-kraeplin-settings-save" method="post" class="space-y-6">
    <!-- Durasi Tes (slider yang sudah kamu punya) -->
    <!-- ... kode durasi yang sekarang ... -->

    <!-- Interval Skor -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mt-6">
        <h3 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
            <span>📊 Interval Skor</span>
            <span class="text-[11px] font-normal text-slate-500">
                (jarak waktu per baris / perhitungan, dalam detik)
            </span>
        </h3>

        <div class="mt-4 flex items-center gap-4">
            <select name="interval_seconds"
                    class="rounded-lg border-slate-300 text-sm px-3 py-1.5">
                <?php foreach ([5,10,15,20,25,30] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $intervalSeconds === $opt ? 'selected' : '' ?>>
                        <?= $opt ?> detik
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="text-sm text-slate-700">
                Interval aktif: <strong><?= $intervalSeconds ?> detik</strong> per baris.
            </span>
        </div>

        <p class="mt-2 text-[11px] text-slate-500 leading-relaxed">
            Contoh: jika interval 15 detik dan durasi 20 menit, maka sistem akan
            mengukur performa per <strong>15 detik</strong> dan memindahkan Anda ke baris
            berikutnya setiap 15 detik.
        </p>
    </div>

    <button type="submit"
            class="mt-4 inline-flex items-center justify-center rounded-xl bg-indigo-600 text-white text-sm font-semibold px-4 py-2.5 hover:bg-indigo-700">
        Simpan Pengaturan
    </button>
</form>
                </div>
            </form>
        </div>
    </div>
</div>
