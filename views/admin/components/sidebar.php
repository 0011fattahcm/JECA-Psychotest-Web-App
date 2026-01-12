<?php
if (!defined('ADMIN_PAGE')) { die("Access Denied"); }
if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
// base URL menuju router utama
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base = ($basePath ? $basePath : '') . "/index.php?page=";

// current page + params
$currentPage = $_GET['page'] ?? '';
$currentCategory = $_GET['category'] ?? null;
$currentSession  = $_GET['session'] ?? null;

/**
 * Nav link generator (return string, bukan echo)
 * - Bisa match query params untuk menu tertentu (contoh TPA list)
 */
function navLink($label, $query, $base, $opts = []) {
    $currentPage     = $opts['currentPage'] ?? '';
    $currentCategory = $opts['currentCategory'] ?? null;
    $currentSession  = $opts['currentSession'] ?? null;

    $matchParams = $opts['matchParams'] ?? null; // ['category'=>'verbal','session'=>'1']
    $icon        = $opts['icon'] ?? '';
    $sub         = !empty($opts['sub']);
    $badge       = $opts['badge'] ?? null;

    $queryPage = explode("&", $query)[0];

    $isActive = ($currentPage === $queryPage);

    // Active yang lebih presisi untuk admin-tpa-list (cek category/session)
    if ($isActive && is_array($matchParams)) {
        if (isset($matchParams['category']) && $matchParams['category'] !== $currentCategory) $isActive = false;
        if (isset($matchParams['session'])  && (string)$matchParams['session'] !== (string)$currentSession) $isActive = false;
    }

    $baseClass = "group flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium transition";
    $subClass  = $sub ? "ml-2" : "";

    if ($isActive) {
        $stateClass = "bg-slate-800/80 text-white shadow-sm ring-1 ring-white/10";
        $leftBar    = "<span class='absolute left-0 top-1/2 -translate-y-1/2 h-8 w-1 rounded-r-full bg-indigo-400'></span>";
    } else {
        $stateClass = "text-slate-200/90 hover:bg-slate-800/50 hover:text-white";
        $leftBar    = "";
    }

    $badgeHtml = "";
    if ($badge !== null) {
        $badgeHtml = "<span class='inline-flex items-center rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold text-slate-100/90 ring-1 ring-white/10'>{$badge}</span>";
    }

    $iconHtml = "";
    if (!empty($icon)) {
        $iconHtml = "<span class='shrink-0 text-slate-300 group-hover:text-white'>{$icon}</span>";
    }

    

    $href = $base . $query;

    return "
    <a href='{$href}' class='relative {$baseClass} {$subClass} {$stateClass}'>
        {$leftBar}
        <span class='flex items-center gap-2 min-w-0'>
            {$iconHtml}
            <span class='truncate'>{$label}</span>
        </span>
        {$badgeHtml}
    </a>";
}

function sectionButton($title, $id, $icon = '') {
    return "
    <button type='button' data-collapse-toggle='{$id}'
        class='w-full flex items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-[11px] font-semibold uppercase tracking-widest
               text-slate-400 hover:text-slate-200 hover:bg-slate-800/40 transition'>
        <span class='flex items-center gap-2'>
            {$icon}
            {$title}
        </span>
        <span class='collapse-caret transition' data-caret='{$id}'>
            <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
                <path d='m9 18 6-6-6-6'/>
            </svg>
        </span>
    </button>";
}

$iconDashboard = "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M3 13h8V3H3z'/><path d='M13 21h8v-8h-8z'/><path d='M13 3h8v8h-8z'/><path d='M3 21h8v-6H3z'/></svg>";
$iconUsers = "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>";
$iconList = "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><line x1='8' y1='6' x2='21' y2='6'/><line x1='8' y1='12' x2='21' y2='12'/><line x1='8' y1='18' x2='21' y2='18'/><line x1='3' y1='6' x2='3.01' y2='6'/><line x1='3' y1='12' x2='3.01' y2='12'/><line x1='3' y1='18' x2='3.01' y2='18'/></svg>";
$iconPlus = "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M12 5v14'/><path d='M5 12h14'/></svg>";
$iconDoc = "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/><path d='M14 2v6h6'/><path d='M16 13H8'/><path d='M16 17H8'/><path d='M10 9H8'/></svg>";
$iconActivity = "<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'>
  <path d='M3 3v18h18'/>
  <path d='M7 14l3-3 3 3 5-6'/>
</svg>";
?>

