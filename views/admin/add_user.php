<?php
// views/admin/add_user.php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';
?>
<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 min-h-screen bg-gray-100">
    <div class="max-w-4xl mx-auto px-8 py-10 space-y-8">

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wider text-indigo-500 uppercase mb-1">
                    Manajemen Peserta
                </p>
                <h1 class="text-3xl font-semibold text-gray-900">Tambah User</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Masukkan data peserta. <span class="font-medium text-gray-700">User Code</span> akan dibuat
                    otomatis dari ID dan tanggal lahir setelah data tersimpan.
                </p>
            </div>

            <a href="index.php?page=admin-users"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300
                      bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2
                      focus:ring-offset-2 focus:ring-indigo-500">
                Kembali ke daftar
            </a>
        </div>

        <!-- Card Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200">
            <!-- Card Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Form Data User</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Lengkapi minimal nama lengkap dan tanggal lahir peserta.
                    </p>
                </div>
                <div class="hidden sm:flex items-center text-xs text-gray-400 space-x-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Ready to save</span>
                </div>
            </div>

            <!-- Card Body -->
            <form action="index.php?page=admin-user-add-process" method="POST" class="px-6 py-6 space-y-6">

                <!-- Nama Lengkap -->
                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-gray-700">
                        Nama Lengkap
                        <span class="text-red-500">*</span>
                    </label>

                    <div class="relative">
                        <input
                            type="text"
                            name="name"
                            id="name"
                            required
                            placeholder="Contoh: Difa Aufar Hakim"
                            class="block w-full rounded-xl border border-gray-300 bg-gray-50/60 px-4 py-2.5
                                   text-sm text-gray-900 shadow-sm
                                   focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/60
                                   placeholder:text-gray-400 transition-all"
                        >
                        <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                            <span class="text-gray-300 text-xs font-medium">A-Z</span>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        Gunakan nama lengkap sesuai KTP / paspor pesertа.
                    </p>
                </div>

                <!-- Tanggal Lahir -->
                <div class="space-y-1.5">
                    <label for="birthdate" class="block text-sm font-medium text-gray-700">
                        Tanggal Lahir
                        <span class="text-red-500">*</span>
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_auto] gap-3 md:items-center">
                        <!-- Input date -->
                        <div class="relative">
                            <input
                                type="date"
                                name="birthdate"
                                id="birthdate"
                                required
                                class="block w-full rounded-xl border border-gray-300 bg-gray-50/60 px-4 py-2.5
                                       text-sm text-gray-900 shadow-sm
                                       focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/60
                                       placeholder:text-gray-400 transition-all"
                            >
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2
                                             2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Preview User Code -->
                        <div class="flex items-center text-xs text-gray-500 bg-indigo-50/60 border border-indigo-100
                                    rounded-xl px-3 py-2 space-x-2">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full
                                         bg-indigo-500 text-[10px] font-bold text-white">
                                ID
                            </span>
                            <div>
                                <div class="text-[11px] uppercase tracking-wide text-indigo-500 font-semibold">
                                    Contoh User Code
                                </div>
                                <div id="userCodePreview" class="font-mono text-[11px] text-gray-800">
                                    ID-YYYYMMDD
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">
                        Format User Code: <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded-md text-gray-800">
                            ID-YYYYMMDD
                        </span>.
                        Contoh: <span class="font-mono text-gray-800">15-20040720</span>.
                    </p>
                </div>

                <!-- Info User Code -->
                <div class="rounded-xl border border-dashed border-indigo-200 bg-indigo-50/60 px-4 py-3 text-xs">
                    <div class="flex items-start space-x-2">
                        <div class="mt-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 1 0 10 10A10
                                         10 0 0 0 12 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-indigo-700">
                                Field User Code akan terisi otomatis.
                            </p>
                            <p class="mt-0.5 text-indigo-600/90">
                                Sistem akan membuat kode unik setelah data tersimpan, sehingga admin
                                tidak perlu menginput User Code secara manual.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="pt-2 flex items-center justify-end space-x-3">
                    <a href="index.php?page=admin-users"
                       class="inline-flex items-center px-4 py-2 rounded-xl border border-gray-300 bg-white
                              text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50
                              focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300">
                        Batal
                    </a>

                    <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 rounded-xl bg-indigo-600 text-sm
                                   font-semibold text-white shadow-sm hover:bg-indigo-700
                                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Simpan User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Preview simple User Code: ID-YYYYMMDD (ID diganti placeholder 15)
    const birthInput = document.getElementById('birthdate');
    const preview    = document.getElementById('userCodePreview');

    function updatePreview() {
        if (!birthInput.value) {
            preview.textContent = 'ID-YYYYMMDD';
            return;
        }
        const date = new Date(birthInput.value);
        if (isNaN(date.getTime())) {
            preview.textContent = 'ID-YYYYMMDD';
            return;
        }
        const y = date.getFullYear().toString();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        // Contoh ID tetap 15 agar konsisten dengan penjelasan
        preview.textContent = '15-' + y + m + d;
    }

    birthInput.addEventListener('change', updatePreview);
    birthInput.addEventListener('input', updatePreview);
</script>
