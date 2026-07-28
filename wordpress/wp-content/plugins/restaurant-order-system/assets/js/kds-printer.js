/**
 * Kitchen Display System — auto-print new orders.
 */
(function () {
	'use strict';

	if (typeof rosKds === 'undefined') {
		return;
	}

	var printQueue = [];
	var isPrinting = false;
	var printedIds = {};
	var statusEl = document.getElementById('ros-kds-status-text');
	var indicatorEl = document.getElementById('ros-kds-indicator');
	var ordersEl = document.getElementById('ros-kds-orders');
	var emptyEl = document.getElementById('ros-kds-empty');
	var printFrame = document.getElementById('ros-print-frame');

	function setStatus(text, active) {
		if (statusEl) {
			statusEl.textContent = text;
		}
		if (indicatorEl) {
			indicatorEl.className = 'ros-kds-indicator' + (active ? ' active' : '');
		}
	}

	function fetchPending() {
		return fetch(rosKds.pendingUrl, {
			headers: {
				'X-WP-Nonce': rosKds.restNonce
			},
			credentials: 'same-origin'
		})
			.then(function (res) {
				if (!res.ok) {
					throw new Error('Failed to fetch orders');
				}
				return res.json();
			});
	}

	function fetchTicketHtml(orderId) {
		var url = rosKds.ticketUrl + '&order_id=' + orderId + '&nonce=' + encodeURIComponent(rosKds.nonce);
		return fetch(url, { credentials: 'same-origin' })
			.then(function (res) {
				return res.json();
			});
	}

	function markPrinted(orderId) {
		return fetch(rosKds.markPrintedUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': rosKds.restNonce
			},
			credentials: 'same-origin',
			body: JSON.stringify({ order_id: orderId })
		});
	}

	function printTicket(orderId) {
		return fetchTicketHtml(orderId).then(function (response) {
			if (!response.success || !response.data || !response.data.html) {
				throw new Error('Invalid ticket response');
			}

			return new Promise(function (resolve, reject) {
				var frame = printFrame;
				if (!frame) {
					reject(new Error('Print frame missing'));
					return;
				}

				var doc = frame.contentWindow || frame.contentDocument;
				if (doc.document) {
					doc = doc.document;
				}

				frame.onload = function () {
					try {
						frame.contentWindow.focus();
						frame.contentWindow.print();
						setTimeout(resolve, 1500);
					} catch (e) {
						reject(e);
					}
				};

				doc.open();
				doc.write(response.data.html);
				doc.close();
			});
		});
	}

	function processQueue() {
		if (isPrinting || printQueue.length === 0) {
			return;
		}

		isPrinting = true;
		var orderId = printQueue.shift();

		setStatus(rosKds.strings.printing + ' #' + orderId, true);

		printTicket(orderId)
			.then(function () {
				return markPrinted(orderId);
			})
			.then(function () {
				printedIds[orderId] = true;
				isPrinting = false;
				setStatus(rosKds.strings.connected, true);
				processQueue();
			})
			.catch(function () {
				isPrinting = false;
				printQueue.unshift(orderId);
				setStatus('Print error — retrying...', false);
				setTimeout(processQueue, 3000);
			});
	}

	function renderOrderList(orders) {
		if (!ordersEl) {
			return;
		}

		var pending = orders.filter(function (o) {
			return o.use_browser && !printedIds[o.id];
		});

		if (pending.length === 0) {
			if (emptyEl) {
				emptyEl.style.display = 'block';
			}
			ordersEl.querySelectorAll('.ros-kds-order-card').forEach(function (el) {
				el.remove();
			});
			return;
		}

		if (emptyEl) {
			emptyEl.style.display = 'none';
		}

		pending.forEach(function (order) {
			if (ordersEl.querySelector('[data-order-id="' + order.id + '"]')) {
				return;
			}

			var card = document.createElement('div');
			card.className = 'ros-kds-order-card';
			card.setAttribute('data-order-id', order.id);
			card.innerHTML =
				'<div class="ros-kds-order-number">#' + order.number + '</div>' +
				'<div class="ros-kds-order-type">' + order.order_type + '</div>' +
				'<div class="ros-kds-order-customer">' + (order.customer || 'Guest') + '</div>' +
				'<div class="ros-kds-order-items">' + order.item_count + ' items</div>' +
				'<div class="ros-kds-order-time">' + order.created + '</div>';
			ordersEl.appendChild(card);
		});
	}

	function poll() {
		fetchPending()
			.then(function (data) {
				setStatus(rosKds.strings.connected, true);
				var orders = data.orders || [];
				renderOrderList(orders);

				if (rosKds.autoPrint) {
					orders.forEach(function (order) {
						if (order.use_browser && !printedIds[order.id] && printQueue.indexOf(order.id) === -1) {
							printQueue.push(order.id);
							setStatus(rosKds.strings.newOrder, true);
						}
					});
					processQueue();
				}
			})
			.catch(function () {
				setStatus('Connection error — retrying...', false);
			});
	}

	setStatus(rosKds.strings.waiting, false);
	poll();
	setInterval(poll, rosKds.pollInterval);
})();
