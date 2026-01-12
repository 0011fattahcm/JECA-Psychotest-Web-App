<?php
// views/admin/manage_user.php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';

$state = $state ?? [
  'q' => '',
  'from' => '',
  'to' => '',
  'status' => 'all',
  'p' => 1,
  'per_page' => 20,
  'total' => 0,
  'total_pages' => 1,
];

$q       = (string)($state['q'] ?? '');
$from    = (string)($state['from'] ?? '');
$to      = (string)($state['to'] ?? '');

// NOTE: controller kamu belum set status ke $state, jadi kita ambil dari GET biar UI tetap konsisten
$status  = (string)($state['status'] ?? ($_GET['status'] ?? 'all'));

$p       = (int)($state['p'] ?? 1);
$perPage = (int)($state['per_page'] ?? 20);
$total   = (int)($state['total'] ?? 0);
$totalPages = (int)($state['total_pages'] ?? 1);

$users = $users ?? [];
$startNo = ($p - 1) * $perPage;

function buildUrl($baseParams, $override = []) {
  $params = array_merge($baseParams, $override);
  return 'index.php?' . http_build_query($params);
}

$baseParams = [
  'page' => 'admin-users',
  'q' => $q,
  'from' => $from,
  'to' => $to,
  'status' => $status,
  'per_page' => $perPage,
];

$showFrom = $total > 0 ? ($startNo + 1) : 0;
$showTo = min($startNo + count($users), $total);

$returnUrl = buildUrl($baseParams, ['p' => $p]);

function statusBadge(int $isActive): array {
  if ($isActive === 1) return ['Aktif', 'bg-emerald-50 text-emerald-700 border-emerald-200'];
  return ['Nonaktif', 'bg-rose-50 text-rose-700 border-rose-200'];
}

