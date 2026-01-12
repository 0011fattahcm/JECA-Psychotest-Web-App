<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold mb-6">Tambah Soal TPA</h1>

    <form action="index.php?page=admin-tpa-add-process" method="POST"
          enctype="multipart/form-data"
          class="bg-white p-6 rounded shadow space-y-4 max-w-4xl">

        <input type="hidden" name="category" value="<?= $category ?>">
        <input type="hidden" name="session" value="<?= $session ?>">

        <div class="text-sm text-gray-600 mb-2">
            Kategori: <span class="font-semibold"><?= ucfirst($category) ?></span>,
            Sesi: <span class="font-semibold"><?= $session ?></span>
        </div>

        <!-- SOAL -->
        <div class="space-y-2">
            <label class="font-semibold">Soal (Teks)</label>
            <textarea name="question_text" rows="3"
                      class="w-full border rounded px-3 py-2"
                      placeholder="Isi teks soal (boleh kosong jika hanya gambar)"></textarea>

            <label class="font-semibold block mt-2">Soal (Gambar)</label>
            <input type="file" name="question_image" accept="image/*"
                   class="block w-full text-sm text-gray-700"
                   onchange="previewImage(this, 'preview_question_image')">

            <div class="mt-2">
                <img id="preview_question_image"
                     class="h-20 rounded border hidden"
                     alt="Preview soal">
            </div>

            <p class="text-xs text-gray-500">Boleh teks saja, gambar saja, atau keduanya.</p>
        </div>

        <!-- OPSI -->
        <div class="grid grid-cols-2 gap-6">
            <!-- Opsi A -->
            <div class="space-y-2">
                <label class="font-semibold">Opsi A (Teks)</label>
                <input type="text" name="option_a_text"
                       class="w-full border rounded px-3 py-2">

                <label class="font-semibold block mt-2">Opsi A (Gambar)</label>
                <input type="file" name="option_a_image" accept="image/*"
                       class="block w-full text-sm text-gray-700"
                       onchange="previewImage(this, 'preview_option_a_image')">

                <div class="mt-2">
                    <img id="preview_option_a_image"
                         class="h-16 rounded border hidden"
                         alt="Preview opsi A">
                </div>
            </div>

            <!-- Opsi B -->
            <div class="space-y-2">
                <label class="font-semibold">Opsi B (Teks)</label>
                <input type="text" name="option_b_text"
                       class="w-full border rounded px-3 py-2">

                <label class="font-semibold block mt-2">Opsi B (Gambar)</label>
                <input type="file" name="option_b_image" accept="image/*"
                       class="block w-full text-sm text-gray-700"
                       onchange="previewImage(this, 'preview_option_b_image')">

                <div class="mt-2">
                    <img id="preview_option_b_image"
                         class="h-16 rounded border hidden"
                         alt="Preview opsi B">
                </div>
            </div>

            <!-- Opsi C -->
            <div class="space-y-2">
                <label class="font-semibold">Opsi C (Teks)</label>
                <input type="text" name="option_c_text"
                       class="w-full border rounded px-3 py-2">

                <label class="font-semibold block mt-2">Opsi C (Gambar)</label>
                <input type="file" name="option_c_image" accept="image/*"
                       class="block w-full text-sm text-gray-700"
                       onchange="previewImage(this, 'preview_option_c_image')">

                <div class="mt-2">
                    <img id="preview_option_c_image"
                         class="h-16 rounded border hidden"
                         alt="Preview opsi C">
                </div>
            </div>

            <!-- Opsi D -->
            <div class="space-y-2">
                <label class="font-semibold">Opsi D (Teks)</label>
                <input type="text" name="option_d_text"
                       class="w-full border rounded px-3 py-2">

                <label class="font-semibold block mt-2">Opsi D (Gambar)</label>
                <input type="file" name="option_d_image" accept="image/*"
                       class="block w-full text-sm text-gray-700"
                       onchange="previewImage(this, 'preview_option_d_image')">

                <div class="mt-2">
                    <img id="preview_option_d_image"
                         class="h-16 rounded border hidden"
                         alt="Preview opsi D">
                </div>
            </div>
        </div>

        <div>
            <label class="font-semibold">Jawaban Benar</label>
            <select name="correct_option" class="border rounded px-3 py-2 mt-1" required>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
            </select>
        </div>

        <button class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
            Simpan
        </button>
    </form>
</div>

<script>
    function previewImage(input, imgId) {
        const file = input.files && input.files[0];
        const preview = document.getElementById(imgId);

        if (!preview) return;

        if (!file) {
            preview.classList.add('hidden');
            preview.src = '';
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
