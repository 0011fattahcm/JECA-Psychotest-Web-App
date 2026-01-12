/* assets/JS/antiCheatLite.js */
/* AntiCheatLite v1.7 (Policy: 1-2 warning, 3 autosubmit/invalidated)
  Fix utama:
  - Default policy: warningLimit=2, maxViolations=3.
  - warningLimit tidak boleh >= maxViolations (auto-adjust).
  - Strike tab/minimize dihitung saat user KEMBALI (visibility visible) => popup pasti bisa tampil.
  - 1 hidden cycle = 1 strike (anti double-count).
  - BLUR tidak menambah strike (hanya log) untuk mencegah 1 cycle jadi 2 strike.
  - Strike diproses berurutan (queue) agar state violations stabil.
*/
(function (global) {
  "use strict";

  function safeJsonParse(s) { try { return JSON.parse(s); } catch (_) { return null; } }
  function clampInt(n, min, max, def) {
    n = parseInt(n, 10);
    if (!Number.isFinite(n)) return def;
    if (n < min) return min;
    if (n > max) return max;
    return n;
  }
  function nowMs() { return Date.now(); }

  const REG = global.__AntiCheatLiteRegistry || (global.__AntiCheatLiteRegistry = {});

  function cssEscapeSafe(id) {
    try {
      if (global.CSS && typeof global.CSS.escape === "function") return global.CSS.escape(id);
    } catch (_) {}
    return String(id).replace(/[^a-zA-Z0-9_\-]/g, "\\$&");
  }

  function normalizeMaybeDuplicateEl(el) {
    try {
      if (!el || !el.id) return el;
      const id = String(el.id || "");
      if (!id) return el;

      const all = Array.from(document.querySelectorAll("#" + cssEscapeSafe(id)));
      if (all.length <= 1) return el;

      for (let i = all.length - 1; i >= 0; i--) {
        const it = all[i];
        const st = global.getComputedStyle ? global.getComputedStyle(it) : null;
        const visible = !!it.offsetParent && (!st || (st.display !== "none" && st.visibility !== "hidden" && st.opacity !== "0"));
        if (visible) return it;
      }
      return all[all.length - 1] || el;
    } catch (_) {
      return el;
    }
  }

  const AntiCheatLite = {
    init: function init(cfg) {
      cfg = cfg || {};
      const attemptId = String(cfg.attemptId || "").trim();
      if (!attemptId) throw new Error("AntiCheatLite: attemptId is required");

      // jika sudah ada, re-configure agar snippet dobel tetap pakai callback/UI terbaru
      if (REG[attemptId] && typeof REG[attemptId].configure === "function") {
        REG[attemptId].configure(cfg);
        return REG[attemptId];
      }

      const state = {
        started: false,
        enabled: true,
        invalidated: false,

        attemptId,
        testCode: "UNKNOWN",
        postUrl: "",

        // POLICY DEFAULT (sesuai request): 1-2 warning, 3 fatal
        warningLimit: 2,
        maxViolations: 3,

        // debounce tambahan (untuk safety). Hidden-cycle sendiri sudah anti dobel.
        strikeCooldownMs: 600,
        // grace untuk menghindari glitch visibility yang super cepat
        hiddenGraceMs: 80,

        cameraRequired: false,
        // tetap ada, tapi tidak menambah strike (hanya log) agar tidak double count
        countWindowBlur: false,

        strikeOnCopy: false,
        strikeOnPaste: false,
        strikeOnCut: false,
        strikeOnContextMenu: false,

        violations: 0,
        lastStrikeAt: 0,

        hiddenAt: 0,
        hiddenCycleId: 0,
        hiddenCycleActive: false,

        tabId: null,

        bc: null,
        hbTimer: null,
        stream: null,
        styleEl: null,

        cameraVideoEl: null,
        cameraIndicatorEl: null,

        onBlocked: null,
        onInvalidate: null,
        onViolation: null,

        lockKey: "__ac_lock__:" + attemptId,
        tabKey: "__ac_tab_id__:" + attemptId,
        strikeKey: "__ac_last_strike__:" + attemptId // cross-instance debounce
      };

      // queue untuk menghindari race-condition strike async
      let strikeChain = Promise.resolve();

      function configure(cfg2) {
        cfg2 = cfg2 || {};

        if (cfg2.testCode || cfg2.test || cfg2.test_code) {
          state.testCode = String(cfg2.testCode || cfg2.test || cfg2.test_code || state.testCode).toUpperCase();
        }

        if (cfg2.postUrl) state.postUrl = String(cfg2.postUrl).trim();

        // maxViolations diset dulu agar warningLimit bisa di-adjust
        state.maxViolations    = clampInt((cfg2.maxViolations ?? cfg2.max), 1, 20, state.maxViolations);
        state.warningLimit     = clampInt(cfg2.warningLimit, 0, 20, state.warningLimit);

        // WARNING LIMIT TIDAK BOLEH >= MAX
        if (state.warningLimit >= state.maxViolations) {
          state.warningLimit = Math.max(0, state.maxViolations - 1);
        }

        state.strikeCooldownMs = clampInt(cfg2.strikeCooldownMs, 0, 60000, state.strikeCooldownMs);
        state.hiddenGraceMs    = clampInt(cfg2.hiddenGraceMs, 0, 5000, state.hiddenGraceMs);

        state.cameraRequired   = !!cfg2.cameraRequired;
        state.countWindowBlur  = !!cfg2.countWindowBlur;

        state.strikeOnCopy        = !!cfg2.strikeOnCopy;
        state.strikeOnPaste       = !!cfg2.strikeOnPaste;
        state.strikeOnCut         = !!cfg2.strikeOnCut;
        state.strikeOnContextMenu = !!cfg2.strikeOnContextMenu;

        state.onBlocked    = (typeof cfg2.onBlocked === "function") ? cfg2.onBlocked : state.onBlocked;
        state.onInvalidate = (typeof cfg2.onInvalidate === "function") ? cfg2.onInvalidate : state.onInvalidate;
        state.onViolation  = (typeof cfg2.onViolation === "function") ? cfg2.onViolation : state.onViolation;

        if (cfg2.cameraVideoEl) state.cameraVideoEl = normalizeMaybeDuplicateEl(cfg2.cameraVideoEl);
        if (cfg2.cameraIndicatorEl) state.cameraIndicatorEl = normalizeMaybeDuplicateEl(cfg2.cameraIndicatorEl);
      }

      configure(cfg);
      if (!state.postUrl) throw new Error("AntiCheatLite: postUrl is required");

      function getOrCreateTabId() {
        try {
          let id = sessionStorage.getItem(state.tabKey);
          if (!id) {
            id = "tab_" + Math.random().toString(16).slice(2) + "_" + nowMs().toString(16);
            sessionStorage.setItem(state.tabKey, id);
          }
          return id;
        } catch (_) {
          return "tab_" + Math.random().toString(16).slice(2) + "_" + nowMs().toString(16);
        }
      }

      function setBadgeActive(active) {
        const el = state.cameraIndicatorEl;
        if (!el) return;
        if (active) {
          el.classList.remove("hidden");
          el.classList.add("flex");
        } else {
          el.classList.add("hidden");
          el.classList.remove("flex");
        }
      }

      function stopStream() {
        try {
          if (state.stream) state.stream.getTracks().forEach(t => { try { t.stop(); } catch (_) {} });
        } catch (_) {}
        state.stream = null;
        if (state.cameraVideoEl) { try { state.cameraVideoEl.srcObject = null; } catch (_) {} }
        setBadgeActive(false);
      }

      function enforceNoSelectCSS() {
        try {
          const css = `html,body{-webkit-touch-callout:none!important}*{-webkit-user-select:none!important;user-select:none!important}`;
          const style = document.createElement("style");
          style.type = "text/css";
          style.setAttribute("data-anti-cheat", "1");
          style.appendChild(document.createTextNode(css));
          document.head.appendChild(style);
          state.styleEl = style;
        } catch (_) {}
      }

      function restoreNoSelectCSS() {
        try { if (state.styleEl && state.styleEl.parentNode) state.styleEl.parentNode.removeChild(state.styleEl); } catch (_) {}
        state.styleEl = null;
      }

      function readLock() {
        try {
          const raw = localStorage.getItem(state.lockKey);
          return raw ? safeJsonParse(raw) : null;
        } catch (_) { return null; }
      }

      function writeLock() {
        try {
          localStorage.setItem(state.lockKey, JSON.stringify({ attemptId: state.attemptId, tabId: state.tabId, ts: nowMs() }));
        } catch (_) {}
      }

      function clearLockIfOwned() {
        try {
          const cur = readLock();
          if (cur && cur.tabId === state.tabId) localStorage.removeItem(state.lockKey);
        } catch (_) {}
      }

      function cleanupTimers() {
        if (state.hbTimer) { try { clearInterval(state.hbTimer); } catch (_) {} }
        state.hbTimer = null;
      }

      function unbindListeners() {
        document.removeEventListener("visibilitychange", onVisibilityChange, true);
        global.removeEventListener("blur", onBlur, true);

        document.removeEventListener("contextmenu", onContextMenu, true);
        document.removeEventListener("copy", onCopy, true);
        document.removeEventListener("cut", onCut, true);
        document.removeEventListener("paste", onPaste, true);
        document.removeEventListener("keydown", onKeyDown, true);

        global.removeEventListener("storage", onStorage, false);
        global.removeEventListener("pagehide", cleanup, true);
      }

      function cleanup() {
        cleanupTimers();
        if (state.bc) { try { state.bc.close(); } catch (_) {} }
        state.bc = null;

        unbindListeners();
        restoreNoSelectCSS();
        stopStream();
        clearLockIfOwned();
      }

      function invalidate(reason, meta) {
        if (state.invalidated) return;
        state.invalidated = true;
        state.enabled = false;

        const info = meta || {};
        cleanup();
        postEvent("INVALIDATED_" + String(reason || "UNKNOWN").toUpperCase(), { strike: 0, detail: info });

        if (state.onInvalidate) {
          state.onInvalidate({
            reason: reason || "UNKNOWN",
            attemptId: state.attemptId,
            testCode: state.testCode,
            violations: state.violations,
            event: info.event || null
          });
        }
      }

      async function postEvent(eventName, extra) {
        extra = extra || {};
        try {
          const payload = {
            attemptId: state.attemptId,
            test: state.testCode,
            tabId: state.tabId,
            event: String(eventName || "UNKNOWN").toUpperCase(),
            strike: extra.strike ? 1 : 0,
            max: state.maxViolations,
            clientTs: extra.clientTs || nowMs()
          };
          if (typeof extra.detail === "object" && extra.detail) payload.detail = extra.detail;

          const res = await fetch(state.postUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json" },
            body: JSON.stringify(payload),
            cache: "no-store",
            keepalive: true,
            credentials: "same-origin"
          });

          return await res.json().catch(() => null);
        } catch (_) {
          return null;
        }
      }

      async function acquireLockOrCheat(lockStaleMs) {
        const cur = readLock();
        if (cur && cur.tabId && cur.tabId !== state.tabId) {
          const stale = (nowMs() - (cur.ts || 0)) > lockStaleMs;
          if (!stale) {
            await postEvent("MULTI_TAB_DETECTED", { strike: 0 });
            invalidate("MULTI_TAB_DETECTED", { event: "MULTI_TAB_DETECTED" });
            return false;
          }
        }
        writeLock();
        return true;
      }

      function startHeartbeat(heartbeatMs) {
        state.hbTimer = setInterval(() => {
          if (state.invalidated || !state.enabled) return;
          writeLock();
        }, heartbeatMs);
      }

      function setupBroadcastChannel() {
        if (!("BroadcastChannel" in global)) return;
        try {
          const bc = new BroadcastChannel("__ac_bc__:" + state.attemptId);
          state.bc = bc;

          bc.onmessage = (e) => {
            const msg = e && e.data;
            if (!msg || msg.attemptId !== state.attemptId) return;
            if (msg.type === "HELLO" && msg.tabId && msg.tabId !== state.tabId) {
              postEvent("MULTI_TAB_HELLO", { strike: 0 });
              invalidate("MULTI_TAB_HELLO", { event: "MULTI_TAB_HELLO" });
            }
          };

          bc.postMessage({ type: "HELLO", attemptId: state.attemptId, tabId: state.tabId, ts: nowMs() });
        } catch (_) {}
      }

      function onStorage(e) {
        if (!e || e.key !== state.lockKey) return;
        const cur = readLock();
        if (!cur || !cur.tabId) return;
        if (cur.tabId !== state.tabId) {
          postEvent("MULTI_TAB_STORAGE", { strike: 0 });
          invalidate("MULTI_TAB_STORAGE", { event: "MULTI_TAB_STORAGE" });
        }
      }

      function crossInstanceDebounce() {
        try {
          const prev = parseInt(localStorage.getItem(state.strikeKey) || "0", 10);
          const t = nowMs();
          if (Number.isFinite(prev) && prev > 0 && (t - prev) < state.strikeCooldownMs) return true;
          localStorage.setItem(state.strikeKey, String(t));
          return false;
        } catch (_) {
          return false;
        }
      }

      function enqueueStrike(fn) {
        strikeChain = strikeChain.then(fn).catch(() => {});
        return strikeChain;
      }

      function strike(eventName, detail) {
        return enqueueStrike(async () => {
          if (state.invalidated || !state.enabled) return;

          const t = nowMs();
          if (state.strikeCooldownMs > 0 && (t - state.lastStrikeAt) < state.strikeCooldownMs) return;
          state.lastStrikeAt = t;

          if (crossInstanceDebounce()) return;

          const predicted = state.violations + 1;
          const ev = String(eventName || "UNKNOWN").toUpperCase();
          const res = await postEvent(ev, { strike: 1, detail: detail || null, clientTs: t });

          if (res && typeof res.violations === "number") state.violations = parseInt(res.violations, 10) || predicted;
          else state.violations = predicted;

          const willInvalidate = !!(res && res.invalidated) || (state.violations >= state.maxViolations);
          const action = willInvalidate ? "AUTOSUBMIT" : "WARN";

          if (state.onViolation) {
            state.onViolation({
              count: state.violations,
              warningLimit: state.warningLimit,
              maxViolations: state.maxViolations,
              willInvalidate,
              action,
              event: ev
            });
          }

          if (willInvalidate) {
            invalidate(res && res.invalidated ? "SERVER_INVALIDATED" : "MAX_VIOLATIONS_REACHED", { event: ev });
          }
        });
      }

      // 1 hidden cycle = 1 strike, dihitung saat kembali ke tab
      function onVisibilityChange() {
        if (state.invalidated || !state.enabled) return;

        if (document.hidden) {
          if (!state.hiddenAt) {
            state.hiddenAt = nowMs();
            state.hiddenCycleId = state.hiddenAt;
            state.hiddenCycleActive = true;
          }
          return;
        }

        if (state.hiddenAt) {
          const dt = nowMs() - state.hiddenAt;
          const cycleId = state.hiddenCycleId;

          // reset cycle dulu supaya tidak ada kemungkinan dobel hit
          state.hiddenAt = 0;
          state.hiddenCycleId = 0;
          state.hiddenCycleActive = false;

          if (dt >= state.hiddenGraceMs) {
            strike("TAB_HIDDEN", { hiddenMs: dt, cycleId: cycleId });
          }
        }
      }

      // BLUR: hanya log (opsional), tidak strike (untuk mencegah double-count)
      function onBlur() {
        if (!state.countWindowBlur) return;
        // log saja untuk investigasi, tapi tidak menambah violations
        postEvent("WINDOW_BLUR", { strike: 0, detail: { vis: document.visibilityState } });
      }

      function onContextMenu(e) {
        e.preventDefault();
        postEvent("CONTEXT_MENU_BLOCKED", { strike: state.strikeOnContextMenu ? 1 : 0 });
        if (state.strikeOnContextMenu) strike("CONTEXT_MENU");
      }
      function onCopy(e) {
        e.preventDefault();
        postEvent("COPY_BLOCKED", { strike: state.strikeOnCopy ? 1 : 0 });
        if (state.strikeOnCopy) strike("COPY_ATTEMPT");
      }
      function onCut(e) {
        e.preventDefault();
        postEvent("CUT_BLOCKED", { strike: state.strikeOnCut ? 1 : 0 });
        if (state.strikeOnCut) strike("CUT_ATTEMPT");
      }
      function onPaste(e) {
        e.preventDefault();
        postEvent("PASTE_BLOCKED", { strike: state.strikeOnPaste ? 1 : 0 });
        if (state.strikeOnPaste) strike("PASTE_ATTEMPT");
      }

      function onKeyDown(e) {
        const key = String(e.key || "").toLowerCase();
        const ctrl = e.ctrlKey || e.metaKey;
        if (ctrl && ["c", "x", "v", "a", "p", "s", "u"].includes(key)) {
          e.preventDefault();
          postEvent("SHORTCUT_BLOCKED_" + key.toUpperCase(), { strike: 0 });
        }
        if (key === "printscreen") {
          e.preventDefault();
          postEvent("PRINTSCREEN_KEY", { strike: 0 });
        }
      }

      async function getStreamPreferFront() {
        if (!global.isSecureContext) throw new Error("INSECURE_CONTEXT");
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) throw new Error("MEDIA_UNSUPPORTED");

        try {
          return await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { exact: "user" }, width: { ideal: 640 }, height: { ideal: 360 } } });
        } catch (_) {}
        try {
          return await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: "user" }, width: { ideal: 640 }, height: { ideal: 360 } } });
        } catch (_) {}
        return await navigator.mediaDevices.getUserMedia({ audio: false, video: true });
      }

      async function startCameraOrBlock() {
        if (!state.cameraRequired) return;
        const videoEl = state.cameraVideoEl;
        if (!videoEl) {
          if (state.onBlocked) state.onBlocked({ reason: "CAMERA_ELEMENT_MISSING" });
          invalidate("CAMERA_ELEMENT_MISSING", { event: "CAMERA_ELEMENT_MISSING" });
          return;
        }

        try {
          const stream = await getStreamPreferFront();
          state.stream = stream;

          videoEl.setAttribute("autoplay", "autoplay");
          videoEl.setAttribute("muted", "muted");
          videoEl.setAttribute("playsinline", "playsinline");

          try { videoEl.srcObject = stream; } catch (_) {}
          const p = videoEl.play && videoEl.play();
          if (p && typeof p.catch === "function") p.catch(() => {});

          setBadgeActive(true);
          postEvent("CAMERA_OK", { strike: 0 });
        } catch (err) {
          setBadgeActive(false);
          stopStream();

          const msg = String((err && err.message) || err || "CAMERA_DENIED");
          postEvent("CAMERA_FAIL", { strike: 0, detail: { reason: msg } });

          if (state.onBlocked) {
            state.onBlocked({
              reason: (msg === "INSECURE_CONTEXT") ? "INSECURE_CONTEXT"
                    : (msg === "MEDIA_UNSUPPORTED") ? "CAMERA_UNSUPPORTED"
                    : "CAMERA_DENIED"
            });
          }
        }
      }

      function bindListeners() {
        document.addEventListener("visibilitychange", onVisibilityChange, true);
        global.addEventListener("blur", onBlur, true);

        document.addEventListener("contextmenu", onContextMenu, true);
        document.addEventListener("copy", onCopy, true);
        document.addEventListener("cut", onCut, true);
        document.addEventListener("paste", onPaste, true);
        document.addEventListener("keydown", onKeyDown, true);

        global.addEventListener("storage", onStorage, false);
        global.addEventListener("pagehide", cleanup, true);
      }

      async function syncFromServer() {
        const res = await postEvent("HELLO", { strike: 0, detail: { vis: document.visibilityState } });
        if (res && res.invalidated) {
          if (typeof res.violations === "number") state.violations = parseInt(res.violations, 10) || state.violations;
          invalidate("SERVER_INVALIDATED", { event: "HELLO" });
          return;
        }
        if (res && typeof res.violations === "number") state.violations = parseInt(res.violations, 10) || 0;
      }

      async function start() {
        if (state.started) return;
        state.started = true;

        state.tabId = getOrCreateTabId();

        const heartbeatMs = clampInt(cfg.heartbeatMs, 200, 10000, 1000);
        const lockStaleMs = clampInt(cfg.lockStaleMs, 500, 60000, 5000);

        const ok = await acquireLockOrCheat(lockStaleMs);
        if (!ok) return;

        enforceNoSelectCSS();
        bindListeners();
        setupBroadcastChannel();
        startHeartbeat(heartbeatMs);

        // jika start saat hidden, set baseline tanpa langsung strike (strike hanya saat user balik)
        state.hiddenAt = document.hidden ? nowMs() : 0;
        state.hiddenCycleId = state.hiddenAt || 0;
        state.hiddenCycleActive = !!state.hiddenAt;

        await syncFromServer();
        await startCameraOrBlock();
      }

      function setEnabled(flag) {
        state.enabled = !!flag;
        if (!state.enabled) cleanupTimers();
      }

      function getState() {
        return {
          attemptId: state.attemptId,
          testCode: state.testCode,
          tabId: state.tabId,
          started: state.started,
          enabled: state.enabled,
          invalidated: state.invalidated,
          violations: state.violations,
          maxViolations: state.maxViolations,
          warningLimit: state.warningLimit
        };
      }

      const api = { start, invalidate, setEnabled, getState, configure };
      REG[attemptId] = api;
      return api;
    }
  };

  global.AntiCheatLite = AntiCheatLite;
})(window);
