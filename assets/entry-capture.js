(function (window) {
	'use strict';

	var COOKIE_NAME = 'adct_attribution';
	var COOKIE_DAYS = 30;
	var SESSION_KEY = 'adct_session_started';
	var SESSION_ID_KEY = 'adct_session_id';

	function createSessionId() {
		if (window.crypto && window.crypto.randomUUID) {
			return window.crypto.randomUUID();
		}

		return 'adct-' + Date.now() + '-' + Math.random().toString(16).slice(2);
	}

	function getSessionId() {
		var sessionId = sessionStorage.getItem(SESSION_ID_KEY);

		if (!sessionId) {
			sessionId = createSessionId();
			sessionStorage.setItem(SESSION_ID_KEY, sessionId);
		}

		return sessionId;
	}

	function parseQuery(search) {
		var params = {};
		var query = search ? search.substring(1) : '';

		if (!query) {
			return params;
		}

		query.split('&').forEach(function (pair) {
			if (!pair) {
				return;
			}

			var parts = pair.split('=');
			var key = decodeURIComponent(parts[0] || '');
			var value = decodeURIComponent((parts[1] || '').replace(/\+/g, ' '));

			if (key) {
				params[key] = value;
			}
		});

		return params;
	}

	function getReferrerDomain() {
		if (!document.referrer) {
			return '';
		}

		try {
			return new URL(document.referrer).hostname.replace(/^www\./i, '');
		} catch (error) {
			return '';
		}
	}

	function hasCampaignParams(params) {
		return !!(
			params.utm_source ||
			params.utm_medium ||
			params.utm_campaign ||
			params.gclid
		);
	}

	function deriveEntrySource(params, referrerDomain) {
		if (params.gclid || (params.utm_source === 'google' && params.utm_medium === 'cpc')) {
			return 'google_cpc';
		}

		if (params.utm_source && params.utm_medium) {
			return String(params.utm_source + '_' + params.utm_medium).toLowerCase();
		}

		if (params.utm_source) {
			return String(params.utm_source).toLowerCase();
		}

		var ref = (referrerDomain || '').toLowerCase();

		if (!ref) {
			return 'direct';
		}

		if (ref.indexOf('google.') !== -1) {
			return 'google_organic';
		}

		if (ref.indexOf('youtube.') !== -1 || ref.indexOf('youtu.be') !== -1) {
			return 'youtube';
		}

		if (ref.indexOf('facebook.') !== -1 || ref.indexOf('fb.') !== -1) {
			return 'facebook';
		}

		if (ref.indexOf('instagram.') !== -1) {
			return 'instagram';
		}

		return 'referral';
	}

	function readCookie() {
		var pattern = new RegExp('(?:^|; )' + COOKIE_NAME.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)');
		var match = document.cookie.match(pattern);

		if (!match) {
			return null;
		}

		try {
			return JSON.parse(decodeURIComponent(match[1]));
		} catch (error) {
			return null;
		}
	}

	function writeCookie(data) {
		var expires = new Date();
		expires.setTime(expires.getTime() + COOKIE_DAYS * 86400000);
		document.cookie =
			COOKIE_NAME +
			'=' +
			encodeURIComponent(JSON.stringify(data)) +
			';expires=' +
			expires.toUTCString() +
			';path=/;SameSite=Lax';
	}

	function buildAttributionRecord(params, referrerDomain) {
		return {
			landing_url: window.location.href,
			landing_path: window.location.pathname || '/',
			landing_referrer: referrerDomain,
			utm_source: params.utm_source || '',
			utm_medium: params.utm_medium || '',
			utm_campaign: params.utm_campaign || '',
			utm_id: params.utm_id || '',
			utm_term: params.utm_term || '',
			utm_content: params.utm_content || '',
			gclid: params.gclid || '',
			entry_source: deriveEntrySource(params, referrerDomain),
			attributed_at: Date.now(),
		};
	}

	function captureEntry() {
		var params = parseQuery(window.location.search);
		var existing = readCookie();
		var referrerDomain = getReferrerDomain();

		if (!existing) {
			writeCookie(buildAttributionRecord(params, referrerDomain));
		} else if (hasCampaignParams(params)) {
			// First-touch wins: keep existing cookie for 30 days.
		}

		if (!sessionStorage.getItem(SESSION_KEY)) {
			sessionStorage.setItem(SESSION_KEY, String(Date.now()));
		}

		getSessionId();
	}

	function getSessionDurationSeconds() {
		var started = parseInt(sessionStorage.getItem(SESSION_KEY) || '0', 10);

		if (!started) {
			return 0;
		}

		return Math.max(0, Math.round((Date.now() - started) / 1000));
	}

	window.adctGetAttribution = function () {
		var cookie = readCookie() || {};

		return {
			landing_url: cookie.landing_url || '',
			landing_path: cookie.landing_path || '',
			landing_referrer: cookie.landing_referrer || '',
			entry_source: cookie.entry_source || '',
			utm_source: cookie.utm_source || '',
			utm_medium: cookie.utm_medium || '',
			utm_campaign: cookie.utm_campaign || '',
			utm_id: cookie.utm_id || '',
			utm_term: cookie.utm_term || '',
			utm_content: cookie.utm_content || '',
			gclid: cookie.gclid || '',
			duration_seconds: getSessionDurationSeconds(),
			session_id: getSessionId(),
		};
	};

	captureEntry();
})(window);
