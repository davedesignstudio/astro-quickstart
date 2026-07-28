<?php
/**
 * Kitchen Station UI template.
 *
 * @package RestaurantKitchenPrint
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="rkp-station" class="rkp-station" data-ready="0">
	<header class="rkp-station__header">
		<div>
			<p class="rkp-station__eyebrow">Kitchen Station</p>
			<h1 class="rkp-station__title"><?php echo esc_html( RKP_Settings::get()['restaurant_name'] ?? get_bloginfo( 'name' ) ); ?></h1>
			<p class="rkp-station__sub">Leave this page open. New orders print automatically.</p>
		</div>
		<div class="rkp-station__status">
			<span id="rkp-connection" class="rkp-pill rkp-pill--offline">Offline</span>
			<span id="rkp-last-check" class="rkp-muted">Waiting…</span>
		</div>
	</header>

	<section id="rkp-auth" class="rkp-auth">
		<label for="rkp-token">Station token</label>
		<div class="rkp-auth__row">
			<input type="password" id="rkp-token" autocomplete="off" placeholder="Paste token from WooCommerce → Settings → Kitchen Print" />
			<button type="button" id="rkp-connect" class="rkp-btn">Connect</button>
		</div>
		<p class="rkp-muted">Find the token under WooCommerce → Settings → Kitchen Print. Staff with shop manager access can also open this page while logged in.</p>
	</section>

	<section class="rkp-controls">
		<button type="button" id="rkp-test-print" class="rkp-btn rkp-btn--ghost">Test print</button>
		<button type="button" id="rkp-poll-now" class="rkp-btn rkp-btn--ghost">Check now</button>
		<label class="rkp-toggle">
			<input type="checkbox" id="rkp-sound" checked />
			Sound alerts
		</label>
	</section>

	<section class="rkp-feed">
		<h2>Live tickets</h2>
		<ul id="rkp-job-list" class="rkp-job-list">
			<li class="rkp-empty">No pending tickets.</li>
		</ul>
	</section>

	<iframe id="rkp-print-frame" class="rkp-print-frame" title="Print frame"></iframe>
</div>
