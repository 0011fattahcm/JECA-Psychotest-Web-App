<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold text-gray-900">
                Paket Stimulus TAM
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Atur gambar stimulus global dan durasi tampilan serta waktu menjawab.
                Stimulus ini akan digunakan untuk semua tes TAM.
            </p>
        </div>

        <!-- Card utama -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
            <!-- Info saat ini -->
            <div class="flex items-start justify-between gap-6">
                <div class="space-y-2">
                    <h2 class="text-sm font-semibold text-gray-700">Konfigurasi Saat Ini</h2>
                    <dl class="text-sm text-gray-600 space-y-1">
                        <div class="flex items-center">
                            <dt class="w-40 text-gray-500">Durasi tampilan:</dt>
                            <dd class="font-semibold">
                                <?= (int)$package['duration_display'] ?> menit
                            </dd>
                        </div>
                        <div class="flex items-center">
                            <dt class="w-40 text-gray-500">Durasi menjawab:</dt>
                            <dd class="font-semibold">
                                <?= (int)$package['duration_answer'] ?> menit
                            </dd>
                        </div>
                        <div class="flex items-center">
                            <dt class="w-40 text-gray-500">Status stimulus:</dt>
                            <dd class="font-semibold">
                                <?php if (!empty($package['image_path'])): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                                 bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Sudah diupload
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                                                 bg-red-50 text-red-700 border border-red-200">
                                        Belum ada stimulus
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Preview stimulus -->
                <div class="flex flex-col items-end gap-2">
                    <span class="text-xs font-medium text-gray-500">Preview Stimulus</span>
                    <div class="w-64 h-40 bg-gray-50 border border-dashed border-gray-300 rounded-lg
                                flex items-center justify-center overflow-hidden">
                        <?php if (!empty($package['image_path'])): ?>
                            <img id="preview_stimulus"
                                 src="<?= $package['image_path'] ?>"
                                 alt="Stimulus TAM"
                                 class="max-w-full max-h-full object-contain">
                        <?php else: ?>
                            <img id="preview_stimulus"
                                 src=""
                                 alt="Stimulus TAM"
                                 class="max-w-full max-h-full object-contain hidden">
                            <span class="text-xs text-gray-400 text-center px-4">
                                Belum ada gambar stimulus. Upload gambar baru di form di bawah.
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Form pengaturan -->
            <form action="index.php?page=admin-tam-package-save"
                  method="POST" enctype="multipart/form-data"
                  class="space-y-6 pt-4 border-t border-gray-100">

                <input type="hidden" name="old_image_path"
                       value="<?= htmlspecialchars($package['image_path'] ?? '') ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Upload gambar stimulus -->
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            Gambar Stimulus
                        </label>
                        <p class="text-xs text-gray-500 mb-1">
                            Gunakan gambar resolusi tinggi (misalnya 1920×1080). Format yang didukung: JPG, PNG, WEBP.
                        </p>
                        <input type="file" name="image" accept="image/*"
                               class="block w-full text-sm text-gray-700
                                      file:mr-3 file:py-2 file:px-4 file:rounded-lg
                                      file:border-0 file:text-sm file:font-semibold
                                      file:bg-indigo-50 file:text-indigo-700
                                      hover:file:bg-indigo-100"
                               onchange="previewImage(this, 'preview_stimulus')">
                    </div>

                    <!-- Durasi -->
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Durasi Tampilan Stimulus (menit)
                            </label>
                            <input type="number" name="duration_display" min="1" max="60"
                                   value="<?= (int)$package['duration_display'] ?>"
                                   class="w-full border rounded-lg px-3 py-2 text-sm
                                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Durasi Menjawab Soal (menit)
                            </label>
                            <input type="number" name="duration_answer" min="1" max="120"
                                   value="<?= (int)$package['duration_answer'] ?>"
                                   class="w-full border rounded-lg px-3 py-2 text-sm
                                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                </div>

                <!-- Tombol Simpan -->
                <div class="flex justify-end pt-2">
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold
                                   bg-indigo-600 text-white shadow-sm
                                   hover:bg-indigo-700 focus:outline-none
                                   focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function previewImage(input, imgId) {
        const file = input.files && input.files[0];
        const preview = document.getElementById(imgId);
        if (!preview) return;

        if (!file) {
            // kalau tidak ada file, jangan rubah preview lama
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
</script>
