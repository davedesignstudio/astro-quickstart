(function () {
  const cfg = window.RKTStation || {};
  const pinInput = document.getElementById("rkt-pin");
  const pinSave = document.getElementById("rkt-pin-save");
  const pinPanel = document.getElementById("rkt-pin-panel");
  const controls = document.getElementById("rkt-controls");
  const connection = document.getElementById("rkt-connection");
  const lastCheck = document.getElementById("rkt-last-check");
  const queueEl = document.getElementById("rkt-queue");
  const emptyEl = document.getElementById("rkt-empty");
  const preview = document.getElementById("rkt-preview");
  const autoPrint = document.getElementById("rkt-auto-print");
  const soundToggle = document.getElementById("rkt-sound");
  const testPrintBtn = document.getElementById("rkt-test-print");

  const STORAGE_PIN = "rkt_station_pin";
  const printedIds = new Set();
  let timer = null;
  let pin = localStorage.getItem(STORAGE_PIN) || "";

  function setStatus(text, kind) {
    connection.textContent = text;
    connection.className = "rkt-pill rkt-pill--" + (kind || "warn");
  }

  function playChime() {
    if (!soundToggle.checked) return;
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = "triangle";
      o.frequency.value = 880;
      g.gain.value = 0.04;
      o.connect(g);
      g.connect(ctx.destination);
      o.start();
      setTimeout(function () {
        o.frequency.value = 1175;
      }, 120);
      setTimeout(function () {
        o.stop();
        ctx.close();
      }, 320);
    } catch (e) {
      // Ignore audio failures (autoplay policies, etc.).
    }
  }

  function printHtml(html, orderId) {
    return new Promise(function (resolve) {
      const frame = document.createElement("iframe");
      frame.style.position = "fixed";
      frame.style.right = "0";
      frame.style.bottom = "0";
      frame.style.width = "0";
      frame.style.height = "0";
      frame.style.border = "0";
      frame.setAttribute("data-order-id", String(orderId || ""));
      document.body.appendChild(frame);

      const doc = frame.contentDocument || frame.contentWindow.document;
      doc.open();
      doc.write(html);
      doc.close();

      const finish = function () {
        try {
          frame.contentWindow.focus();
          frame.contentWindow.print();
        } catch (e) {
          // no-op
        }
        setTimeout(function () {
          frame.remove();
          resolve();
        }, 800);
      };

      setTimeout(finish, 350);
    });
  }

  async function acknowledge(orderId) {
    const url = cfg.printedUrl + orderId + "/printed";
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "X-RKT-PIN": pin,
        "Content-Type": "application/json",
      },
    });
    if (!res.ok) {
      throw new Error("Failed to acknowledge print for #" + orderId);
    }
  }

  function renderQueue(tickets) {
    queueEl.innerHTML = "";
    if (!tickets.length) {
      emptyEl.hidden = false;
      return;
    }
    emptyEl.hidden = true;
    tickets.forEach(function (t) {
      const li = document.createElement("li");
      const left = document.createElement("div");
      left.innerHTML =
        "<strong>#" +
        escapeHtml(t.number) +
        "</strong><span>" +
        escapeHtml(t.customer || "") +
        " · " +
        escapeHtml(t.order_type || "") +
        "</span>";
      const btn = document.createElement("button");
      btn.className = "rkt-btn rkt-btn--ghost";
      btn.textContent = "Print";
      btn.addEventListener("click", function () {
        handleTicket(t, true);
      });
      li.appendChild(left);
      li.appendChild(btn);
      queueEl.appendChild(li);
    });
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  async function handleTicket(ticket, force) {
    if (!force && printedIds.has(ticket.id)) return;
    printedIds.add(ticket.id);
    preview.srcdoc = ticket.html;
    playChime();
    if (autoPrint.checked || force) {
      await printHtml(ticket.html, ticket.id);
      try {
        await acknowledge(ticket.id);
      } catch (e) {
        setStatus("Ack failed", "err");
      }
    }
  }

  async function poll() {
    if (!pin) return;
    try {
      const url = cfg.restUrl + (cfg.restUrl.indexOf("?") >= 0 ? "&" : "?") + "pin=" + encodeURIComponent(pin);
      const res = await fetch(url, {
        headers: { "X-RKT-PIN": pin },
        cache: "no-store",
      });
      if (res.status === 401 || res.status === 403) {
        setStatus("Bad PIN", "err");
        return;
      }
      if (!res.ok) {
        setStatus("Poll error", "err");
        return;
      }
      const data = await res.json();
      setStatus("Listening", "ok");
      lastCheck.textContent = "Checked " + new Date().toLocaleTimeString();
      const tickets = data.tickets || [];
      renderQueue(tickets);
      for (const t of tickets) {
        await handleTicket(t, false);
      }
    } catch (e) {
      setStatus("Offline", "err");
    }
  }

  function start() {
    if (timer) clearInterval(timer);
    const seconds = Math.max(2, Number(cfg.pollSeconds) || 4);
    poll();
    timer = setInterval(poll, seconds * 1000);
    pinPanel.hidden = true;
    controls.hidden = false;
    setStatus("Listening", "ok");
  }

  pinSave.addEventListener("click", function () {
    pin = (pinInput.value || "").trim();
    if (!pin) {
      setStatus("PIN required", "err");
      return;
    }
    localStorage.setItem(STORAGE_PIN, pin);
    start();
  });

  testPrintBtn.addEventListener("click", function () {
    const sample =
      "<!DOCTYPE html><html><head><title>Test</title></head><body style=\"font-family: monospace; padding: 12px;\">" +
      "<h1>TEST TICKET</h1><p>" +
      escapeHtml(cfg.restaurant || "Restaurant") +
      "</p><p>Kitchen printer OK — " +
      new Date().toLocaleString() +
      "</p></body></html>";
    printHtml(sample, "test");
  });

  if (pin) {
    pinInput.value = pin;
    start();
  }
})();
