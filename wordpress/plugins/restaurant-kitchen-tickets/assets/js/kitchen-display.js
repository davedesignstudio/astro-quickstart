(function () {
  const cfg = window.RKTKitchen || {};
  const queueEl = document.getElementById("rkt-queue");
  const recentEl = document.getElementById("rkt-recent");
  const clockEl = document.getElementById("rkt-clock");
  const connEl = document.getElementById("rkt-connection");
  const frame = document.getElementById("rkt-print-frame");
  const alertEl = document.getElementById("rkt-alert");
  const refreshBtn = document.getElementById("rkt-refresh");

  const seen = new Set();
  let printing = false;
  const printQueue = [];

  function tickClock() {
    if (!clockEl) return;
    clockEl.textContent = new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  }

  function setConnection(ok) {
    if (!connEl) return;
    connEl.textContent = ok ? "Connected" : "Reconnecting…";
    connEl.classList.toggle("rkt-pill-live", ok);
    connEl.classList.toggle("rkt-pill-offline", !ok);
  }

  function playAlert() {
    if (!cfg.soundEnabled || !alertEl) return;
    try {
      alertEl.currentTime = 0;
      alertEl.play().catch(function () {});
    } catch (e) {}
  }

  function renderQueueCard(ticket, state) {
    const card = document.createElement("article");
    card.className = "rkt-card" + (state ? " " + state : "");
    card.dataset.queueId = String(ticket.queue_id);
    card.innerHTML =
      '<div class="rkt-card-top">' +
      '<div class="rkt-order-no">#' +
      escapeHtml(ticket.order_number) +
      "</div>" +
      '<div class="rkt-badge">' +
      escapeHtml(ticket.fulfillment || "Order") +
      "</div>" +
      "</div>" +
      '<div class="rkt-meta">' +
      escapeHtml(ticket.customer || "Guest") +
      (ticket.requested ? " · Ready " + escapeHtml(ticket.requested) : "") +
      " · " +
      escapeHtml(String(ticket.item_count || 0)) +
      " items</div>";
    return card;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function enqueuePrint(ticket) {
    if (seen.has(ticket.queue_id)) return;
    seen.add(ticket.queue_id);
    printQueue.push(ticket);
    if (queueEl) {
      const empty = document.getElementById("rkt-empty");
      if (empty) empty.remove();
      queueEl.prepend(renderQueueCard(ticket, "printing"));
    }
    playAlert();
    processPrintQueue();
  }

  function processPrintQueue() {
    if (printing || !printQueue.length) return;
    printing = true;
    const ticket = printQueue.shift();

    frame.onload = function () {
      try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
      } catch (e) {}

      setTimeout(function () {
        markPrinted(ticket.queue_id);
        const card = queueEl && queueEl.querySelector('[data-queue-id="' + ticket.queue_id + '"]');
        if (card) {
          card.classList.remove("printing");
          card.classList.add("done");
        }
        printing = false;
        processPrintQueue();
      }, 1200);
    };

    frame.src = ticket.ticket_url + "?t=" + Date.now();
  }

  function markPrinted(queueId) {
    const body = new FormData();
    body.append("action", "rkt_mark_printed");
    body.append("nonce", cfg.nonce);
    body.append("queue_id", String(queueId));
    return fetch(cfg.ajaxUrl, { method: "POST", body: body, credentials: "same-origin" });
  }

  function poll() {
    const body = new FormData();
    body.append("action", "rkt_poll_tickets");
    body.append("nonce", cfg.nonce);

    fetch(cfg.ajaxUrl, { method: "POST", body: body, credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        setConnection(true);
        if (!json || !json.success) return;
        (json.data.tickets || []).forEach(enqueuePrint);
      })
      .catch(function () {
        setConnection(false);
      });
  }

  function loadRecent() {
    const body = new FormData();
    body.append("action", "rkt_recent_orders");
    body.append("nonce", cfg.nonce);

    fetch(cfg.ajaxUrl, { method: "POST", body: body, credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (!json || !json.success || !recentEl) return;
        recentEl.innerHTML = "";
        (json.data.orders || []).forEach(function (order) {
          const card = document.createElement("article");
          card.className = "rkt-card";
          card.innerHTML =
            '<div class="rkt-card-top">' +
            '<div class="rkt-order-no">#' +
            escapeHtml(order.number) +
            "</div>" +
            '<div class="rkt-badge">' +
            escapeHtml(order.fulfillment) +
            "</div>" +
            "</div>" +
            '<div class="rkt-meta">' +
            escapeHtml(order.customer) +
            " · " +
            escapeHtml(order.status) +
            (order.printed ? " · Printed" : "") +
            "</div>";
          card.addEventListener("click", function () {
            window.open(order.ticket_url, "_blank");
          });
          recentEl.appendChild(card);
        });
      })
      .catch(function () {});
  }

  tickClock();
  setInterval(tickClock, 1000);
  poll();
  loadRecent();
  setInterval(poll, cfg.pollInterval || 4000);
  setInterval(loadRecent, 15000);
  if (refreshBtn) refreshBtn.addEventListener("click", loadRecent);
})();
