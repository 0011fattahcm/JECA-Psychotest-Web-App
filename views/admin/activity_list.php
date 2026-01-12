<?php
define('ADMIN_PAGE', true);
include __DIR__ . '/components/sidebar.php';

// variables tersedia: $rows, $page, $pages, $total, dan filter GET
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$rows  = $rows  ?? [];
$page  = (int)($page  ?? 1);
$pages = (int)($pages ?? 1);
$total = (int)($total ?? 0);

// helper build url (mirip manage_user.php)
function buildUrl($baseParams, $override = []) {
  $params = array_merge($baseParams, $override);
  return 'index.php?' . http_build_query($params);
}

$baseParams = [
  'page'       => 'admin-activity-list',
  'q'          => $_GET['q'] ?? '',
  'event_type' => $_GET['event_type'] ?? '',
  'event_name' => $_GET['event_name'] ?? '',
  'test_code'  => $_GET['test_code'] ?? '',
  'from'       => $_GET['from'] ?? '',
  'to'         => $_GET['to'] ?? '',
];
?>
<script src="https://cdn.tailwindcss.com"></script>

<div class="ml-64 p-8 bg-gray-100 min-h-screen">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-semibold text-gray-900">Activity Log (User)</h1>
        <p class="text-sm text-gray-500 mt-1">
          Total: <?= (int)$total ?> • Halaman <?= (int)$page ?>/<?= (int)$pages ?>
        </p>
      </div>
    </div>

    <!-- Filter -->
   <form method="GET" action="index.php"
      class="bg-white rounded-2xl border border-slate-200 p-4 grid grid-cols-1 md:grid-cols-7 gap-3">
  <input type="hidden" name="page" value="admin-activity-list">

  <input class="md:col-span-2 w-full min-w-0 rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-200"
         name="q" placeholder="Cari user code/nama/event/test..." value="<?= h($_GET['q'] ?? '') ?>">

      <select class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-200" name="event_type">
        <option value="">Semua Type</option>
        <?php $v=$_GET['event_type']??''; ?>
        <option value="auth" <?= $v==='auth'?'selected':''; ?>>auth</option>
        <option value="test" <?= $v==='test'?'selected':''; ?>>test</option>
      </select>

      <select class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-200" name="event_name">
        <option value="">Semua Event</option>
        <?php $v=$_GET['event_name']??''; ?>
        <?php foreach (['login_success','logout','enter','complete_manual','complete_auto','blocked'] as $e): ?>
          <option value="<?= h($e) ?>" <?= $v===$e?'selected':''; ?>><?= h($e) ?></option>
        <?php endforeach; ?>
      </select>

      <select class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-200" name="test_code">
        <option value="">Semua Tes</option>
        <?php $v=$_GET['test_code']??''; ?>
        <?php foreach (['TPA','TAM','KRAEPLIN'] as $t): ?>
          <option value="<?= h($t) ?>" <?= $v===$t?'selected':''; ?>><?= h($t) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="flex gap-2">
        <input type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-200"
               name="from" value="<?= h($_GET['from'] ?? '') ?>">
        <input type="date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-200"
               name="to" value="<?= h($_GET['to'] ?? '') ?>">
      </div>

      <div class="md:col-span-6 flex items-center gap-2">
        <button class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
          Terapkan Filter
        </button>
        <a class="px-4 py-2 rounded-xl border border-slate-200 text-sm hover:bg-slate-50"
           href="index.php?page=admin-activity-list">
          Reset
        </a>
      </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="text-left px-4 py-3 w-[160px]">Waktu</th>
              <th class="text-left px-4 py-3">User</th>
              <th class="text-left px-4 py-3 w-[90px]">Type</th>
              <th class="text-left px-4 py-3 w-[160px]">Event</th>
              <th class="text-left px-4 py-3 w-[110px]">Tes</th>
              <th class="text-left px-4 py-3">IP</th>
              <th class="text-left px-4 py-3 w-[90px]">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php if (empty($rows)): ?>
              <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Belum ada log.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr class="hover:bg-slate-50/50">
                <td class="px-4 py-3 text-xs text-slate-600"><?= h($r['event_time']) ?></td>
                <td class="px-4 py-3">
                  <div class="font-semibold text-slate-900"><?= h($r['user_code'] ?? '-') ?></div>
                  <div class="text-xs text-slate-500"><?= h($r['name'] ?? '') ?></div>
                </td>
                <td class="px-4 py-3"><?= h($r['event_type']) ?></td>
                <td class="px-4 py-3 font-semibold"><?= h($r['event_name']) ?></td>
                <td class="px-4 py-3"><?= h($r['test_code'] ?? '-') ?></td>
                <td class="px-4 py-3 text-xs text-slate-600"><?= h($r['ip'] ?? '-') ?></td>
                <td class="px-4 py-3">
                  <button type="button"
                          class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs hover:bg-slate-50"
                          data-detail-id="<?= (int)$r['id'] ?>">
                    Detail
                  </button>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-4 py-3 border-t border-slate-200 text-sm">
        <div class="text-slate-500">Halaman <?= (int)$page ?> / <?= (int)$pages ?></div>
        <div class="flex gap-2">
          <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
            <?php $url = buildUrl($baseParams, ['p' => $i]); ?>
            <a class="px-3 py-1.5 rounded-xl border <?= $i===$page?'bg-slate-900 text-white border-slate-900':'border-slate-200 hover:bg-slate-50' ?>"
               href="<?= h($url) ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal Detail -->
<div id="detail-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl w-[92%] max-w-xl p-4 space-y-3">
    <div class="flex items-start justify-between">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Detail Activity</h2>
        <p class="text-xs text-slate-500" id="detail-sub">-</p>
      </div>
      <button id="detail-close" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs hover:bg-slate-50">Tutup</button>
    </div>

    <pre id="detail-json" class="text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 overflow-auto max-h-[360px]"></pre>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('detail-modal');
  const closeBtn = document.getElementById('detail-close');
  const pre = document.getElementById('detail-json');
  const sub = document.getElementById('detail-sub');

  function openModal(){ modal.classList.remove('hidden'); modal.classList.add('flex'); }
  function closeModal(){ modal.classList.add('hidden'); modal.classList.remove('flex'); }

  closeBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });

  document.querySelectorAll('[data-detail-id]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = btn.getAttribute('data-detail-id');
      pre.textContent = 'Loading...';
      sub.textContent = 'ID ' + id;

      openModal();
      try {
        const res = await fetch('index.php?page=admin-activity-detail&id=' + encodeURIComponent(id), { cache: 'no-store' });
        const js = await res.json();
        if (!js.ok) { pre.textContent = 'Gagal mengambil detail.'; return; }

        const d = js.data || {};
        const detail = d.detail_json ? safeParse(d.detail_json) : null;

        pre.textContent = JSON.stringify({
          id: d.id,
          time: d.event_time,
          user: { id: d.user_id, user_code: d.user_code, name: d.name },
          event: { type: d.event_type, name: d.event_name, test: d.test_code },
          ip: d.ip,
          user_agent: d.user_agent,
          session_id: d.session_id,
          detail: detail
        }, null, 2);
      } catch(e) {
        pre.textContent = 'Error: ' + (e?.message || 'unknown');
      }
    });
  });

  function safeParse(s){
    try { return JSON.parse(s); } catch(e){ return s; }
  }
})();
</script>
