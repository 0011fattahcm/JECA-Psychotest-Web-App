<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';

// Safeguard
$total_user          = $total_user          ?? 0;
$total_tpa_questions = $total_tpa_questions ?? 0;
$total_tam_questions = $total_tam_questions ?? 0;
$total_tpa_results   = $total_tpa_results   ?? 0;
$total_tam_results   = $total_tam_results   ?? 0;
$total_kraep_results = $total_kraep_results ?? 0;

$users_done_tpa      = $users_done_tpa      ?? 0;
$users_done_tam      = $users_done_tam      ?? 0;
$users_done_kraeplin = $users_done_kraeplin ?? 0;

$days        = $days        ?? [];
$chartTPA    = $chartTPA    ?? [];
$chartTAM    = $chartTAM    ?? [];
$chartKR     = $chartKR     ?? [];

$monthLabels = $monthLabels ?? [];
$monthUsers  = $monthUsers  ?? [];
$monthTests  = $monthTests  ?? [];

$recentActivities = $recentActivities ?? [];
$topKraeplin      = $topKraeplin      ?? [];

$testsTotal = (int)$total_tpa_results + (int)$total_tam_results + (int)$total_kraep_results;

$percent = function($n, $d) {
    if ($d <= 0) return 0;
    return round(($n / $d) * 100, 1);
};

?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="ml-64 p-8 bg-slate-50 min-h-screen">
  <div class="max-w-7xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[11px] font-semibold tracking-[0.25em] text-indigo-500 uppercase">JECA Psychotest – Admin</p>
        <h1 class="mt-2 text-3xl font-extrabold text-slate-900">Dashboard Admin</h1>
        <p class="mt-1 text-sm text-slate-600">
          Ringkasan data sistem, tren aktivitas, progres peserta, dan aktivitas terbaru.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <a href="index.php?page=admin-results"
           class="inline-flex items-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-white">
          Lihat Results
        </a>
        <a href="index.php?page=admin-kraeplin-results"
           class="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
          Kraeplin Results
        </a>
      </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <p class="text-xs font-semibold text-slate-500 uppercase">Kontrol Tes</p>
      <h2 class="mt-1 text-lg font-extrabold text-slate-900">Buka / Tutup Tes (Login Gate)</h2>
      <p class="mt-1 text-sm text-slate-600">
        Jika tes belum dibuka sesuai tanggal & jam, user tidak bisa login dan akan melihat notif “Admin belum memulai Tes”.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
        <span class="px-2.5 py-1 rounded-full border <?= $isOpenNow ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-rose-50 border-rose-200 text-rose-700' ?>">
          Status saat ini: <?= $isOpenNow ? 'OPEN' : 'CLOSED' ?>
        </span>
        <span class="px-2.5 py-1 rounded-full border border-slate-200 text-slate-600 bg-slate-50">
          Server time: <?= date('Y-m-d H:i') ?>
        </span>
      </div>
    </div>
  </div>
  <?php 
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$twMode = ((int)($testWindow['is_open'] ?? 0) === 1) ? 'open' : 'closed';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$flashErr = $_SESSION['flash_err'] ?? '';
$flashOk  = $_SESSION['flash_ok'] ?? '';

unset($_SESSION['flash_err'], $_SESSION['flash_ok']);
  ?>
  <?php if ($flashErr): ?>
  <div id="flashMsg" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
    <?= h($flashErr) ?>
  </div>
<?php elseif ($flashOk): ?>
  <div id="flashMsg" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
    <?= h($flashOk) ?>
  </div>
<?php endif; ?>

