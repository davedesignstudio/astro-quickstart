(() => {
  const cfg = window.ROT_KITCHEN || {};
  const board = document.getElementById("rot-kitchen-board");
  const statusEl = document.getElementById("rot-connection");
  const autoPrintEl = document.getElementById("rot-auto-print");
  const printFrame = document.getElementById("rot-print-frame");
  const chime = document.getElementById("rot-chime");

  let serverTs = 0;
  let known = new Set();
  let printing = new Set();

  async function api(path, options = {}) {
    const res = await fetch(`${cfg.restUrl}${path}`, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": cfg.nonce,
        ...(options.headers || {}),
      },
      credentials: "same-origin",
    });
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  }

  function setStatus(text, state) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.classList.remove("is-live", "is-error");
    if (state) statusEl.classList.add(state);
  }

  function playChime() {
    if (!cfg.sound || !chime) return;
    chime.currentTime = 0;
    chime.play().catch(() => {});
  }

  function browserPrint(html) {
    if (!printFrame) return;
    const doc = printFrame.contentWindow.document;
    doc.open();
    doc.write(`<!doctype html><html><head><title>Ticket</title>
      <style>
        body{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;margin:0;padding:12px;color:#111}
        .rot-ticket{width:280px}
        .rot-ticket__brand,.rot-ticket__title{font-weight:700}
        .rot-ticket__items{list-style:none;padding:0;margin:0}
        .rot-ticket__items li{margin:0 0 8px}
        .qty{font-weight:700;margin-right:6px}
        .meta{margin-left:18px;font-size:12px}
        hr{border:none;border-top:1px dashed #999;margin:10px 0}
      </style></head><body>${html}</body></html>`);
    doc.close();
    setTimeout(() => {
      printFrame.contentWindow.focus();
      printFrame.contentWindow.print();
    }, 150);
  }

  function render(orders) {
    if (!board) return;
    if (!orders.length) {
      board.innerHTML = `<p class="rot-empty">${cfg.i18n.empty}</p>`;
      return;
    }

    board.innerHTML = orders
      .map((order) => {
        const cls = order.is_new ? "rot-ticket-card is-new" : "rot-ticket-card";
        return `
          <article class="${cls}" data-order-id="${order.id}">
            <h2>#${order.number}</h2>
            <div class="meta-row">
              <span>${order.order_type || ""} · ${order.requested || "ASAP"}</span>
              <span>${order.total || ""}</span>
            </div>
            <div class="ticket-html">${order.ticket_html || ""}</div>
            <div class="rot-actions">
              <button type="button" class="print" data-action="print">${cfg.i18n.print}</button>
              <button type="button" class="ack" data-action="ack">${cfg.i18n.ack}</button>
            </div>
          </article>`;
      })
      .join("");
  }

  async function poll() {
    try {
      const data = await api(`/kitchen-orders?since=${serverTs || 0}`);
      const orders = data.orders || [];
      serverTs = data.server_ts || serverTs;
      setStatus("Live", "is-live");

      for (const order of orders) {
        const id = String(order.id);
        if (!known.has(id) && order.is_new) {
          playChime();
          if (autoPrintEl?.checked && !printing.has(id)) {
            printing.add(id);
            browserPrint(order.ticket_html || "");
            // Best-effort cloud reprint if PrintNode is configured.
            api(`/orders/${order.id}/print`, { method: "POST" }).catch(() => {});
          }
        }
        known.add(id);
      }

      // Drop acked/disappeared ids so a future requeue can chime again.
      const visible = new Set(orders.map((o) => String(o.id)));
      known = new Set([...known].filter((id) => visible.has(id)));
      printing = new Set([...printing].filter((id) => visible.has(id)));

      render(orders);
    } catch (err) {
      setStatus("Disconnected", "is-error");
      console.error(err);
    }
  }

  board?.addEventListener("click", async (event) => {
    const btn = event.target.closest("button[data-action]");
    if (!btn) return;
    const card = btn.closest("[data-order-id]");
    if (!card) return;
    const id = card.getAttribute("data-order-id");
    const action = btn.getAttribute("data-action");

    try {
      if (action === "print") {
        const html = card.querySelector(".ticket-html")?.innerHTML || "";
        browserPrint(html);
        await api(`/orders/${id}/print`, { method: "POST" });
      }
      if (action === "ack") {
        await api(`/orders/${id}/ack`, { method: "POST" });
        await poll();
      }
    } catch (err) {
      console.error(err);
      alert("Action failed. Check that you are logged in as a shop manager.");
    }
  });

  poll();
  setInterval(poll, cfg.pollMs || 4000);
})();