$msg = $_GET['msg'] ?? '';
?>
<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
  <div class="max-w-6xl mx-auto space-y-6">

    <?php if (!empty($msg)): ?>
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
        <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-semibold text-gray-900">Kelola User</h1>
        <p class="text-sm text-gray-500 mt-1">
          Daftar seluruh peserta tes psikotes yang terdaftar di sistem.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <!-- Export -->
        <form method="GET" action="index.php" class="flex flex-wrap items-end gap-2">
          <input type="hidden" name="page" value="admin-users-export-excel">
          <input type="hidden" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">

          <div>
            <label class="block text-[11px] text-slate-500 mb-1">Dari</label>
            <input type="date" name="from" value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>"
                   class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-indigo-200">
          </div>

          <div>
            <label class="block text-[11px] text-slate-500 mb-1">Sampai</label>
            <input type="date" name="to" value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>"
                   class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-indigo-200">
          </div>

          <button type="submit"
                  class="h-10 inline-flex items-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
            Download Excel
          </button>
        </form>

        <a href="index.php?page=admin-add-user"
           class="h-10 inline-flex items-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white">
          <span class="mr-1 text-lg leading-none">+</span>
          Tambah User
        </a>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
      <form method="GET" action="index.php" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="page" value="admin-users">

        <div class="flex-1 min-w-[220px]">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Pencarian</label>
          <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="Cari nama atau user code..."
                 class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
          <select name="status"
                  class="rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="all"      <?= $status === 'all' ? 'selected' : '' ?>>Semua</option>
            <option value="active"   <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Dari</label>
          <input type="date" name="from" value="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>"
                 class="rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Sampai</label>
          <input type="date" name="to" value="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>"
                 class="rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Per halaman</label>
          <select name="per_page"
                  class="rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <?php foreach ([10,20,50,100] as $opt): ?>
              <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex items-center gap-2">
          <button type="submit"
                  class="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Terapkan
          </button>

          <a href="index.php?page=admin-users"
             class="inline-flex items-center rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Reset
          </a>
        </div>
      </form>

      <div class="mt-3 text-xs text-gray-500">
        Menampilkan <span class="font-semibold text-gray-800"><?= $showFrom ?></span>–<span class="font-semibold text-gray-800"><?= $showTo ?></span>
        dari <span class="font-semibold text-gray-800"><?= $total ?></span> user.
      </div>
    </div>

    <!-- Tabel -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto border-t border-gray-100">
        <table class="min-w-[1200px] w-full divide-y divide-gray-100 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">#</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User Code</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal Lahir</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Retake</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dibuat</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
            </tr>
          </thead>

          <tbody class="bg-white divide-y divide-gray-50">
            <?php if (!empty($users)): ?>
              <?php foreach ($users as $index => $user): ?>
                <?php
                  $isActive = (int)($user['is_active'] ?? 1);
                  [$label, $badgeClass] = statusBadge($isActive);
                  $toggleTo = $isActive === 1 ? 0 : 1;
                  $toggleText = $isActive === 1 ? 'Nonaktifkan' : 'Aktifkan';
                  $toggleBtnClass = $isActive === 1
                    ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100'
                    : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100';

                  // user_code anti-wrap (non-breaking hyphen)
                  $userCode = (string)($user['user_code'] ?? '-');
                  $userCodeNoBreak = str_replace('-', '-', $userCode); // U+2011
                ?>

                <tr class="hover:bg-indigo-50/50 align-top">
                  <td class="px-6 py-3 text-gray-500 align-top"><?= $startNo + $index + 1 ?></td>

                  <td class="px-6 py-3 font-mono text-xs text-gray-900 whitespace-nowrap align-top">
                    <?= htmlspecialchars($userCodeNoBreak, ENT_QUOTES, 'UTF-8') ?>
                  </td>

                  <td class="px-6 py-3 text-gray-900 align-top">
                    <?= htmlspecialchars($user['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                  </td>

                  <td class="px-6 py-3 align-top">
                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $badgeClass ?>">
                      <?= $label ?>
                    </span>
                  </td>

                  <td class="px-6 py-3 text-gray-700 align-top whitespace-nowrap">
                    <?= !empty($user['birthdate']) ? date('d M Y', strtotime($user['birthdate'])) : '-' ?>
                  </td>

                  <!-- RETAKE (rapi, tidak numpuk) -->
                  <td class="px-6 py-3 align-top min-w-[340px]">
                    <div class="space-y-2">
                      <div class="text-xs text-slate-600">
                        <span class="font-semibold text-slate-900">TPA:</span> <?= (int)($user['tpa_retake_quota'] ?? 0) ?>
                        <span class="mx-1 text-slate-300">|</span>
                        <span class="font-semibold text-slate-900">TAM:</span> <?= (int)($user['tam_retake_quota'] ?? 0) ?>
                        <span class="mx-1 text-slate-300">|</span>
                        <span class="font-semibold text-slate-900">KRAEPLIN:</span> <?= (int)($user['kraeplin_retake_quota'] ?? 0) ?>
                      </div>

                      <form method="POST" action="index.php?page=admin-user-grant-retake" class="space-y-2">
                        <input type="hidden" name="user_id" value="<?= (int)($user['id'] ?? 0) ?>">

                        <div class="grid grid-cols-3 gap-2">
                          <label class="inline-flex items-center gap-2 text-xs text-slate-700 whitespace-nowrap">
                            <input type="checkbox" name="tpa" value="1" class="rounded border-slate-300">
                            TPA
                          </label>

                          <label class="inline-flex items-center gap-2 text-xs text-slate-700 whitespace-nowrap">
                            <input type="checkbox" name="tam" value="1" class="rounded border-slate-300">
                            TAM
                          </label>

                          <label class="inline-flex items-center gap-2 text-xs text-slate-700 whitespace-nowrap">
                            <input type="checkbox" name="kraeplin" value="1" class="rounded border-slate-300">
                            KRAEPLIN
                          </label>
                        </div>

                        <button type="submit"
                          class="w-full inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                          Beri Akses Ulang
                        </button>
                      </form>
                    </div>
                  </td>

                  <td class="px-6 py-3 text-gray-500 text-xs align-top whitespace-nowrap">
                    <?= !empty($user['created_at']) ? date('d M Y H:i', strtotime($user['created_at'])) : '-' ?>
                  </td>

                  <td class="px-6 py-3 text-right space-x-3 align-top whitespace-nowrap">
                    <a href="index.php?page=admin-user-edit&id=<?= (int)$user['id'] ?>"
                       class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Edit</a>

                    <button type="button"
                      class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold border transition btn-open-status <?= $toggleBtnClass ?>"
                      data-user-id="<?= (int)$user['id'] ?>"
                      data-user-name="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                      data-toggle-to="<?= (int)$toggleTo ?>"
                      data-toggle-text="<?= htmlspecialchars($toggleText, ENT_QUOTES, 'UTF-8') ?>">
                      <?= $toggleText ?>
                    </button>

                    <button type="button"
                      class="inline-flex items-center rounded-full bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600
                             hover:bg-red-100 border border-red-200 transition btn-open-delete"
                      data-user-id="<?= (int)$user['id'] ?>"
                      data-user-name="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                      Hapus
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="px-6 py-10 text-center text-gray-400 text-sm">
                  Tidak ada data user sesuai filter.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
          <div class="text-xs text-gray-500">
            Halaman <span class="font-semibold text-gray-800"><?= $p ?></span> dari <span class="font-semibold text-gray-800"><?= $totalPages ?></span>
          </div>

          <div class="flex items-center gap-1">
            <?php
              $prevUrl = buildUrl($baseParams, ['p' => max(1, $p - 1)]);
              $nextUrl = buildUrl($baseParams, ['p' => min($totalPages, $p + 1)]);
            ?>
            <a href="<?= htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8') ?>"
               class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-300 bg-white hover:bg-gray-50 <?= $p <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
              Prev
            </a>

            <?php
              $start = max(1, $p - 2);
              $end   = min($totalPages, $p + 2);

              if ($start > 1) {
                echo '<a class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-300 bg-white hover:bg-gray-50" href="' . htmlspecialchars(buildUrl($baseParams, ['p'=>1]), ENT_QUOTES, 'UTF-8') . '">1</a>';
                if ($start > 2) echo '<span class="px-2 text-xs text-gray-400">…</span>';
              }

              for ($i = $start; $i <= $end; $i++) {
                $url = buildUrl($baseParams, ['p' => $i]);
                $active = $i === $p ? 'bg-slate-900 text-white border-slate-900' : 'bg-white hover:bg-gray-50 border-gray-300';
                echo '<a class="px-3 py-1.5 rounded-lg text-xs font-medium border ' . $active . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
              }

              if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span class="px-2 text-xs text-gray-400">…</span>';
                echo '<a class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-300 bg-white hover:bg-gray-50" href="' . htmlspecialchars(buildUrl($baseParams, ['p'=>$totalPages]), ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a>';
              }
            ?>

            <a href="<?= htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') ?>"
               class="px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-300 bg-white hover:bg-gray-50 <?= $p >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>">
              Next
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Toggle Status -->
<div id="statusModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm">
  <div class="mx-4 w-full max-w-md rounded-3xl bg-white shadow-xl shadow-slate-900/20 border border-slate-200">
    <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
      <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2" />
        </svg>
      </div>
      <div>
        <h2 class="text-sm font-semibold text-slate-900" id="statusTitle">Ubah Status?</h2>
        <p class="text-xs text-slate-500 mt-0.5" id="statusDesc">User akan berubah status.</p>
      </div>
    </div>

    <div class="px-6 py-4">
      <p class="text-sm text-slate-700">Target user:</p>
      <p class="mt-1 text-sm font-semibold text-slate-900" id="statusUserName"></p>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4">
      <button type="button"
              class="btn-close-status inline-flex items-center rounded-full border border-slate-300 bg-white
                     px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition">
        Batal
      </button>

      <form id="statusForm" method="POST" action="index.php">
        <input type="hidden" name="page" value="admin-user-toggle-active">
        <input type="hidden" name="id" id="statusUserId" value="">
        <input type="hidden" name="state" id="statusTo" value="">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">

        <button type="submit"
                id="statusSubmitBtn"
                class="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold
                       text-white shadow-md hover:bg-slate-800 focus:outline-none transition">
          Konfirmasi
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm">
  <div class="mx-4 w-full max-w-md rounded-3xl bg-white shadow-xl shadow-slate-900/20 border border-slate-200">
    <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
      <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-3h4m-6 0h2m4 0h2M9 7v10m6-10v10" />
        </svg>
      </div>
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Hapus User?</h2>
        <p class="text-xs text-slate-500 mt-0.5">Tindakan ini tidak dapat dibatalkan. Data user akan dihapus permanen.</p>
      </div>
    </div>

    <div class="px-6 py-4">
      <p class="text-sm text-slate-700">Anda akan menghapus user:</p>
      <p class="mt-1 text-sm font-semibold text-slate-900" id="deleteUserName"></p>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4">
      <button type="button"
              class="btn-close-delete inline-flex items-center rounded-full border border-slate-300 bg-white
                     px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition">
        Batal
      </button>

      <form id="deleteForm" method="GET" action="index.php">
        <input type="hidden" name="page" value="admin-user-delete">
        <input type="hidden" name="id" id="deleteUserId" value="">
        <button type="submit"
                class="inline-flex items-center rounded-full bg-red-600 px-4 py-2 text-xs font-semibold
                       text-white shadow-md shadow-red-500/30 hover:bg-red-700 focus:outline-none transition">
          Ya, Hapus
        </button>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // ===== Delete modal =====
  const deleteModal = document.getElementById('deleteModal');
  const deleteNameTarget = document.getElementById('deleteUserName');
  const deleteIdInput = document.getElementById('deleteUserId');

  const openDeleteButtons = document.querySelectorAll('.btn-open-delete');
  const closeDeleteButtons = document.querySelectorAll('.btn-close-delete');

  function openDeleteModal() {
    deleteModal.classList.remove('hidden');
    deleteModal.classList.add('flex');
  }
  function closeDeleteModal() {
    deleteModal.classList.add('hidden');
    deleteModal.classList.remove('flex');
  }

  openDeleteButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      deleteNameTarget.textContent = btn.getAttribute('data-user-name') || 'User ini';
      deleteIdInput.value = btn.getAttribute('data-user-id');
      openDeleteModal();
    });
  });

  closeDeleteButtons.forEach(btn => btn.addEventListener('click', closeDeleteModal));
  deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeDeleteModal(); });

  // ===== Status modal =====
  const statusModal = document.getElementById('statusModal');
  const statusUserName = document.getElementById('statusUserName');
  const statusUserId = document.getElementById('statusUserId');
  const statusTo = document.getElementById('statusTo');
  const statusTitle = document.getElementById('statusTitle');
  const statusDesc = document.getElementById('statusDesc');
  const statusSubmitBtn = document.getElementById('statusSubmitBtn');

  const openStatusButtons = document.querySelectorAll('.btn-open-status');
  const closeStatusButtons = document.querySelectorAll('.btn-close-status');

  function openStatusModal() {
    statusModal.classList.remove('hidden');
    statusModal.classList.add('flex');
  }
  function closeStatusModal() {
    statusModal.classList.add('hidden');
    statusModal.classList.remove('flex');
  }

  openStatusButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const userId = btn.getAttribute('data-user-id');
      const userName = btn.getAttribute('data-user-name') || 'User ini';
      const toggleToVal = btn.getAttribute('data-toggle-to'); // "0" / "1"
      const toggleText = btn.getAttribute('data-toggle-text') || 'Ubah Status';

      statusUserName.textContent = userName;
      statusUserId.value = userId;
      statusTo.value = toggleToVal;

      statusTitle.textContent = toggleText + ' user?';
      statusDesc.textContent = (toggleToVal === "1")
        ? 'User akan diaktifkan dan dapat login serta mengerjakan tes.'
        : 'User akan dinonaktifkan dan tidak dapat login serta mengerjakan tes.';

      statusSubmitBtn.textContent = 'Ya, ' + toggleText;
      openStatusModal();
    });
  });

  closeStatusButtons.forEach(btn => btn.addEventListener('click', closeStatusModal));
  statusModal.addEventListener('click', (e) => { if (e.target === statusModal) closeStatusModal(); });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeDeleteModal();
      closeStatusModal();
    }
  });
});
</script>