<script>
(function(){
  const el = document.getElementById('flashMsg');
  if(!el) return;

  setTimeout(() => {
    el.style.transition = 'opacity 250ms ease';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
  }, 3000);
})();
</script>


  <form class="mt-4 grid grid-cols-1 lg:grid-cols-12 gap-3"
        method="post" action="index.php?page=admin-test-window-save">

    <!-- Buttons mode -->
    <div class="lg:col-span-4">
      <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-50 p-1">
        <button type="button" id="btnClosed"
          class="px-4 py-2 rounded-xl text-sm font-semibold <?= $twMode==='closed'?'bg-slate-900 text-white':'text-slate-700 hover:bg-white' ?>">
          Tutup Tes
        </button>
        <button type="button" id="btnOpen"
          class="px-4 py-2 rounded-xl text-sm font-semibold <?= $twMode==='open'?'bg-indigo-600 text-white':'text-slate-700 hover:bg-white' ?>">
          Buka Tes
        </button>
      </div>
      <input type="hidden" name="mode" id="modeInput" value="<?= h($twMode) ?>">
      <p class="mt-2 text-xs text-slate-500">Pilih “Buka Tes” untuk mengaktifkan input tanggal & jam.</p>
    </div>

    <!-- Date -->
    <div class="lg:col-span-3">
      <label class="block text-xs font-semibold text-slate-600 mb-1">Tanggal</label>
      <input type="date" name="open_date" id="openDate"
        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white"
        value="<?= h($testWindow['open_date'] ?? '') ?>">
    </div>

    <!-- Start -->
    <div class="lg:col-span-2">
      <label class="block text-xs font-semibold text-slate-600 mb-1">Jam Mulai</label>
      <input type="time" name="start_time" id="startTime"
        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white"
        value="<?= h(substr((string)($testWindow['start_time'] ?? ''),0,5)) ?>">
    </div>

    <!-- End -->
    <div class="lg:col-span-2">
      <label class="block text-xs font-semibold text-slate-600 mb-1">Jam Selesai</label>
      <input type="time" name="end_time" id="endTime"
        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white"
        value="<?= h(substr((string)($testWindow['end_time'] ?? ''),0,5)) ?>">
    </div>

    <!-- Save -->
    <div class="lg:col-span-1 flex items-end">
      <button
        class="w-full inline-flex justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
        Simpan
      </button>
    </div>
  </form>
