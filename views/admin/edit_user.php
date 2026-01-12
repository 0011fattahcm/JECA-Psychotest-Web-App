<?php
// views/admin/edit_user.php

define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>

<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 min-h-screen bg-slate-100">
    <div class="max-w-4xl mx-auto px-6 py-10">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-semibold text-slate-900">Edit User</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Perbarui data dasar peserta tes. Desain form dibuat nyaman untuk diisi.
                </p>
            </div>

            <a href="index.php?page=admin-users"
               class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition">
                &larr; Kembali
            </a>
        </div>

        <!-- Card Form -->
        <div class="rounded-3xl border border-slate-200 bg-white/90 shadow-lg shadow-slate-200/70 backdrop-blur">
            <?php if (!empty($user)): ?>
                <div class="border-b border-slate-100 px-6 py-4">
                    <p class="text-sm font-medium text-slate-700">
                        Informasi User
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        Pastikan nama dan tanggal lahir sesuai data identitas peserta.
                    </p>
                </div>

                <form action="index.php?page=admin-user-update-process" method="POST" class="px-6 py-6 space-y-6">

                    <!-- hidden id -->
                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

                    <!-- GRID 2 kolom -->
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Kode user (readonly) -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">
                                Kode User
                            </label>
                            <div
                                class="flex items-center rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm
                                       shadow-inner focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/60 transition">
                                <span class="mr-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-[11px] font-semibold text-indigo-600">
                                    ID
                                </span>
                                <input
                                    type="text"
                                    class="w-full border-none bg-transparent text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-0"
                                    value="<?= htmlspecialchars($user['user_code'] ?? '-', ENT_QUOTES, 'UTF-8') ?>"
                                    readonly
                                >
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">
                                Kode user dapat berubah otomatis bila tanggal lahir diganti.
                            </p>
                        </div>

                        <!-- Nama -->
                        <div class="md:col-span-2">
                            <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">
                                Nama Lengkap
                            </label>
                            <div
                                class="flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm
                                       focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/60 transition">
                                <span class="mr-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-50 text-[11px] font-semibold text-indigo-600">
                                    Nm
                                </span>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    required
                                    class="w-full border-none bg-transparent text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0"
                                    placeholder="Masukkan nama lengkap user"
                                    value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </div>
                        </div>

                        <!-- Tanggal lahir -->
                        <div class="md:col-span-1">
                            <label for="birthdate" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">
                                Tanggal Lahir
                            </label>
                            <div
                                class="flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm
                                       focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/60 transition">
                                <span class="mr-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-50 text-[11px] font-semibold text-indigo-600">
                                    DOB
                                </span>
                                <input
                                    type="date"
                                    id="birthdate"
                                    name="birthdate"
                                    required
                                    class="w-full border-none bg-transparent text-sm text-slate-800 focus:outline-none focus:ring-0"
                                    value="<?= htmlspecialchars($user['birthdate'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                >
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">
                                Format: YYYY-MM-DD (contoh: 2004-07-20).
                            </p>
                        </div>

                        <!-- (Opsional) Email atau keterangan lain – kalau mau ditambah nanti -->
                        <!--
                        <div class="md:col-span-1">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">
                                Email
                            </label>
                            <div
                                class="flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm
                                       focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/60 transition">
                                <span class="mr-3 inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-50 text-[11px] font-semibold text-indigo-600">
                                    @
                                </span>
                                <input
                                    type="email"
                                    class="w-full border-none bg-transparent text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0"
                                    placeholder="user@example.com"
                                >
                            </div>
                        </div>
                        -->
                    </div>

                    <!-- Footer actions -->
                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <a href="index.php?page=admin-users"
                           class="inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                            Batal
                        </a>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/30 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 transition">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="px-6 py-10 text-center">
                    <p class="text-sm text-red-500">
                        Data user tidak ditemukan. Silakan kembali ke halaman daftar user.
                    </p>
                    <a href="index.php?page=admin-users"
                       class="mt-4 inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        &larr; Kembali
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
