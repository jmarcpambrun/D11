(function (Drupal, drupalSettings) {
  'use strict';

  let chart = null;
  let currentMetric = 'unique';

  function formatNumber(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function buildChart(ctx, labels, dataCur, dataPrev, labelCurrent, labelPrev) {
    const datasets = [
      {
        label: labelCurrent,
        data: dataCur,
        fill: false,
        tension: 0.2,
        borderWidth: 2,
        pointRadius: 3,
        backgroundColor: '#000',
        borderColor: '#000',
      },
    ];
    if (dataPrev) {
      datasets.push({
        label: labelPrev,
        data: dataPrev,
        fill: false,
        tension: 0.2,
        borderDash: [6, 4],
        borderWidth: 2,
        pointRadius: 2,
        backgroundColor: '#000',
        borderColor: '#000',
      });
    }

    const cfg = {
      type: 'line',
      data: {
        labels: labels,
        datasets: datasets,
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: { mode: 'index', intersect: false },
        },
        scales: {
          x: { display: true, title: { display: false } },
        },
        aspectRatio: 4,
      },
    };

    if (chart) {
      chart.destroy();
    }
    chart = new Chart(ctx, cfg);
  }

  function updateNotification(data) {
    const noteEl = document.getElementById('counter-notification');
    if (!noteEl) {
      return;
    }
    const percent = currentMetric === 'views' ? data.percent_views : data.percent_unique;
    const direction = currentMetric === 'views' ? data.direction_views : data.direction_unique;

    if (percent === null || percent === undefined) {
      noteEl.textContent = '';
      return;
    }

    const dirText = direction === 'up'
      ? Drupal.t('increased', {}, { context: 'counter' })
      : Drupal.t('decreased', {}, { context: 'counter' });

    const metricLabel = currentMetric === 'views'
      ? Drupal.t('Views', {}, { context: 'counter' })
      : Drupal.t('Visitors', {}, { context: 'counter' });

    noteEl.innerHTML =
      '<div class="compared-text ' + direction + '">' +
      metricLabel + ' ' + dirText + ' <span>' + percent + '%</span> ' +
      Drupal.t('compared to the previous period.', {}, { context: 'counter' }) +
      '</div>';
  }

  function getCustomDates() {
    const startInput = document.getElementById('counter-custom-start');
    const endInput = document.getElementById('counter-custom-end');
    if (!startInput || !endInput) {
      return [null, null];
    }
    return [startInput.value || null, endInput.value || null];
  }

  function refresh(range, compare) {
    const url = new URL(drupalSettings.counter.dataUrl, window.location.origin);
    url.searchParams.set('range', range);
    url.searchParams.set('compare', compare ? '1' : '0');

    if (range === 'custom') {
      const [start, end] = getCustomDates();
      if (start) {
        url.searchParams.set('start', start);
      }
      if (end) {
        url.searchParams.set('end', end);
      }
    }

    fetch(url.toString(), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        const viewsEl = document.getElementById('counter-views');
        const uniqueEl = document.getElementById('counter-unique');
        if (viewsEl) {
          viewsEl.textContent = formatNumber(data.total_views_current || 0);
        }
        if (uniqueEl) {
          uniqueEl.textContent = formatNumber(data.total_unique_current || 0);
        }

        updateNotification(data);

        let curSeries = currentMetric === 'views' ? data.series_views : data.series_unique;
        let prevSeries = currentMetric === 'views' ? data.series_views_prev : data.series_unique_prev;

        const ctxEl = document.getElementById('counter-chart');
        if (!ctxEl) {
          return;
        }

        const labelCurrent = currentMetric === 'views'
          ? Drupal.t('Current period', {}, { context: 'counter' })
          : Drupal.t('Current period', {}, { context: 'counter' });

        const labelPrev = currentMetric === 'views'
          ? Drupal.t('Previous period', {}, { context: 'counter' })
          : Drupal.t('Previous period', {}, { context: 'counter' });

        buildChart(ctxEl.getContext('2d'), data.labels || [], curSeries || [], prevSeries || null, labelCurrent, labelPrev);
      })
      .catch(err => {
        console.error(Drupal.t('Counter stats fetch error', {}, { context: 'counter' }), err);
      });
  }

  Drupal.behaviors.counterStats = {
    attach: function (context) {
      const rangeSelect = document.getElementById('counter-range-select');
      const compareCheckbox = document.getElementById('counter-compare-checkbox');
      const customWrap = document.getElementById('counter-custom-dates');
      const startInput = document.getElementById('counter-custom-start');
      const endInput = document.getElementById('counter-custom-end');

      const metricViewsEl = document.getElementById('metric-views');
      const metricUniqueEl = document.getElementById('metric-unique');

      function setActiveMetric(metric) {
        currentMetric = metric;
        if (metric === 'views') {
          metricViewsEl.classList.add('is-active');
          metricUniqueEl.classList.remove('is-active');
        } else {
          metricUniqueEl.classList.add('is-active');
          metricViewsEl.classList.remove('is-active');
        }
      }

      function onRangeChange() {
        const val = rangeSelect.value;
        if (val === 'custom') {
          customWrap.style.display = 'inline-block';
        } else {
          customWrap.style.display = 'none';
        }
        refresh(val, compareCheckbox.checked);
      }

      if (rangeSelect && !rangeSelect._counterAttached) {
        rangeSelect.addEventListener('change', onRangeChange);
        rangeSelect._counterAttached = true;
      }

      if (compareCheckbox && !compareCheckbox._counterAttached) {
        compareCheckbox.addEventListener('change', function () {
          refresh(rangeSelect.value, compareCheckbox.checked);
        });
        compareCheckbox._counterAttached = true;
      }

      if (startInput && !startInput._counterAttached) {
        startInput.addEventListener('change', function () {
          if (rangeSelect.value === 'custom') {
            refresh(rangeSelect.value, compareCheckbox.checked);
          }
        });
        startInput._counterAttached = true;
      }
      if (endInput && !endInput._counterAttached) {
        endInput.addEventListener('change', function () {
          if (rangeSelect.value === 'custom') {
            refresh(rangeSelect.value, compareCheckbox.checked);
          }
        });
        endInput._counterAttached = true;
      }

      if (metricViewsEl && !metricViewsEl._counterAttached) {
        metricViewsEl.addEventListener('click', function () {
          setActiveMetric('views');
          refresh(rangeSelect.value, compareCheckbox.checked);
        });
        metricViewsEl.addEventListener('keypress', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            setActiveMetric('views');
            refresh(rangeSelect.value, compareCheckbox.checked);
            e.preventDefault();
          }
        });
        metricViewsEl._counterAttached = true;
      }
      if (metricUniqueEl && !metricUniqueEl._counterAttached) {
        metricUniqueEl.addEventListener('click', function () {
          setActiveMetric('unique');
          refresh(rangeSelect.value, compareCheckbox.checked);
        });
        metricUniqueEl.addEventListener('keypress', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            setActiveMetric('unique');
            refresh(rangeSelect.value, compareCheckbox.checked);
            e.preventDefault();
          }
        });
        metricUniqueEl._counterAttached = true;
      }

      if (!Drupal.behaviors.counterStats._inited) {
        Drupal.behaviors.counterStats._inited = true;
        const initialRange = drupalSettings.counter && drupalSettings.counter.defaultRange ? drupalSettings.counter.defaultRange : '7days';
        if (rangeSelect) {
          rangeSelect.value = initialRange;
        }
        if (initialRange === 'custom' && customWrap) {
          customWrap.style.display = 'inline-block';
        }
        setActiveMetric('unique');
        refresh(initialRange, compareCheckbox ? compareCheckbox.checked : false);
      }
    },
  };

})(Drupal, drupalSettings);
