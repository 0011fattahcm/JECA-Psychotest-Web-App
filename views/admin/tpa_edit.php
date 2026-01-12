<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <h1 class="text-3xl font-bold mb-6">Edit Soal TPA</h1>

    <form action="index.php?page=admin-tpa-edit-process" method="POST"
          enctype="multipart/form-data"
          class="bg-white p-6 rounded shadow space-y-4 max-w-4xl">

        <input type="hidden" name="id" value="<?= $row['id'] ?>">
        <input type="hidden" name="category" value="<?= $row['category'] ?>">
        <input type="hidden" name="session" value="<?= $row['session'] ?>">

        <!-- path lama -->
        <input type="hidden" name="old_question_image" value="<?= $row['question_image'] ?>">
        <input type="hidden" name="old_option_a_image" value="<?= $row['option_a_image'] ?>">
        <input type="hidden" name="old_option_b_image" value="<?= $row['option_b_image'] ?>">
        <input type="hidden" name="old_option_c_image" value="<?= $row['option_c_image'] ?>">
        <input type="hidden" name="old_option_d_image" value="<?= $row['option_d_image'] ?>">

        <div class="text-sm text-gray-600 mb-2">
            Kategori: <span class="font-semibold"><?= ucfirst($row['category']) ?></span>,
            Sesi: <span class="font-semibold"><?= $row['session'] ?></span>
        </div>

        <!-- SOAL -->
        <div class="space-y-2">
            <label class="font-semibold">Soal (Teks)</label>
            <textarea name="question_text" rows="3"
                      class="w-full border rounded px-3 py-2"><?= htmlspecialchars($row['question_text']) ?></textarea>

            <label class="font-semibold block mt-2">Soal (Gambar)</label>
            <?php if (!empty($row['question_image'])): ?>
                <img src="<?= $row['question_image'] ?>" alt="Question Image"
                     class="h-24 mb-2 rounded border">
            <?php endif; ?>
            <input type="file" name="question_image" accept="image/*"
                   class="block w-full text-sm text-gray-700">
        </div>

        <!-- OPSI -->
        <div class="grid grid-cols-2 gap-6">
            <div class="space-y-2">
                <label class="font-semibold">Opsi A (Teks)</label>
                <input type="text" name="option_a_text"
                       class="w-full border rounded px-3 py-2"
                       value="<?= htmlspecialchars($row['option_a_text']) ?>">

                <label class="font-semibold block mt-2">Opsi A (Gambar)</label>
                <?php if (!empty($row['option_a_image'])): ?>
                    <img src="<?= $row['option_a_image'] ?>" alt="Option A Image"
                         class="h-20 mb-2 rounded border">
                <?php endif; ?>
                <input type="file" name="option_a_image" accept="image/*"
                       class="block w-full text-sm text-gray-700">
            </div>

            <div class="space-y-2">
                <label class="font-semibold">Opsi B (Teks)</label>
                <input type="text" name="option_b_text"
                       class="w-full border rounded px-3 py-2"
                       value="<?= htmlspecialchars($row['option_b_text']) ?>">

                <label class="font-semibold block mt-2">Opsi B (Gambar)</label>
                <?php if (!empty($row['option_b_image'])): ?>
                    <img src="<?= $row['option_b_image'] ?>" alt="Option B Image"
                         class="h-20 mb-2 rounded border">
                <?php endif; ?>
                <input type="file" name="option_b_image" accept="image/*"
                       class="block w-full text-sm text-gray-700">
            </div>

            <div class="space-y-2">
                <label class="font-semibold">Opsi C (Teks)</label>
                <input type="text" name="option_c_text"
                       class="w-full border rounded px-3 py-2"
                       value="<?= htmlspecialchars($row['option_c_text']) ?>">

                <label class="font-semibold block mt-2">Opsi C (Gambar)</label>
                <?php if (!empty($row['option_c_image'])): ?>
                    <img src="<?= $row['option_c_image'] ?>" alt="Option C Image"
                         class="h-20 mb-2 rounded border">
                <?php endif; ?>
                <input type="file" name="option_c_image" accept="image/*"
                       class="block w-full text-sm text-gray-700">
            </div>

            <div class="space-y-2">
                <label class="font-semibold">Opsi D (Teks)</label>
                <input type="text" name="option_d_text"
                       class="w-full border rounded px-3 py-2"
                       value="<?= htmlspecialchars($row['option_d_text']) ?>">

                <label class="font-semibold block mt-2">Opsi D (Gambar)</label>
                <?php if (!empty($row['option_d_image'])): ?>
                    <img src="<?= $row['option_d_image'] ?>" alt="Option D Image"
                         class="h-20 mb-2 rounded border">
                <?php endif; ?>
                <input type="file" name="option_d_image" accept="image/*"
                       class="block w-full text-sm text-gray-700">
            </div>
        </div>

        <div>
            <label class="font-semibold">Jawaban Benar</label>
            <select name="correct_option" class="border rounded px-3 py-2 mt-1">
                <option value="A" <?= $row['correct_option']==='A' ? 'selected' : '' ?>>A</option>
                <option value="B" <?= $row['correct_option']==='B' ? 'selected' : '' ?>>B</option>
                <option value="C" <?= $row['correct_option']==='C' ? 'selected' : '' ?>>C</option>
                <option value="D" <?= $row['correct_option']==='D' ? 'selected' : '' ?>>D</option>
            </select>
        </div>

        <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Update
        </button>
    </form>
</div>