</div>
    <!-- KPI Cards -->
    <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs text-slate-500 font-semibold">Total User</p>
        <p class="mt-2 text-3xl font-extrabold text-slate-900"><?= (int)$total_user ?></p>
        <p class="mt-1 text-xs text-slate-500">Jumlah akun peserta terdaftar.</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs text-slate-500 font-semibold">Total Soal TPA</p>
        <p class="mt-2 text-3xl font-extrabold text-slate-900"><?= (int)$total_tpa_questions ?></p>
        <p class="mt-1 text-xs text-slate-500">Bank soal pada modul TPA.</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs text-slate-500 font-semibold">Total Soal TAM</p>
        <p class="mt-2 text-3xl font-extrabold text-slate-900"><?= (int)$total_tam_questions ?></p>
        <p class="mt-1 text-xs text-slate-500">Bank soal pada modul TAM.</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs text-slate-500 font-semibold">Riwayat Tes TPA</p>
        <p class="mt-2 text-3xl font-extrabold text-slate-900"><?= (int)$total_tpa_results ?></p>
        <p class="mt-1 text-xs text-slate-500">Total attempt TPA.</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs text-slate-500 font-semibold">Riwayat Tes TAM</p>
        <p class="mt-2 text-3xl font-extrabold text-slate-900"><?= (int)$total_tam_results ?></p>
        <p class="mt-1 text-xs text-slate-500">Total attempt TAM.</p>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs text-slate-500 font-semibold">Riwayat Tes Kraeplin</p>
        <p class="mt-2 text-3xl font-extrabold text-slate-900"><?= (int)$total_kraep_results ?></p>
        <p class="mt-1 text-xs text-slate-500">Total attempt Kraeplin.</p>
      </div>
    </section>

    <!-- Charts + Side Panels -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Chart utama -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold text-slate-500 uppercase">Aktivitas 7 hari terakhir</p>
            <p class="mt-1 text-sm text-slate-600">Jumlah attempt per hari (TPA, TAM, Kraeplin).</p>
          </div>
        </div>
        <div class="mt-4 h-72">
          <canvas id="chartDaily"></canvas>
        </div>
      </div>

      <!-- Funnel + Distribusi -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 space-y-5">
        <div>
          <p class="text-xs font-semibold text-slate-500 uppercase">Progres Peserta (Unique User)</p>

          <div class="mt-3 space-y-3">
            <?php
            $p1 = $percent($users_done_tpa, $total_user);
            $p2 = $percent($users_done_tam, $total_user);
            $p3 = $percent($users_done_kraeplin, $total_user);
            ?>

            <div>
              <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-slate-700">Selesai TPA</span>
                <span class="text-slate-500"><?= $users_done_tpa ?> user (<?= $p1 ?>%)</span>
              </div>
              <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-indigo-500" style="width: <?= $p1 ?>%"></div>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-slate-700">Selesai TAM</span>
                <span class="text-slate-500"><?= $users_done_tam ?> user (<?= $p2 ?>%)</span>
              </div>
              <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-emerald-500" style="width: <?= $p2 ?>%"></div>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-slate-700">Selesai Kraeplin</span>
                <span class="text-slate-500"><?= $users_done_kraeplin ?> user (<?= $p3 ?>%)</span>
              </div>
              <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-rose-500" style="width: <?= $p3 ?>%"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="border-t border-slate-100 pt-4">
          <p class="text-xs font-semibold text-slate-500 uppercase">Distribusi Attempt</p>
          <p class="mt-1 text-sm text-slate-600">Proporsi attempt dari semua modul.</p>
          <div class="mt-3 h-48">
            <canvas id="chartMix"></canvas>
          </div>

          <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2">
              <p class="text-[10px] text-slate-500 font-semibold">TPA</p>
              <p class="text-sm font-extrabold text-slate-900"><?= $percent($total_tpa_results, max($testsTotal,1)) ?>%</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2">
              <p class="text-[10px] text-slate-500 font-semibold">TAM</p>
              <p class="text-sm font-extrabold text-slate-900"><?= $percent($total_tam_results, max($testsTotal,1)) ?>%</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2">
              <p class="text-[10px] text-slate-500 font-semibold">Kraeplin</p>
              <p class="text-sm font-extrabold text-slate-900"><?= $percent($total_kraep_results, max($testsTotal,1)) ?>%</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Chart bulanan + leaderboard -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-semibold text-slate-500 uppercase">Tren 6 bulan terakhir</p>
        <p class="mt-1 text-sm text-slate-600">User baru vs total attempt semua tes.</p>
        <div class="mt-4 h-72">
          <canvas id="chartMonthly"></canvas>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-semibold text-slate-500 uppercase">Top Kraeplin</p>
        <p class="mt-1 text-sm text-slate-600">Berdasarkan produktivitas tertinggi.</p>

        <div class="mt-4 space-y-3">
          <?php if (!empty($topKraeplin)): ?>
            <?php foreach ($topKraeplin as $i => $r): ?>
              <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                <div>
                  <p class="text-xs font-bold text-slate-900">
                    #<?= $i+1 ?> · <?= htmlspecialchars($r['user_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                  <p class="text-[11px] text-slate-500 font-mono">
                    <?= htmlspecialchars($r['user_code'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-sm font-extrabold text-slate-900"><?= (int)$r['total_productivity'] ?></p>
                  <p class="text-[11px] text-slate-500">item</p>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-sm text-slate-500">Belum ada data Kraeplin.</div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Aktivitas Terbaru -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-semibold text-slate-500 uppercase">Aktivitas Terbaru</p>
          <p class="mt-1 text-sm text-slate-600">10 aktivitas terbaru dari semua tes.</p>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-xs text-slate-500 uppercase tracking-wide bg-slate-50/70 border-b border-slate-100">
              <th class="px-4 py-2 text-left">Waktu</th>
              <th class="px-4 py-2 text-left">Peserta</th>
              <th class="px-4 py-2 text-center">Tes</th>
              <th class="px-4 py-2 text-center">Skor/Info</th>
              <th class="px-4 py-2 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
          <?php if (!empty($recentActivities)): ?>
            <?php foreach ($recentActivities as $r): ?>
              <?php
                $type = $r['test_type'] ?? '-';
                $id   = (int)($r['test_id'] ?? 0);

                $detailUrl = '#';
                if ($type === 'TPA') $detailUrl = "index.php?page=admin-tpa-result-detail&id={$id}";
                if ($type === 'Kraeplin') $detailUrl = "index.php?page=admin-kraeplin-result-detail&id={$id}";
              ?>
              <tr class="hover:bg-slate-50/60">
                <td class="px-4 py-3 text-xs text-slate-500">
                  <?= htmlspecialchars($r['created_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td class="px-4 py-3">
                  <div class="text-sm font-semibold text-slate-900">
                    <?= htmlspecialchars($r['user_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <div class="text-xs text-slate-500">ID: <?= (int)($r['user_id'] ?? 0) ?></div>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                    <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">
                    <?= htmlspecialchars($r['score_text'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <?php if ($detailUrl !== '#'): ?>
                    <a href="<?= $detailUrl ?>"
                       class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-900 hover:text-white hover:border-slate-900 transition">
                      Detail
                    </a>
                  <?php else: ?>
                    <span class="text-xs text-slate-400">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="px-4 py-8 text-center text-slate-500 text-sm">
                Belum ada aktivitas.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</div>

<script>

  (function(){
  const btnOpen   = document.getElementById('btnOpen');
  const btnClosed = document.getElementById('btnClosed');
  const modeInput = document.getElementById('modeInput');

  const openDate  = document.getElementById('openDate');
  const startTime = document.getElementById('startTime');
  const endTime   = document.getElementById('endTime');

  function setMode(mode){
    modeInput.value = mode;

    const isOpen = (mode === 'open');
    openDate.disabled  = !isOpen;
    startTime.disabled = !isOpen;
    endTime.disabled   = !isOpen;

    // styling tombol (biar konsisten)
    btnOpen.classList.toggle('bg-indigo-600', isOpen);
    btnOpen.classList.toggle('text-white', isOpen);

    btnClosed.classList.toggle('bg-slate-900', !isOpen);
    btnClosed.classList.toggle('text-white', !isOpen);
  }

  btnOpen.addEventListener('click', () => setMode('open'));
  btnClosed.addEventListener('click', () => setMode('closed'));

  // init
  setMode(modeInput.value || 'closed');
})();
(function () {
  // Daily chart
  const dayLabels = <?= json_encode($days) ?>;
  const dataTPA   = <?= json_encode($chartTPA) ?>;
  const dataTAM   = <?= json_encode($chartTAM) ?>;
  const dataKR    = <?= json_encode($chartKR) ?>;

  const ctxDaily = document.getElementById('chartDaily');
  if (ctxDaily) {
    new Chart(ctxDaily, {
      type: 'line',
      data: {
        labels: dayLabels,
        datasets: [
          { label: 'TPA', data: dataTPA, tension: 0.25, borderWidth: 2 },
          { label: 'TAM', data: dataTAM, tension: 0.25, borderWidth: 2 },
          { label: 'Kraeplin', data: dataKR, tension: 0.25, borderWidth: 2 },
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Mix chart (attempt distribution)
  const ctxMix = document.getElementById('chartMix');
  if (ctxMix) {
    new Chart(ctxMix, {
      type: 'doughnut',
      data: {
        labels: ['TPA', 'TAM', 'Kraeplin'],
        datasets: [{
          data: [<?= (int)$total_tpa_results ?>, <?= (int)$total_tam_results ?>, <?= (int)$total_kraep_results ?>],
          borderWidth: 1
        }]
      },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
  }

  // Monthly chart
  const monthLabels = <?= json_encode($monthLabels) ?>;
  const monthUsers  = <?= json_encode($monthUsers) ?>;
  const monthTests  = <?= json_encode($monthTests) ?>;

  const ctxMonthly = document.getElementById('chartMonthly');
  if (ctxMonthly) {
    new Chart(ctxMonthly, {
      type: 'bar',
      data: {
        labels: monthLabels,
        datasets: [
          { label: 'User Baru', data: monthUsers, borderWidth: 1 },
          { label: 'Total Attempt Tes', data: monthTests, borderWidth: 1 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }
})();
</script>
