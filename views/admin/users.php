<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>

<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-10 bg-gray-100 min-h-screen">

    <h1 class="text-2xl font-bold text-gray-800 mb-8">Kelola User</h1>

    <div class="bg-white shadow rounded-lg p-6">

        <div class="flex justify-between mb-6">
            <h2 class="text-lg font-semibold">Daftar User</h2>
            <a href="index.php?page=admin-add-user"
               class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                + Tambah User
            </a>
        </div>

        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-gray-200 text-gray-700">
                    <th class="p-3 border">ID</th>
                    <th class="p-3 border">Nama</th>
                    <th class="p-3 border">Tanggal Lahir</th>
                    <th class="p-3 border">User Code</th>
                    <th class="p-3 border">Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($row = $users->fetch_assoc()): ?>
                    <tr class="border hover:bg-gray-50">
                        <td class="p-3"><?= $row['id'] ?></td>
                        <td class="p-3"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="p-3"><?= $row['birthdate'] ?></td>
                        <td class="p-3 font-semibold text-blue-700"><?= $row['user_code'] ?></td>

                        <td class="p-3 flex gap-2">
                            <a href="index.php?page=admin-edit-user&id=<?= $row['id'] ?>"
                               class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700">
                                Edit
                            </a>

                            <a href="index.php?page=admin-delete-user&id=<?= $row['id'] ?>"
                               onclick="return confirm('Hapus user ini?')"
                               class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700">
                                Hapus
                            </a>
                        </td>

                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>
</div>
