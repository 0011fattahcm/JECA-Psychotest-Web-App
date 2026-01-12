<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-6">
            Edit Soal TAM
        </h1>

        <form action="index.php?page=admin-tam-edit-process" method="POST"
              class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">

            <input type="hidden" name="id" value="<?= $row['id'] ?>">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Soal
                </label>
                <textarea name="question" rows="3" required
                          class="w-full border rounded-lg px-3 py-2 text-sm
                                 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"><?= htmlspecialchars($row['question']) ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Opsi A
                    </label>
                    <input type="text" name="option_a"
                           value="<?= htmlspecialchars($row['option_a']) ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Opsi B
                    </label>
                    <input type="text" name="option_b"
                           value="<?= htmlspecialchars($row['option_b']) ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Opsi C
                    </label>
                    <input type="text" name="option_c"
                           value="<?= htmlspecialchars($row['option_c']) ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">
                        Opsi D
                    </label>
                    <input type="text" name="option_d"
                           value="<?= htmlspecialchars($row['option_d']) ?>"
                           class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Jawaban Benar
                </label>
                <select name="correct_option" required
                        class="border rounded-lg px-3 py-2 text-sm
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Pilih jawaban</option>
                    <option value="A" <?= $row['correct_option']==='A' ? 'selected' : '' ?>>A</option>
                    <option value="B" <?= $row['correct_option']==='B' ? 'selected' : '' ?>>B</option>
                    <option value="C" <?= $row['correct_option']==='C' ? 'selected' : '' ?>>C</option>
                    <option value="D" <?= $row['correct_option']==='D' ? 'selected' : '' ?>>D</option>
                </select>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold
                               bg-blue-600 text-white shadow-sm hover:bg-blue-700
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
