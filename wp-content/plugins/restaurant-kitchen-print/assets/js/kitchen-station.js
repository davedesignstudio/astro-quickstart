/**
 * Kitchen Station auto-print client.
 * Polls REST for pending jobs and silently prints via hidden iframe.
 */
(function () {
  const cfg = window.rkpStation || {};
  const els = {
    root: document.getElementById("rkp-station"),
    auth: document.getElementById("rkp-auth"),
    token: document.getElementById("rkp-token"),
    connect: document.getElementById("rkp-connect"),
    connection: document.getElementById("rkp-connection"),
    lastCheck: document.getElementById("rkp-last-check"),
    list: document.getElementById("rkp-job-list"),
    sound: document.getElementById("rkp-sound"),
    testPrint: document.getElementById("rkp-test-print"),
    pollNow: document.getElementById("rkp-poll-now"),
    frame: document.getElementById("rkp-print-frame"),
  };

  if (!els.root) return;

  let token = localStorage.getItem(cfg.storageKey || "rkp_station_token") || "";
  let timer = null;
  let printing = false;
  const queue = [];
  const seen = new Set();

  if (cfg.notifySound === false && els.sound) {
    els.sound.checked = false;
  }

  function setStatus(online, message) {
    if (!els.connection) return;
    els.connection.textContent = online ? "Online" : "Offline";
    els.connection.classList.toggle("rkp-pill--online", online);
    els.connection.classList.toggle("rkp-pill--offline", !online);
    if (message && els.lastCheck) els.lastCheck.textContent = message;
  }

  function headers() {
    const h = { Accept: "application/json" };
    if (token) h["X-RKP-Token"] = token;
    return h;
  }

  async function api(path, options) {
    const url = (cfg.restUrl || "").replace(/\/$/, "") + path;
    const res = await fetch(url, {
      credentials: "same-origin",
      headers: headers(),
      ...(options || {}),
    });
    if (!res.ok) {
      const body = await res.text();
      throw new Error(body || res.statusText);
    }
    return res.json();
  }

  function playChime() {
    if (!els.sound || !els.sound.checked) return;
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = "sine";
      o.frequency.value = 880;
      g.gain.value = 0.05;
      o.connect(g);
      g.connect(ctx.destination);
      o.start();
      setTimeout(function () {
        o.stop();
        ctx.close();
      }, 220);
    } catch (e) {
      /* ignore */
    }
  }

  function renderList(jobs) {
    if (!els.list) return;
    if (!jobs.length) {
      els.list.innerHTML = '<li class="rkp-empty">No pending tickets.</li>';
      return;
    }
    els.list.innerHTML = jobs
      .map(function (job) {
        const p = job.payload || {};
        return (
          '<li class="rkp-job">' +
          "<strong>#" +
          escapeHtml(String(p.order_number || job.order_id)) +
          "</strong> " +
          '<span class="rkp-job__type">' +
          escapeHtml(String(p.order_type_label || "")) +
          "</span>" +
          '<div class="rkp-job__meta">' +
          escapeHtml(String(p.customer || "")) +
          " · " +
          escapeHtml(String(job.created || "")) +
          "</div></li>"
        );
      })
      .join("");
  }

  function escapeHtml(str) {
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function printHtml(html) {
    return new Promise(function (resolve) {
      const copies = Math.max(1, Number(cfg.copies || 1));
      let remaining = copies;

      function once() {
        const doc = els.frame.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();
        setTimeout(function () {
          try {
            els.frame.contentWindow.focus();
            els.frame.contentWindow.print();
          } catch (e) {
            /* ignore */
          }
          remaining -= 1;
          if (remaining > 0) {
            setTimeout(once, 600);
          } else {
            resolve();
          }
        }, 250);
      }
      once();
    });
  }

  async function processQueue() {
    if (printing) return;
    printing = true;
    while (queue.length) {
      const job = queue.shift();
      try {
        playChime();
        await printHtml(job.html);
        await api("/printed/" + job.job_id, { method: "POST" });
      } catch (e) {
        console.error("RKP print failed", e);
      }
    }
    printing = false;
  }

  async function poll() {
    try {
      const data = await api("/pending");
      const jobs = data.jobs || [];
      renderList(jobs);
      jobs.forEach(function (job) {
        if (seen.has(job.job_id)) return;
        seen.add(job.job_id);
        queue.push(job);
      });
      setStatus(true, "Last check " + new Date().toLocaleTimeString());
      processQueue();
    } catch (e) {
      setStatus(false, "Auth or network error");
      console.warn(e);
    }
  }

  function start() {
    if (timer) clearInterval(timer);
    poll();
    timer = setInterval(poll, Math.max(1000, Number(cfg.pollIntervalMs || 3000)));
    els.root.setAttribute("data-ready", "1");
    if (token && els.auth) els.auth.classList.add("is-hidden");
  }

  if (els.connect) {
    els.connect.addEventListener("click", function () {
      token = (els.token.value || "").trim();
      if (token) localStorage.setItem(cfg.storageKey || "rkp_station_token", token);
      start();
    });
  }

  if (els.pollNow) els.pollNow.addEventListener("click", poll);
  if (els.testPrint) {
    els.testPrint.addEventListener("click", function () {
      printHtml(
        "<html><body style='font-family:monospace;padding:12px'><h1>TEST TICKET</h1><p>" +
          escapeHtml(cfg.restaurantName || "Restaurant") +
          "</p><p>If you see this on paper, auto-print is ready.</p></body></html>"
      );
    });
  }

  if (token && els.token) {
    els.token.value = token;
    start();
  } else {
    // Logged-in shop managers can poll without a token.
    start();
  }
})();
