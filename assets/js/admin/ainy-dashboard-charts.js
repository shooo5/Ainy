/**
 * Ainy ダッシュボード折れ線グラフ（KPI切替・ページ利用）
 */
(function () {
  'use strict';

  var payload = window.ainyDashboardCharts;
  if (!payload || !payload.metrics) {
    return;
  }

  if (typeof Chart === 'undefined') {
    return;
  }

  var metrics = payload.metrics;
  var defaultMetric = payload.defaultMetric || 'users_total';
  var pageUsage = payload.pageUsage || { pages: [], default_page_key: '' };

  function formatLabels(dates) {
    return (dates || []).map(function (d) {
      var parts = String(d).split('-');
      if (parts.length === 3) {
        return parts[1] + '/' + parts[2];
      }
      return d;
    });
  }

  function chartColor(metricId) {
    var colors = {
      users_total: 'rgb(140, 26, 246)',
      teams_total: 'rgb(59, 130, 246)',
      matches_established: 'rgb(34, 197, 94)',
      pending_matches: 'rgb(245, 158, 11)',
    };
    return colors[metricId] || 'rgb(140, 26, 246)';
  }

  function hexToRgba(rgb, alpha) {
    var m = rgb.match(/\d+/g);
    if (!m || m.length < 3) {
      return 'rgba(140, 26, 246, ' + alpha + ')';
    }
    return 'rgba(' + m[0] + ',' + m[1] + ',' + m[2] + ',' + alpha + ')';
  }

  var kpiCanvas = document.getElementById('ainy-dashboard-trend-chart');
  var kpiTitle = document.getElementById('ainy-dashboard-chart-title');
  var kpiNote = document.getElementById('ainy-dashboard-chart-note');
  var csvLink = document.getElementById('ainy-dashboard-csv-link');
  var kpiChart = null;

  function updateKpiCardActive(metricId) {
    document.querySelectorAll('[data-chart-metric]').forEach(function (el) {
      var active = el.getAttribute('data-chart-metric') === metricId;
      el.classList.toggle('is-chart-active', active);
      el.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function renderKpiChart(metricId) {
    var data = metrics[metricId] || metrics[defaultMetric];
    if (!data || !kpiCanvas) {
      return;
    }

    var color = chartColor(metricId);
    if (kpiTitle) {
      kpiTitle.textContent = '推移（' + (data.label || '') + '）';
    }
    if (kpiNote) {
      kpiNote.textContent = data.beta
        ? '指標定義は暫定（β）です。集計開始日以降のデータが反映されます。'
        : '集計開始日以降の日次データを表示します。カードをクリックすると指標を切り替えられます。';
    }
    if (csvLink && csvLink.dataset.baseUrl) {
      var sep = csvLink.dataset.baseUrl.indexOf('?') >= 0 ? '&' : '?';
      csvLink.href =
        csvLink.dataset.baseUrl + sep + 'metric=' + encodeURIComponent(metricId);
    }

    updateKpiCardActive(metricId);

    if (kpiChart) {
      kpiChart.destroy();
    }

    kpiChart = new Chart(kpiCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: formatLabels(data.labels),
        datasets: [
          {
            label: data.label,
            data: data.values || [],
            borderColor: color,
            backgroundColor: hexToRgba(color, 0.08),
            tension: 0.25,
            fill: true,
            pointRadius: 3,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 },
          },
        },
      },
    });
  }

  document.querySelectorAll('[data-chart-metric]').forEach(function (card) {
    card.addEventListener('click', function () {
      var metricId = card.getAttribute('data-chart-metric');
      if (metricId && metrics[metricId]) {
        renderKpiChart(metricId);
      }
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        card.click();
      }
    });
  });

  renderKpiChart(defaultMetric);

  /* ページ利用状況 */
  var pageCanvas = document.getElementById('ainy-dashboard-page-chart');
  var pageTitle = document.getElementById('ainy-dashboard-page-chart-title');
  var pageChart = null;
  var pageMetric = 'views';

  function findPage(pageKey) {
    var list = pageUsage.pages || [];
    for (var i = 0; i < list.length; i++) {
      if (list[i].page_key === pageKey) {
        return list[i];
      }
    }
    return list[0] || null;
  }

  function updatePageCardActive(pageKey) {
    document.querySelectorAll('[data-page-key]').forEach(function (el) {
      var active = el.getAttribute('data-page-key') === pageKey;
      el.classList.toggle('is-chart-active', active);
      el.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function renderPageChart(pageKey, metric) {
    var page = findPage(pageKey);
    if (!page || !pageCanvas) {
      return;
    }
    pageMetric = metric || pageMetric;

    var series = page.series || {};
    var values = pageMetric === 'avg_sec' ? series.avg_sec : series.views;
    var label = pageMetric === 'avg_sec' ? '平均滞在（秒）' : 'PV';
    var color = pageMetric === 'avg_sec' ? 'rgb(236, 72, 153)' : 'rgb(14, 165, 233)';

    if (pageTitle) {
      pageTitle.textContent =
        'ページ推移（' + page.label + ' · ' + label + '）';
    }

    updatePageCardActive(page.page_key);

    document.querySelectorAll('[data-page-metric]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-page-metric') === pageMetric);
    });

    if (pageChart) {
      pageChart.destroy();
    }

    pageChart = new Chart(pageCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: formatLabels(series.labels),
        datasets: [
          {
            label: label,
            data: values || [],
            borderColor: color,
            backgroundColor: hexToRgba(color, 0.08),
            tension: 0.25,
            fill: true,
            pointRadius: 3,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 },
          },
        },
      },
    });
  }

  document.querySelectorAll('[data-page-key]').forEach(function (card) {
    card.addEventListener('click', function () {
      renderPageChart(card.getAttribute('data-page-key'), pageMetric);
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        card.click();
      }
    });
  });

  document.querySelectorAll('[data-page-metric]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var activeKey = document.querySelector('[data-page-key].is-chart-active');
      var key = activeKey
        ? activeKey.getAttribute('data-page-key')
        : pageUsage.default_page_key;
      renderPageChart(key, btn.getAttribute('data-page-metric'));
    });
  });

  if (pageUsage.default_page_key && pageUsage.pages && pageUsage.pages.length) {
    renderPageChart(pageUsage.default_page_key, 'views');
  }

  window.ainyDashboardKpiChart = { render: renderKpiChart };
  window.ainyDashboardPageChart = { render: renderPageChart };
})();
