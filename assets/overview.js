(function () {
	'use strict';

	var config = window.adctOverviewConfig || null;

	if (!config || typeof Chart === 'undefined') {
		return;
	}

	var charts = {
		summary: null,
		sources: null,
		campaigns: null,
		trend: null,
	};

	function destroyChart(key) {
		if (charts[key]) {
			charts[key].destroy();
			charts[key] = null;
		}
	}

	function donutOptions() {
		return {
			responsive: true,
			maintainAspectRatio: false,
			cutout: '68%',
			plugins: {
				legend: {
					display: false,
				},
				tooltip: {
					callbacks: {
						label: function (context) {
							var value = context.parsed || 0;
							var total = context.dataset.data.reduce(function (sum, item) {
								return sum + item;
							}, 0);
							var percent = total ? ((value / total) * 100).toFixed(1) : 0;
							return context.label + ': ' + value + ' (' + percent + '%)';
						},
					},
				},
			},
		};
	}

	function renderDonut(key, canvasId, labels, counts, colors) {
		var canvas = document.getElementById(canvasId);

		if (!canvas) {
			return;
		}

		destroyChart(key);

		if (!counts || !counts.length) {
			return;
		}

		charts[key] = new Chart(canvas, {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [
					{
						data: counts,
						backgroundColor: colors,
						borderWidth: 2,
						borderColor: '#ffffff',
						hoverOffset: 6,
					},
				],
			},
			options: donutOptions(),
		});
	}

	function renderSummaryDonut() {
		renderDonut(
			'summary',
			'adct-summary-chart',
			config.contactLabels,
			config.contactCounts,
			config.contactColors
		);
	}

	function renderSourcesDonut() {
		renderDonut(
			'sources',
			'adct-sources-chart',
			config.sourceLabels,
			config.sourceCounts,
			config.sourceColors
		);
	}

	function renderCampaignsDonut() {
		renderDonut(
			'campaigns',
			'adct-campaigns-chart',
			config.campaignLabels,
			config.campaignCounts,
			config.campaignColors
		);
	}

	function renderTrendChart(metric) {
		var canvas = document.getElementById('adct-trend-chart');

		if (!canvas) {
			return;
		}

		destroyChart('trend');

		var isSessions = metric === 'sessions';
		var values = isSessions ? config.sessionSeries : config.clickSeries;
		var color = isSessions ? '#c9a227' : '#4285f4';
		var label = isSessions ? 'Sessions' : 'Contact clicks';

		charts.trend = new Chart(canvas, {
			type: 'line',
			data: {
				labels: config.dayLabels,
				datasets: [
					{
						label: label,
						data: values,
						borderColor: color,
						backgroundColor: isSessions ? 'rgba(201, 162, 39, 0.18)' : 'rgba(66, 133, 244, 0.18)',
						fill: true,
						tension: 0.35,
						pointRadius: 2,
						pointHoverRadius: 4,
						borderWidth: 2,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					mode: 'index',
					intersect: false,
				},
				plugins: {
					legend: {
						display: false,
					},
					tooltip: {
						callbacks: {
							label: function (context) {
								return context.dataset.label + ': ' + context.parsed.y;
							},
						},
					},
				},
				scales: {
					x: {
						grid: {
							display: false,
						},
						ticks: {
							maxTicksLimit: 8,
							color: '#646970',
						},
					},
					y: {
						beginAtZero: true,
						grid: {
							color: 'rgba(0,0,0,0.06)',
						},
						ticks: {
							precision: 0,
							color: '#646970',
						},
					},
				},
			},
		});
	}

	function setActiveMetric(metric) {
		var buttons = document.querySelectorAll('[data-adct-metric]');

		buttons.forEach(function (button) {
			button.classList.toggle('is-active', button.getAttribute('data-adct-metric') === metric);
		});

		renderTrendChart(metric);
	}

	function setActivePanel(panel) {
		var tabs = document.querySelectorAll('[data-adct-panel-tab]');
		var panels = document.querySelectorAll('[data-adct-panel]');

		tabs.forEach(function (tab) {
			tab.classList.toggle('is-active', tab.getAttribute('data-adct-panel-tab') === panel);
		});

		panels.forEach(function (item) {
			item.classList.toggle('is-active', item.getAttribute('data-adct-panel') === panel);
		});

		if (panel === 'marketing') {
			renderSourcesDonut();
			renderCampaignsDonut();
		}
	}

	document.querySelectorAll('[data-adct-metric]').forEach(function (button) {
		button.addEventListener('click', function () {
			setActiveMetric(button.getAttribute('data-adct-metric'));
		});
	});

	document.querySelectorAll('[data-adct-panel-tab]').forEach(function (tab) {
		tab.addEventListener('click', function () {
			setActivePanel(tab.getAttribute('data-adct-panel-tab'));
		});
	});

	renderSummaryDonut();
	setActiveMetric('clicks');
})();
