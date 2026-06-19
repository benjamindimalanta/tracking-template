(function () {
	'use strict';

	if (typeof adctConfig === 'undefined' || !adctConfig.ajaxUrl) {
		return;
	}

	var DEBOUNCE_MS = 2000;
	var recentKeys = {};
	var ELFSIGHT_SELECTORS = [
		'[class*="eapp-click-to-call"]',
		'[class*="elfsight-app-"]',
		'[class*="eapps-"]',
	];

	var FOOTER_SELECTORS = 'footer, .site-footer, #colophon, .elementor-location-footer';

	function getContext() {
		if (adctConfig.product && adctConfig.product.id) {
			return {
				id: adctConfig.product.id,
				title: adctConfig.product.title || '',
				url: adctConfig.product.url || window.location.href,
				price: adctConfig.product.price || '',
				mileage: adctConfig.product.mileage || '',
				image_url: adctConfig.product.image_url || '',
			};
		}

		if (adctConfig.page) {
			return {
				id: 0,
				title: adctConfig.page.title || document.title,
				url: adctConfig.page.url || window.location.href,
				price: '',
				mileage: '',
				image_url: '',
			};
		}

		return {
			id: 0,
			title: document.title,
			url: window.location.href,
			price: '',
			mileage: '',
			image_url: '',
		};
	}

	function buildPayload(contactType, agentId, agentName, source, clickedValue) {
		var context = getContext();
		var payload = {
			action: 'adct_log_click',
			nonce: adctConfig.nonce || '',
			product_id: context.id || '',
			product_title: context.title || '',
			product_url: context.url || window.location.href,
			product_price: context.price || '',
			product_mileage: context.mileage || '',
			product_image_url: context.image_url || '',
			agent_id: agentId || '',
			agent_name: agentName || '',
			contact_type: contactType || '',
			source: source || '',
			clicked_value: clickedValue || '',
		};

		if (typeof window.adctGetAttribution === 'function') {
			var attribution = window.adctGetAttribution();
			Object.keys(attribution).forEach(function (key) {
				payload[key] = attribution[key];
			});
		}

		return payload;
	}

	function shouldSkip(payload) {
		var keyParts = [
			payload.session_id || '',
			payload.product_id,
			payload.agent_id,
			payload.contact_type,
			payload.source,
		];

		if (payload.contact_type !== 'elfsight_call') {
			keyParts.push(payload.clicked_value);
		}

		var key = keyParts.join('|');

		var now = Date.now();
		if (recentKeys[key] && now - recentKeys[key] < DEBOUNCE_MS) {
			return true;
		}

		recentKeys[key] = now;
		return false;
	}

	function toFormBody(payload) {
		return Object.keys(payload)
			.map(function (key) {
				return encodeURIComponent(key) + '=' + encodeURIComponent(payload[key]);
			})
			.join('&');
	}

	function sendEvent(payload) {
		if (shouldSkip(payload)) {
			return;
		}

		var body = toFormBody(payload);
		var url = adctConfig.ajaxUrl;

		if (navigator.sendBeacon) {
			try {
				var formData = new FormData();
				Object.keys(payload).forEach(function (key) {
					formData.append(key, payload[key]);
				});
				if (navigator.sendBeacon(url, formData)) {
					return;
				}
			} catch (error) {
				// Fall through to fetch.
			}
		}

		try {
			fetch(url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
				body: body,
				credentials: 'same-origin',
				keepalive: true,
			});
		} catch (error) {
			// Tracking must never block the user action.
		}
	}

	function readData(el) {
		return {
			contactType: el.getAttribute('data-contact-type') || '',
			agentId: el.getAttribute('data-agent-id') || '',
			agentName: el.getAttribute('data-agent-name') || '',
			source: el.getAttribute('data-source') || '',
		};
	}

	function eventPath(event) {
		if (event.composedPath) {
			return event.composedPath();
		}

		var path = [];
		var node = event.target;

		while (node) {
			path.push(node);
			node = node.parentNode;
		}

		return path;
	}

	function classNameIncludesElfsight(className) {
		if (!className || typeof className !== 'string') {
			return false;
		}

		return (
			className.indexOf('eapp-click-to-call') !== -1 ||
			className.indexOf('elfsight-app-') !== -1 ||
			className.indexOf('eapps-') !== -1
		);
	}

	function nodeMatchesElfsight(node) {
		if (!node || node.nodeType !== 1) {
			return false;
		}

		if (node.classList) {
			for (var i = 0; i < node.classList.length; i++) {
				if (classNameIncludesElfsight(node.classList[i])) {
					return true;
				}
			}
		}

		if (node.matches) {
			for (var j = 0; j < ELFSIGHT_SELECTORS.length; j++) {
				try {
					if (node.matches(ELFSIGHT_SELECTORS[j])) {
						return true;
					}
				} catch (error) {
					// Ignore invalid selector edge cases.
				}
			}
		}

		return false;
	}

	function isElfsightClick(event) {
		var path = eventPath(event);
		var i;

		for (i = 0; i < path.length; i++) {
			if (nodeMatchesElfsight(path[i])) {
				return true;
			}
		}

		if (typeof event.clientX === 'number' && typeof event.clientY === 'number' && document.elementsFromPoint) {
			var stack = document.elementsFromPoint(event.clientX, event.clientY);

			for (i = 0; i < stack.length; i++) {
				if (nodeMatchesElfsight(stack[i])) {
					return true;
				}
			}
		}

		return false;
	}

	function findTelHref(event) {
		var path = eventPath(event);
		var i;

		for (i = 0; i < path.length; i++) {
			var node = path[i];

			if (node && node.tagName === 'A' && node.href && node.href.indexOf('tel:') === 0) {
				return node.href;
			}
		}

		return '';
	}

	function isFooterTelLink(anchor) {
		if (!anchor || anchor.tagName !== 'A' || !anchor.href || anchor.href.indexOf('tel:') !== 0) {
			return false;
		}

		if (anchor.closest('[data-track="contact"]')) {
			return false;
		}

		try {
			return !!anchor.closest(FOOTER_SELECTORS);
		} catch (error) {
			return false;
		}
	}

	function trackFooterTelClick(anchor) {
		sendEvent(
			buildPayload(
				'footer_landline',
				'',
				'Showroom Landline',
				'site_footer',
				anchor.href || ''
			)
		);
	}

	function getElfsightClickValue(event) {
		var tel = findTelHref(event);
		if (tel) {
			return tel;
		}

		var path = eventPath(event);
		var i;
		var j;

		for (i = 0; i < path.length; i++) {
			var node = path[i];

			if (!node || !node.classList) {
				continue;
			}

			for (j = 0; j < node.classList.length; j++) {
				if (node.classList[j].indexOf('eapp-click-to-call') !== -1) {
					return node.classList[j];
				}
			}
		}

		return 'elfsight_widget_click';
	}

	function trackElfsightClick(event) {
		var ts = event.timeStamp || Date.now();

		if (trackElfsightClick.lastTs && ts - trackElfsightClick.lastTs < 250) {
			return;
		}

		trackElfsightClick.lastTs = ts;

		sendEvent(
			buildPayload(
				'elfsight_call',
				'',
				'Elfsight Call Us',
				'elfsight_widget',
				getElfsightClickValue(event)
			)
		);
	}

	function wireShadowRoot(host) {
		if (!host || !host.shadowRoot || host.dataset.adctShadowWired) {
			return;
		}

		host.dataset.adctShadowWired = '1';
		host.shadowRoot.addEventListener(
			'click',
			function (event) {
				trackElfsightClick(event);
			},
			true
		);
	}

	function wireElfsightHosts() {
		var hosts = document.querySelectorAll('[class*="elfsight-app-"]');
		var i;

		for (i = 0; i < hosts.length; i++) {
			wireShadowRoot(hosts[i]);

			if (hosts[i].dataset.adctHostWired) {
				continue;
			}

			hosts[i].dataset.adctHostWired = '1';
			hosts[i].addEventListener(
				'click',
				function (event) {
					trackElfsightClick(event);
				},
				true
			);
		}
	}

	if (typeof MutationObserver !== 'undefined') {
		var elfsightObserver = new MutationObserver(wireElfsightHosts);
		elfsightObserver.observe(document.documentElement, {
			childList: true,
			subtree: true,
		});
	}

	wireElfsightHosts();
	window.setInterval(wireElfsightHosts, 2000);

	document.addEventListener(
		'click',
		function (event) {
			var tracked = event.target.closest('[data-track="contact"]');

			if (tracked) {
				var data = readData(tracked);
				if (!data.contactType) {
					return;
				}

				sendEvent(
					buildPayload(
						data.contactType,
						data.agentId,
						data.agentName,
						data.source,
						tracked.getAttribute('href') || ''
					)
				);
				return;
			}

			var footerTel = event.target.closest('a[href^="tel:"]');
			if (footerTel && isFooterTelLink(footerTel)) {
				trackFooterTelClick(footerTel);
				return;
			}

			if (!isElfsightClick(event)) {
				return;
			}

			trackElfsightClick(event);
		},
		true
	);
})();
