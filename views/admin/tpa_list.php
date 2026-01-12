<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900">
                Daftar Soal TPA
                <span class="text-indigo-600">
                    (<?= ucfirst($category) ?> – Sesi <?= $session ?>)
                </span>
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Kelola soal untuk kategori dan sesi ini. Soal bisa berupa teks, gambar, atau kombinasi keduanya.
            </p>
        </div>

        <a href="index.php?page=admin-tpa-add&category=<?= $category ?>&session=<?= $session ?>"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold
                  bg-indigo-600 text-white shadow-sm hover:bg-indigo-700
                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            <span class="mr-2 text-lg">＋</span>
            Tambah Soal
        </a>
    </div>

    <!-- Card tabel -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
            <h2 class="text-sm font-semibold text-gray-700">Soal Terdaftar</h2>
            <span class="text-xs text-gray-400">
                Total: <?= $result->num_rows ?> soal
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                <tr class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-6 py-3 text-left w-16">ID</th>
                    <th class="px-6 py-3 text-left">Soal</th>
                    <th class="px-6 py-3 text-left w-40">Jenis</th>
                    <th class="px-6 py-3 text-center w-32">Jawaban Benar</th>
                    <th class="px-6 py-3 text-center w-40">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $hasText  = !empty($row['question_text']);
                    $hasImage = !empty($row['question_image']);

                    if ($hasText && $hasImage) {
                        $typeLabel = 'Teks + Gambar';
                        $typeColor = 'bg-indigo-50 text-indigo-700 border-indigo-200';
                    } elseif ($hasImage) {
                        $typeLabel = 'Gambar';
                        $typeColor = 'bg-pink-50 text-pink-700 border-pink-200';
                    } else {
                        $typeLabel = 'Teks';
                        $typeColor = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    }

                    $questionPreview = '';
                    if ($hasText) {
                        $questionPreview = strip_tags($row['question_text']);
                        if (strlen($questionPreview) > 120) {
                            $questionPreview = substr($questionPreview, 0, 120) . '...';
                        }
                    }
                    ?>
                    <tr class="hover:bg-gray-50/80 transition-colors">
                        <!-- ID -->
                        <td class="px-6 py-4 text-gray-600 font-medium align-top">
                            <?= $row['id'] ?>
                        </td>

                        <!-- Soal -->
                        <td class="px-6 py-4 align-top">
                            <?php if ($hasText): ?>
                                <div class="text-gray-800 text-sm font-medium">
                                    <?= htmlspecialchars($questionPreview) ?>
                                </div>
                            <?php else: ?>
                                <div class="italic text-gray-400 text-sm">
                                    (Tidak ada teks soal)
                                </div>
                            <?php endif; ?>

                            <?php if ($hasImage): ?>
                                <div class="mt-1 flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full
                                                 bg-gray-100 text-[11px] text-gray-600 border border-gray-200">
                                        Soal bergambar
                                    </span>
                                    <img src="<?= $row['question_image'] ?>"
                                         alt="Preview soal"
                                         class="h-8 w-auto rounded border border-gray-200 object-contain">
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Jenis -->
                        <td class="px-6 py-4 align-top">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border <?= $typeColor ?>">
                                <?= $typeLabel ?>
                            </span>
                        </td>

                        <!-- Jawaban Benar -->
                        <td class="px-6 py-4 text-center align-top">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full
                                         bg-gray-900 text-white text-sm font-semibold">
                                <?= strtoupper($row['correct_option']) ?>
                            </span>
                        </td>

                        <!-- Aksi -->
                        <td class="px-6 py-4 text-center align-top">
                            <div class="inline-flex items-center space-x-2">
                                <a href="index.php?page=admin-tpa-edit&id=<?= $row['id'] ?>"
                                   class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold
                                          border border-indigo-200 text-indigo-700 bg-indigo-50
                                          hover:bg-indigo-100 hover:border-indigo-300 transition">
                                    Edit
                                </a>

                                <button type="button"
                                        onclick="openDeleteModal('index.php?page=admin-tpa-delete&id=<?= $row['id'] ?>&category=<?= $category ?>&session=<?= $session ?>')"
                                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold
                                               border border-red-200 text-red-700 bg-red-50
                                               hover:bg-red-100 hover:border-red-300 transition">
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>

                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">
                            Belum ada soal untuk kategori dan sesi ini.
                            <a href="index.php?page=admin-tpa-add&category=<?= $category ?>&session=<?= $session ?>"
                               class="text-indigo-600 hover:underline ml-1">
                                Tambahkan sekarang
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Hapus -->
<div id="deleteModal"
     class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 mr-3">
                !
            </div>
            <h3 class="text-base font-semibold text-gray-900">Hapus Soal</h3>
        </div>
        <div class="px-6 py-4 text-sm text-gray-600">
            <p>Apakah Anda yakin ingin menghapus soal ini?</p>
            <p class="mt-1 text-xs text-gray-400">
                Tindakan ini tidak dapat dibatalkan dan akan menghapus soal dari tes TPA.
            </p>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end space-x-3">
            <button type="button"
                    onclick="closeDeleteModal()"
                    class="px-4 py-2 rounded-lg text-sm font-semibold
                           border border-gray-200 text-gray-700 bg-white
                           hover:bg-gray-100 transition">
                Batal
            </button>
            <button type="button"
                    onclick="confirmDelete()"
                    class="px-4 py-2 rounded-lg text-sm font-semibold
                           bg-red-600 text-white hover:bg-red-700
                           shadow-sm transition">
                Ya, Hapus
            </button>
        </div>
    </div>
</div>

<script>
    let currentDeleteUrl = '';

    function openDeleteModal(url) {
        currentDeleteUrl = url;
        const modal = document.getElementById('deleteModal');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-y-hidden');
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-y-hidden');
        currentDeleteUrl = '';
    }

    function confirmDelete() {
        if (!currentDeleteUrl) return;
        window.location.href = currentDeleteUrl;
    }

    // Tutup modal kalau klik di luar kotak
    document.getElementById('deleteModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
</script>