<style>
    .scrollbar-thin::-webkit-scrollbar { width: 6px; }
    .scrollbar-thin::-webkit-scrollbar-track { background: #0b1220; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background: #334155; border-radius: 999px; }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>

<!-- SIDEBAR -->
<aside class="fixed top-0 left-0 h-screen w-64 bg-slate-950 text-slate-200 shadow-xl flex flex-col">

    <!-- Header -->
    <div class="px-5 py-5 border-b border-white/5 bg-gradient-to-b from-slate-950 to-slate-950/40">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold tracking-[0.25em] text-indigo-400 uppercase">JECA Psychotest</p>
                <h1 class="mt-1 text-base font-extrabold text-white leading-tight">Admin Panel</h1>
                <p class="text-[11px] text-slate-400 mt-1">Kelola soal, hasil tes, dan peserta.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-white/5 px-2 py-1 text-[10px] font-semibold text-slate-200 ring-1 ring-white/10">
                v1
            </span>
        </div>

        <!-- Search menu -->
        <div class="mt-4">
            <div class="flex items-center gap-2 rounded-xl bg-white/5 ring-1 ring-white/10 px-3 py-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.3-4.3"></path>
                </svg>
                <input id="sidebarSearch" type="text" placeholder="Cari menu..."
                       class="w-full bg-transparent text-[12px] text-slate-200 placeholder:text-slate-500 outline-none" />
            </div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto scrollbar-thin px-3 py-4 space-y-2">

        <!-- Dashboard -->
        <div data-menu-item>
            <?= navLink("Dashboard", "admin-dashboard", $base, [
                'currentPage' => $currentPage,
                'icon' => $iconDashboard
            ]) ?>
        </div>

        <!-- USER -->
        <div class="pt-2">
            <?= sectionButton("User", "sec-user", "<span class='text-slate-400'>{$iconUsers}</span>") ?>
            <div id="sec-user" class="mt-1 space-y-1 pl-1" data-collapse>
                <div data-menu-item>
                    <?= navLink("Kelola User", "admin-users", $base, [
                        'currentPage' => $currentPage,
                        'sub' => true,
                        'icon' => $iconList
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Tambah User", "admin-add-user", $base, [
                        'currentPage' => $currentPage,
                        'sub' => true,
                        'icon' => $iconPlus
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- TPA -->
        <div class="pt-2">
            <?= sectionButton("Tes TPA", "sec-tpa", "<span class='text-slate-400'>{$iconDoc}</span>") ?>
            <div id="sec-tpa" class="mt-1 space-y-1 pl-1" data-collapse>
                <div data-menu-item>
                    <?= navLink("Verbal – Sesi 1", "admin-tpa-list&category=verbal&session=1", $base, [
                        'currentPage' => $currentPage,
                        'currentCategory' => $currentCategory,
                        'currentSession' => $currentSession,
                        'matchParams' => ['category'=>'verbal','session'=>'1'],
                        'sub' => true
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Verbal – Sesi 2", "admin-tpa-list&category=verbal&session=2", $base, [
                        'currentPage' => $currentPage,
                        'currentCategory' => $currentCategory,
                        'currentSession' => $currentSession,
                        'matchParams' => ['category'=>'verbal','session'=>'2'],
                        'sub' => true
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Verbal – Sesi 3", "admin-tpa-list&category=verbal&session=3", $base, [
                        'currentPage' => $currentPage,
                        'currentCategory' => $currentCategory,
                        'currentSession' => $currentSession,
                        'matchParams' => ['category'=>'verbal','session'=>'3'],
                        'sub' => true
                    ]) ?>
                </div>

                <div class="h-px bg-white/5 my-1"></div>

                <div data-menu-item>
                    <?= navLink("Kuantitatif", "admin-tpa-list&category=kuantitatif&session=1", $base, [
                        'currentPage' => $currentPage,
                        'currentCategory' => $currentCategory,
                        'currentSession' => $currentSession,
                        'matchParams' => ['category'=>'kuantitatif','session'=>'1'],
                        'sub' => true
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Logika", "admin-tpa-list&category=logika&session=1", $base, [
                        'currentPage' => $currentPage,
                        'currentCategory' => $currentCategory,
                        'currentSession' => $currentSession,
                        'matchParams' => ['category'=>'logika','session'=>'1'],
                        'sub' => true
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Spasial", "admin-tpa-list&category=spasial&session=1", $base, [
                        'currentPage' => $currentPage,
                        'currentCategory' => $currentCategory,
                        'currentSession' => $currentSession,
                        'matchParams' => ['category'=>'spasial','session'=>'1'],
                        'sub' => true
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- TAM -->
        <div class="pt-2">
            <?= sectionButton("Tes TAM", "sec-tam", "<span class='text-slate-400'>{$iconDoc}</span>") ?>
            <div id="sec-tam" class="mt-1 space-y-1 pl-1" data-collapse>
                <div data-menu-item>
                    <?= navLink("Paket Stimulus", "admin-tam-package", $base, [
                        'currentPage' => $currentPage,
                        'sub' => true
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Tambah Soal TAM", "admin-tam-add", $base, [
                        'currentPage' => $currentPage,
                        'sub' => true
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Daftar Soal TAM", "admin-tam-list", $base, [
                        'currentPage' => $currentPage,
                        'sub' => true
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- RESULTS -->
        <div class="pt-2" data-menu-item>
            <?= navLink("Results TPA & TAM", "admin-results", $base, [
                'currentPage' => $currentPage,
                'icon' => $iconList,
               
            ]) ?>
        </div>

        <!-- KRAEPLIN -->
        <div class="pt-2">
            <?= sectionButton("Tes Kraeplin", "sec-kr", "<span class='text-slate-400'>{$iconDoc}</span>") ?>
            <div id="sec-kr" class="mt-1 space-y-1 pl-1" data-collapse>
                <div data-menu-item>
                    <?= navLink("Pengaturan Durasi", "admin-kraeplin-settings", $base, [
                        'currentPage' => $currentPage,
                        'sub' => true
                    ]) ?>
                </div>
                <div data-menu-item>
                    <?= navLink("Hasil Tes Kraeplin", "admin-kraeplin-results", $base, [
                        'currentPage' => $currentPage,
                        'sub' => true
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- ACTIVITY LOGS -->
<div class="pt-2" data-menu-item>
    <?= navLink("Activity Logs", "admin-activity-list", $base, [
        'currentPage' => $currentPage,
        'icon' => $iconActivity
    ]) ?>
</div>


        <!-- Divider -->
        <div class="h-px bg-white/5 my-2"></div>

        <!-- Logout (trigger modal) -->
        <button type="button" id="btnAdminLogout"
            class="w-full flex items-center justify-between rounded-xl px-3 py-2.5 text-[13px] font-semibold
                   text-rose-300 hover:bg-rose-500/10 hover:text-rose-200 transition">
            <span class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Logout
            </span>
        </button>

    </nav>

    <!-- Footer -->
    <div class="px-4 py-4 border-t border-white/5 text-[11px] text-slate-500">
        <div class="flex items-center justify-between">
            <span>JECA Psychotest App</span>
            <span class="font-mono"><?= date('Y') ?></span>
        </div>
    </div>
</aside>

<!-- Logout Modal -->
<div id="adminLogoutModal" class="fixed inset-0 z-[9999] hidden">
<div id="adminLogoutBackdrop" class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-slate-950 text-slate-100 border border-white/10 shadow-2xl">
            <div class="px-5 py-4 border-b border-white/10">
                <p class="text-xs font-semibold tracking-[0.25em] text-indigo-400 uppercase">Konfirmasi</p>
                <h3 class="mt-1 text-lg font-extrabold">Keluar dari Admin Panel?</h3>
                <p class="mt-2 text-sm text-slate-300 leading-relaxed">
                    Anda akan logout dari sistem admin. Pastikan perubahan sudah tersimpan.
                </p>
            </div>
            <div class="px-5 py-4 flex items-center justify-end gap-2">
                <button type="button" id="btnAdminLogoutCancel"
                        class="rounded-xl border border-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/5 transition">
                    Batal
                </button>
          <form method="post" action="<?= $base ?>admin-logout" class="inline">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <button type="submit" id="btnAdminLogoutConfirm"
        class="rounded-xl bg-rose-500 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-600 transition">
        Ya, Logout
    </button>
</form>

            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Search filter
    const input = document.getElementById('sidebarSearch');
    const items = Array.from(document.querySelectorAll('[data-menu-item]'));
    if (input) {
        input.addEventListener('input', function () {
            const q = (this.value || '').toLowerCase().trim();
            items.forEach(el => {
                const text = (el.innerText || '').toLowerCase();
                el.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        });
    }

    // Collapse sections + persist state
    const toggles = document.querySelectorAll('[data-collapse-toggle]');
    const getState = (id) => localStorage.getItem('sb:' + id);
    const setState = (id, v) => localStorage.setItem('sb:' + id, v);

    function setOpen(id, open) {
        const target = document.getElementById(id);
        const caret  = document.querySelector(`[data-caret="${id}"]`);
        if (!target) return;

        if (open) {
            target.classList.remove('hidden');
            if (caret) caret.classList.add('rotate-90');
            setState(id, 'open');
        } else {
            target.classList.add('hidden');
            if (caret) caret.classList.remove('rotate-90');
            setState(id, 'closed');
        }
    }

    // init
    document.querySelectorAll('[data-collapse]').forEach(el => {
        const id = el.getAttribute('id');
        const saved = getState(id);
        // default: open jika ada link aktif di dalamnya
        const hasActive = el.querySelector('.bg-slate-800\\/80');
        if (saved === 'open' || (!saved && hasActive)) setOpen(id, true);
        else setOpen(id, false);
    });

    toggles.forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-collapse-toggle');
            const target = document.getElementById(id);
            const isHidden = target.classList.contains('hidden');
            setOpen(id, isHidden);
        });
    });

     // Logout modal
    const modal = document.getElementById('adminLogoutModal');
    const backdrop = document.getElementById('adminLogoutBackdrop');
    const btnOpen = document.getElementById('btnAdminLogout');
    const btnCancel = document.getElementById('btnAdminLogoutCancel');

    function openModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    // esc close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
    });
})();
</script>
