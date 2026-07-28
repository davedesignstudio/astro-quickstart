(function () {
	'use strict';

	if (typeof rapKitchen === 'undefined') {
		return;
	}

	const state = {
		seenOrderIds: new Set(),
		autoPrint: rapKitchen.autoPrint,
		soundEnabled: rapKitchen.soundEnabled,
		isPrinting: false,
	};

	const els = {
		status: document.getElementById('rap-connection-status'),
		lastCheck: document.getElementById('rap-last-check'),
		pendingList: document.getElementById('rap-pending-orders'),
		preview: document.getElementById('rap-ticket-preview'),
		printContainer: document.getElementById('rap-print-container'),
		autoPrintToggle: document.getElementById('rap-auto-print-toggle'),
		testPrintBtn: document.getElementById('rap-test-print'),
		toggleSoundBtn: document.getElementById('rap-toggle-sound'),
	};

	function apiHeaders() {
		const headers = { Accept: 'application/json' };
		if (rapKitchen.secret) {
			headers['X-Kitchen-Secret'] = rapKitchen.secret;
		}
		return headers;
	}

	function setStatus(type, message) {
		els.status.className = 'rap-status rap-status--' + type;
		els.status.textContent = message;
	}

	function playNewOrderSound() {
		if (!state.soundEnabled) {
			return;
		}

		try {
			const ctx = new (window.AudioContext || window.webkitAudioContext)();
			const oscillator = ctx.createOscillator();
			const gain = ctx.createGain();
			oscillator.type = 'sine';
			oscillator.frequency.value = 880;
			gain.gain.value = 0.08;
			oscillator.connect(gain);
			gain.connect(ctx.destination);
			oscillator.start();
			setTimeout(function () {
				oscillator.stop();
				ctx.close();
			}, 180);
		} catch (e) {
			// Audio not available.
		}
	}

	function renderPendingOrders(orders) {
		if (!orders.length) {
			els.pendingList.innerHTML = '<p class="rap-empty">Waiting for orders…</p>';
			return;
		}

		els.pendingList.innerHTML = orders
			.map(function (order) {
				return (
					'<article class="rap-pending-card" data-order-id="' +
					order.id +
					'" data-html="' +
					encodeURIComponent(order.html) +
					'">' +
					'<div class="rap-pending-card__top"><span>#' +
					order.order_number +
					'</span><span>' +
					order.order_type +
					'</span></div>' +
					'<div class="rap-pending-card__meta">' +
					(order.customer || 'Guest') +
					'</div>' +
					'</article>'
				);
			})
			.join('');

		els.pendingList.querySelectorAll('.rap-pending-card').forEach(function (card) {
			card.addEventListener('click', function () {
				const html = decodeURIComponent(card.getAttribute('data-html'));
				els.preview.innerHTML = html;
			});
		});
	}

	async function markPrinted(orderId) {
		await fetch(rapKitchen.apiBase + '/mark-printed/' + orderId, {
			method: 'POST',
			headers: apiHeaders(),
		});
	}

	function printTicket(html, orderId) {
		return new Promise(function (resolve) {
			els.printContainer.innerHTML = html;

			const onAfterPrint = function () {
				window.removeEventListener('afterprint', onAfterPrint);
				els.printContainer.innerHTML = '';
				resolve();
			};

			window.addEventListener('afterprint', onAfterPrint);

			setTimeout(function () {
				window.print();
				// Fallback if afterprint never fires.
				setTimeout(function () {
					window.removeEventListener('afterprint', onAfterPrint);
					els.printContainer.innerHTML = '';
					resolve();
				}, 3000);
			}, 100);

			if (orderId) {
				markPrinted(orderId);
			}
		});
	}

	async function processNewOrders(orders) {
		const newOrders = orders.filter(function (order) {
			return !state.seenOrderIds.has(order.id);
		});

		if (!newOrders.length) {
			return;
		}

		newOrders.forEach(function (order) {
			state.seenOrderIds.add(order.id);
		});

		playNewOrderSound();

		const latest = newOrders[newOrders.length - 1];
		els.preview.innerHTML = latest.html;

		if (state.autoPrint && !state.isPrinting) {
			state.isPrinting = true;
			for (const order of newOrders) {
				await printTicket(order.html, order.id);
			}
			state.isPrinting = false;
		}
	}

	async function pollOrders() {
		try {
			const response = await fetch(rapKitchen.apiBase + '/pending', {
				headers: apiHeaders(),
				cache: 'no-store',
			});

			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}

			const data = await response.json();
			setStatus('online', 'Online');
			els.lastCheck.textContent = 'Last check: ' + new Date().toLocaleTimeString();

			renderPendingOrders(data.orders || []);
			await processNewOrders(data.orders || []);
		} catch (error) {
			setStatus('offline', 'Offline');
			els.lastCheck.textContent = 'Connection error';
		}
	}

	function bindControls() {
		if (els.autoPrintToggle) {
			els.autoPrintToggle.checked = state.autoPrint;
			els.autoPrintToggle.addEventListener('change', function () {
				state.autoPrint = els.autoPrintToggle.checked;
			});
		}

		if (els.toggleSoundBtn) {
			els.toggleSoundBtn.textContent = state.soundEnabled ? 'Sound On' : 'Sound Off';
			els.toggleSoundBtn.addEventListener('click', function () {
				state.soundEnabled = !state.soundEnabled;
				els.toggleSoundBtn.textContent = state.soundEnabled ? 'Sound On' : 'Sound Off';
			});
		}

		if (els.testPrintBtn) {
			els.testPrintBtn.addEventListener('click', function () {
				const testHtml =
					'<div class="rap-ticket"><header class="rap-ticket__header"><h1 class="rap-ticket__restaurant">' +
					rapKitchen.restaurant +
					'</h1><p class="rap-ticket__title">TEST PRINT</p></header><section class="rap-ticket__meta"><p><strong>Status:</strong> Printer connected</p><p><strong>Time:</strong> ' +
					new Date().toLocaleString() +
					'</p></section></div>';
				printTicket(testHtml, null);
			});
		}
	}

	setStatus('connecting', 'Connecting…');
	bindControls();
	pollOrders();
	setInterval(pollOrders, rapKitchen.pollInterval || 3000);
})();
