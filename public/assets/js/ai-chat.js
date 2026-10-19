(() => {
  const formatMoney = (val) => {
    if (val === null || val === undefined || val === '') return '—';
    const num = Number(val || 0);
    if (!Number.isFinite(num)) return String(val ?? '');
    const prefix = num < 0 ? '-Rp' : 'Rp';
    return prefix + Math.abs(Math.round(num)).toLocaleString('id-ID');
  };

  const formatNumber = (val) => {
    if (val === null || val === undefined || val === '') return '—';
    const num = Number(val || 0);
    return Number.isFinite(num) ? num.toLocaleString('id-ID') : String(val ?? '');
  };

  const escapeHtml = (str) =>
    String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const renderMarkdownText = (text) => {
    const lines = String(text ?? '').split(/\r?\n/);
    const htmlParts = [];
    let listItems = [];

    const flushList = () => {
      if (!listItems.length) return;
      htmlParts.push(`<ul>${listItems.map((item) => `<li>${item}</li>`).join('')}</ul>`);
      listItems = [];
    };

    const inlineFormat = (raw) =>
      escapeHtml(raw)
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');

    for (const line of lines) {
      const trimmed = line.trim();
      if (!trimmed) {
        flushList();
        continue;
      }
      if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
        listItems.push(inlineFormat(trimmed.slice(2)));
      } else {
        flushList();
        htmlParts.push(`<p>${inlineFormat(trimmed)}</p>`);
      }
    }
    flushList();
    return htmlParts.join('');
  };

  const chartMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const disposeCharts = (root) => root?.querySelectorAll('.ai-block-chart').forEach((chart) => chart._aiChartDispose?.());
  const chartLabel = (label, multipleYears) => {
    const date = /^(\d{4})-(\d{2})(?:-(\d{2}))?$/.exec(String(label));
    if (date && Number(date[2]) >= 1 && Number(date[2]) <= 12) {
      return `${date[3] ? `${Number(date[3])} ` : ''}${chartMonths[Number(date[2]) - 1]}${multipleYears ? ` ${date[1].slice(-2)}` : ''}`;
    }
    return String(label).length > 12 ? `${String(label).slice(0, 11)}…` : String(label);
  };

  const openChartModal = (block, mode, opener) => {
    const modal = document.getElementById('aiChartModal');
    if (!modal || !window.bootstrap?.Modal) return;
    const content = modal.querySelector('#aiChartModalContent');
    modal.querySelector('#aiChartModalTitle').textContent = block.title || 'Grafik analisis';
    disposeCharts(content);
    content.replaceChildren(renderChartBlock(block, 0, { expanded: true, initialMode: mode }));
    modal._aiChartOpener = opener;
    if (!modal._aiChartBound) {
      modal._aiChartBound = true;
      const fullscreen = modal.querySelector('#aiChartFullscreen');
      fullscreen.addEventListener('click', () => {
        const active = modal.querySelector('.modal-dialog').classList.toggle('modal-fullscreen');
        fullscreen.setAttribute('aria-pressed', String(active));
        fullscreen.textContent = active ? 'Ukuran biasa' : 'Layar penuh';
      });
      modal.addEventListener('shown.bs.modal', () => content.querySelector('svg')?.focus());
      modal.addEventListener('hidden.bs.modal', () => {
        disposeCharts(content);
        content.replaceChildren();
        modal.querySelector('.modal-dialog').classList.remove('modal-fullscreen');
        fullscreen.setAttribute('aria-pressed', 'false');
        fullscreen.textContent = 'Layar penuh';
        if (modal._aiChartOpener?.isConnected) modal._aiChartOpener.focus();
        modal._aiChartOpener = null;
      });
    }
    window.bootstrap.Modal.getOrCreateInstance(modal, { backdrop: 'static', keyboard: true }).show();
  };

  const renderChartBlock = (block, blockIndex, { expanded = false, initialMode } = {}) => {
    const wrapper = document.createElement('section');
    wrapper.className = 'ai-block-chart';
    const chartType = initialMode || block.chart_type || 'bar';
    let currentMode = chartType;
    const labels = Array.isArray(block.labels) ? block.labels : [];
    const series = Array.isArray(block.series) ? block.series.map((s) => ({ ...s, data: Array.isArray(s.data) ? s.data.slice(0, labels.length) : [] })) : [];

    const header = document.createElement('div');
    header.className = 'ai-block-toolbar';
    header.innerHTML = `
      ${expanded ? '' : `<div class="ai-chart-heading">
        <h4>${escapeHtml(block.title || 'Visualisasi Grafik')}</h4>
        <small>${escapeHtml(block.subtitle || '')}</small>
      </div>`}
      <div class="btn-group btn-group-sm" role="group" aria-label="Pilih tipe grafik">
        <button type="button" class="btn btn-soft ${chartType === 'bar' ? 'active' : ''}" data-chart-switch="bar" aria-pressed="${chartType === 'bar'}">Batang</button>
        <button type="button" class="btn btn-soft ${chartType === 'line' ? 'active' : ''}" data-chart-switch="line" aria-pressed="${chartType === 'line'}">Garis</button>
      </div>
    `;
    if (!expanded) {
      const enlarge = document.createElement('button');
      enlarge.type = 'button';
      enlarge.className = 'btn btn-soft btn-sm ai-chart-expand';
      enlarge.textContent = 'Perbesar';
      enlarge.setAttribute('aria-haspopup', 'dialog');
      enlarge.setAttribute('aria-controls', 'aiChartModal');
      enlarge.disabled = !labels.length || !series.some((s) => s.data.some((value) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))));
      enlarge.addEventListener('click', () => openChartModal(block, currentMode, enlarge));
      header.appendChild(enlarge);
    }

    const canvas = document.createElement('div');
    canvas.className = 'ai-chart-canvas';
    canvas.dataset.chartIndex = String(blockIndex);
    const readout = document.createElement('div');
    readout.className = 'ai-chart-readout';
    readout.setAttribute('role', 'status');
    readout.setAttribute('aria-live', 'polite');
    const validValue = (value) => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
    const valueText = (s, value) => !validValue(value) ? 'Data tidak tersedia'
      : /omzet|penjualan|belanja|laba|harga|nilai|saldo|biaya|piutang|nominal|hpp|retur/i.test(s.name || '') ? formatMoney(value) : formatNumber(value);
    const showValue = (index) => {
      readout.textContent = `${labels[index]} — ${series.map((s) => `${s.name || 'Nilai'}: ${valueText(s, s.data?.[index])}`).join(' · ')}`;
    };

    const legend = document.createElement('div');
    legend.className = 'ai-chart-legend';
    const colors = ['var(--chart-primary, #176b87)', 'var(--chart-secondary, #13a777)', 'var(--chart-tertiary, #e6a436)'];

    series.forEach((s, idx) => {
      const item = document.createElement('span');
      item.innerHTML = `<i style="background:${colors[idx % colors.length]}"></i>${escapeHtml(s.name || `Seri ${idx + 1}`)}`;
      legend.appendChild(item);
    });

    const drawSvg = (mode) => {
      currentMode = mode;
      canvas.replaceChildren();
      readout.textContent = labels.length && series.length ? 'Sentuh atau arahkan ke titik untuk detail. Gunakan ← → saat grafik terfokus.' : '';
      if (!labels.length || !series.length) {
        canvas.textContent = 'Data grafik belum tersedia.';
        return;
      }

      const allVals = series.flatMap((s) => (s.data || []).filter(validValue).map(Number));
      const finiteVals = allVals.filter(Number.isFinite);
      if (!finiteVals.length) { canvas.textContent = 'Data grafik belum tersedia.'; readout.textContent = ''; return; }
      const minVal = Math.min(0, ...finiteVals);
      const maxVal = Math.max(0, ...finiteVals) || (minVal === 0 ? 1 : 0);
      const range = maxVal - minVal || 1;
      // Match the physical drawing area, so SVG text never scales with a wide chat card.
      const width = Math.max(220, (canvas.clientWidth || 656) - 18);
      const height = Math.max(150, (canvas.clientHeight || 258) - 18);
      const padLeft = 60;
      const padRight = 22;
      const padTop = 20;
      const padBottom = 42;
      const plotW = width - padLeft - padRight;
      const plotH = height - padTop - padBottom;
      const valueY = (value) => padTop + ((maxVal - (Number.isFinite(Number(value)) ? Number(value) : 0)) / range) * plotH;
      const zeroY = valueY(0);
      const groupStep = plotW / Math.max(1, labels.length);

      const svgNS = 'http://www.w3.org/2000/svg';
      const svg = document.createElementNS(svgNS, 'svg');
      svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
      svg.setAttribute('class', 'ai-svg-chart');
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', block.title || 'Grafik data analisis POS');
      svg.setAttribute('tabindex', '0');
      let activeIndex = -1;
      svg.addEventListener('pointerdown', (event) => {
        const bounds = svg.getBoundingClientRect();
        if (!bounds.width) return;
        const x = (event.clientX - bounds.left) * width / bounds.width;
        activeIndex = Math.max(0, Math.min(labels.length - 1, Math.floor((x - padLeft) / groupStep)));
        showValue(activeIndex);
      });
      svg.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        activeIndex = event.key === 'Home' ? 0 : event.key === 'End' ? labels.length - 1
          : Math.max(0, Math.min(labels.length - 1, activeIndex + (event.key === 'ArrowLeft' ? -1 : 1)));
        showValue(activeIndex);
      });
      const addPointDetail = (point, index, s, value) => {
        const title = document.createElementNS(svgNS, 'title');
        title.textContent = `${labels[index]} (${s.name || 'Nilai'}): ${valueText(s, value)}`;
        point.appendChild(title);
        point.addEventListener('pointerenter', () => showValue(index));
        point.addEventListener('click', () => { activeIndex = index; showValue(index); svg.focus(); });
      };

      for (let step = 0; step <= 4; step++) {
        const ratio = step / 4;
        const y = padTop + plotH - ratio * plotH;
        const val = Math.round(minVal + range * ratio);

        const line = document.createElementNS(svgNS, 'line');
        line.setAttribute('x1', String(padLeft));
        line.setAttribute('y1', String(y));
        line.setAttribute('x2', String(width - padRight));
        line.setAttribute('y2', String(y));
        line.setAttribute('stroke', 'var(--line, #dde4ec)');
        line.setAttribute('stroke-width', '1');
        svg.appendChild(line);

        const text = document.createElementNS(svgNS, 'text');
        text.setAttribute('x', String(padLeft - 8));
        text.setAttribute('y', String(y + 4));
        text.setAttribute('text-anchor', 'end');
        text.setAttribute('fill', 'var(--muted, #68768a)');
        text.setAttribute('font-size', '11');
        text.textContent = Math.abs(val) >= 1_000_000_000 ? `${(val / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })}M`
          : Math.abs(val) >= 1_000_000 ? `${(val / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })}jt`
          : Math.abs(val) >= 10_000 ? `${(val / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })}rb` : val.toLocaleString('id-ID');
        svg.appendChild(text);
      }

      if (mode === 'line') {
        series.forEach((s, sIdx) => {
          let pts = [];
          const drawSegment = () => {
            if (pts.length > 1) {
              const poly = document.createElementNS(svgNS, 'polyline');
              poly.setAttribute('fill', 'none');
              poly.setAttribute('stroke', colors[sIdx % colors.length]);
              poly.setAttribute('stroke-width', '2');
              poly.setAttribute('points', pts.join(' '));
              svg.appendChild(poly);
            }
            pts = [];
          };
          (s.data || []).forEach((v, i) => {
            if (!validValue(v)) { drawSegment(); return; }
            const x = padLeft + i * groupStep + groupStep / 2;
            const y = valueY(v);
            pts.push(`${x.toFixed(1)},${y.toFixed(1)}`);
          });
          drawSegment();
          (s.data || []).forEach((v, i) => {
            if (!validValue(v)) return;
            const x = padLeft + i * groupStep + groupStep / 2;
            const y = valueY(v);
            const circle = document.createElementNS(svgNS, 'circle');
            circle.setAttribute('cx', String(x));
            circle.setAttribute('cy', String(y));
            circle.setAttribute('r', '4');
            circle.setAttribute('fill', colors[sIdx % colors.length]);
            addPointDetail(circle, i, s, v);
            svg.appendChild(circle);
          });
        });
      } else {
        const barCount = Math.max(1, series.length);
        const barWidth = Math.min(28, (groupStep * 0.72) / barCount);

        labels.forEach((lbl, i) => {
          const groupStart = padLeft + i * groupStep + (groupStep - barWidth * barCount) / 2;
          series.forEach((s, sIdx) => {
            if (!validValue(s.data?.[i])) return;
            const val = Number(s.data?.[i] || 0);
            const barH = Math.abs(valueY(val) - zeroY);
            const x = groupStart + sIdx * barWidth;
            const y = Math.min(valueY(val), zeroY);

            const rect = document.createElementNS(svgNS, 'rect');
            rect.setAttribute('x', String(x));
            rect.setAttribute('y', String(y));
            rect.setAttribute('width', String(Math.max(0.5, barWidth - Math.min(2, barWidth * 0.15))));
            rect.setAttribute('height', String(barH));
            rect.setAttribute('rx', '3');
            rect.setAttribute('fill', colors[sIdx % colors.length]);
            addPointDetail(rect, i, s, val);
            svg.appendChild(rect);
          });
        });
      }

      const multipleYears = new Set(labels.map((label) => /^(\d{4})-\d{2}/.exec(String(label))?.[1]).filter(Boolean)).size > 1;
      const maxLabels = Math.max(2, Math.min(expanded ? 16 : 8, Math.floor(plotW / 64)));
      const labelStep = Math.max(1, Math.ceil((labels.length - 1) / (maxLabels - 1)));
      labels.forEach((lbl, i) => {
        if (i !== labels.length - 1 && (i % labelStep !== 0 || (i > 0 && labels.length - 1 - i < labelStep))) return;
        const x = padLeft + i * groupStep + groupStep / 2;
        const text = document.createElementNS(svgNS, 'text');
        text.setAttribute('x', String(x));
        text.setAttribute('y', String(height - 14));
        text.setAttribute('text-anchor', i === 0 ? 'start' : i === labels.length - 1 ? 'end' : 'middle');
        text.setAttribute('fill', 'var(--muted, #68768a)');
        text.setAttribute('font-size', '11');
        text.textContent = chartLabel(lbl, multipleYears);
        svg.appendChild(text);
      });

      canvas.appendChild(svg);
    };

    header.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-chart-switch]');
      if (!btn) return;
      header.querySelectorAll('[data-chart-switch]').forEach((b) => { b.classList.toggle('active', b === btn); b.setAttribute('aria-pressed', String(b === btn)); });
      drawSvg(btn.dataset.chartSwitch);
    });

    drawSvg(chartType);
    wrapper.append(header, canvas, readout, legend);
    if (typeof ResizeObserver !== 'undefined') {
      const observer = new ResizeObserver(() => {
        if (!canvas.isConnected) { observer.disconnect(); return; }
        drawSvg(currentMode);
      });
      observer.observe(canvas);
      wrapper._aiChartDispose = () => observer.disconnect();
    }
    return wrapper;
  };

  const renderTableBlock = (block) => {
    const wrapper = document.createElement('section');
    wrapper.className = 'ai-block-table';
    const columns = Array.isArray(block.columns) ? block.columns : [];
    const allRows = Array.isArray(block.rows) ? [...block.rows] : [];

    let searchTerm = '';
    let sortKey = '';
    let sortDir = 'asc';
    let currentPage = 1;
    const pageSize = 8;

    const toolbar = document.createElement('div');
    toolbar.className = 'ai-table-filter-bar';
    toolbar.innerHTML = `
      <div>
        <h4 class="mb-0">${escapeHtml(block.title || 'Tabel Data Analisis')}</h4>
        <small class="text-muted" data-ai-table-count>${allRows.length} baris data</small>
      </div>
      <div class="input-group input-group-sm ai-table-search">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" class="form-control" placeholder="Filter baris tabel..." aria-label="Filter baris tabel">
      </div>
    `;

    const tableWrap = document.createElement('div');
    tableWrap.className = 'table-responsive';
    tableWrap.tabIndex = 0;

    const table = document.createElement('table');
    table.className = 'table app-table align-middle mb-0';
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');

    columns.forEach((col) => {
      const th = document.createElement('th');
      th.scope = 'col';
      if (['number', 'currency'].includes(col.type)) th.className = 'text-end';
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'kit-sort-button';
      btn.dataset.colKey = col.key;
      btn.innerHTML = `${escapeHtml(col.label)} <span aria-hidden="true">↕</span>`;
      th.appendChild(btn);
      headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    const tbody = document.createElement('tbody');
    table.append(thead, tbody);
    tableWrap.appendChild(table);

    const footer = document.createElement('div');
    footer.className = 'kit-table-footer mt-2 mb-0';
    footer.innerHTML = `
      <small data-ai-page-info>Halaman 1</small>
      <div class="kit-pagination ms-auto">
        <button type="button" class="btn btn-soft btn-sm" data-ai-page="-1">Sebelumnya</button>
        <button type="button" class="btn btn-soft btn-sm" data-ai-page="1">Berikutnya</button>
      </div>
    `;

    const updateView = () => {
      const filtered = allRows.filter((r) =>
        !searchTerm || Object.values(r).join(' ').toLocaleLowerCase('id-ID').includes(searchTerm)
      );

      if (sortKey) {
        filtered.sort((a, b) => {
          const av = a[sortKey];
          const bv = b[sortKey];
          const cmp =
            typeof av === 'number' && typeof bv === 'number'
              ? av - bv
              : String(av ?? '').localeCompare(String(bv ?? ''), 'id-ID', { numeric: true });
          return sortDir === 'desc' ? -cmp : cmp;
        });
      }

      const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
      currentPage = Math.min(Math.max(1, currentPage), totalPages);
      const slice = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize);

      tbody.replaceChildren();
      if (!slice.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = Math.max(1, columns.length);
        td.className = 'text-center text-muted py-3';
        td.textContent = 'Tidak ada baris yang cocok dengan filter.';
        tr.appendChild(td);
        tbody.appendChild(tr);
      } else {
        slice.forEach((row) => {
          const tr = document.createElement('tr');
          columns.forEach((col) => {
            const td = document.createElement('td');
            const rawVal = row[col.key];
            if (col.type === 'currency') {
              td.className = 'text-end numeric';
              td.textContent = formatMoney(rawVal);
            } else if (col.type === 'number') {
              td.className = 'text-end numeric';
              td.textContent = formatNumber(rawVal);
            } else {
              td.textContent = String(rawVal ?? '-');
            }
            tr.appendChild(td);
          });
          tbody.appendChild(tr);
        });
      }

      toolbar.querySelector('[data-ai-table-count]').textContent = `${filtered.length} baris data`;
      footer.querySelector('[data-ai-page-info]').textContent = `Halaman ${currentPage} / ${totalPages}`;
      const [prevBtn, nextBtn] = footer.querySelectorAll('[data-ai-page]');
      prevBtn.disabled = currentPage <= 1;
      nextBtn.disabled = currentPage >= totalPages;
      footer.hidden = filtered.length <= pageSize;
    };

    toolbar.querySelector('input').addEventListener('input', (e) => {
      searchTerm = e.target.value.trim().toLocaleLowerCase('id-ID');
      currentPage = 1;
      updateView();
    });

    thead.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-col-key]');
      if (!btn) return;
      const key = btn.dataset.colKey;
      sortDir = sortKey === key && sortDir === 'asc' ? 'desc' : 'asc';
      sortKey = key;
      thead.querySelectorAll('[data-col-key]').forEach((b) => {
        b.querySelector('span').textContent = b === btn ? (sortDir === 'asc' ? '↑' : '↓') : '↕';
      });
      updateView();
    });

    footer.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-ai-page]');
      if (!btn) return;
      currentPage += Number(btn.dataset.aiPage);
      updateView();
    });

    updateView();
    wrapper.append(toolbar, tableWrap, footer);
    return wrapper;
  };

  const createMessageElement = (msg) => {
    const article = document.createElement('article');
    if (msg.role === 'user') {
      article.className = 'ai-msg ai-msg-user';
      const bubble = document.createElement('div');
      bubble.className = 'ai-msg-bubble';
      bubble.textContent = msg.content || '';
      article.appendChild(bubble);
      return article;
    }

    article.className = 'ai-msg ai-msg-assistant';
    const card = document.createElement('div');
    card.className = 'ai-msg-card';

    const header = document.createElement('header');
    header.className = 'ai-msg-header';
    header.innerHTML = `
      <span class="ai-msg-author"><i class="bi bi-stars" aria-hidden="true"></i><span>POS AI Chat Analyst</span></span>
      <span class="ai-msg-time">${escapeHtml(msg.created_at || 'Baru saja')}</span>
    `;
    card.appendChild(header);

    const emptyResult = msg.metadata?.empty_result === true;
    let blocks = Array.isArray(msg.blocks) && msg.blocks.length
      ? [...msg.blocks]
      : [{ type: 'text', content: msg.content || '' }];
    if (emptyResult) blocks = blocks.filter(b => ['text', 'warning'].includes(b.type));
    if (msg.metadata?.source_mode === 'pos_verification_fixture' && !blocks.some(b => b.type === 'warning' && b.title === 'Data simulasi')) {
      blocks.unshift({type: 'warning', title: 'Data simulasi', content: 'Riwayat ini berasal dari dataset verifikasi, bukan transaksi POS live.'});
    }
    if ((msg.metadata?.query_failure_present || msg.metadata?.queries?.some(q => q.success === false)) && msg.metadata?.analysis_status !== 'error') {
      blocks.unshift({type: 'warning', title: 'Hasil belum lengkap', content: 'Riwayat ini memuat query yang gagal. Angkanya belum dapat dijadikan hasil analisis yang terverifikasi.'});
    }

    const presentation = msg.metadata?.presentation || 'auto';
    const detail = (label, open = false) => {
      const section = document.createElement('details');
      section.className = 'ai-result-detail'; section.open = open;
      const summary = document.createElement('summary'); summary.textContent = label;
      section.appendChild(summary); card.appendChild(section); return section;
    };
    blocks.forEach((block, idx) => {
      const target = block.type === 'chart' ? detail('Grafik', ['chart', 'both'].includes(presentation))
        : block.type === 'table' ? detail('Tabel data', ['table', 'both'].includes(presentation))
        : block.type === 'insight' ? detail('Metode dan catatan') : card;
      if (block.type === 'text') {
        const div = document.createElement('div');
        div.className = 'ai-block-text';
        div.innerHTML = renderMarkdownText(block.content);
        target.appendChild(div);
      } else if (block.type === 'warning') {
        const warn = document.createElement('div');
        warn.className = 'ai-block-warning' + (block.title === 'Data simulasi' ? ' ai-simulation-note' : '');
        warn.setAttribute('role', block.title === 'Data simulasi' ? 'status' : 'alert');
        warn.innerHTML = `<strong><i class="bi bi-shield-exclamation me-1"></i>${escapeHtml(block.title || 'Peringatan')}</strong><div>${renderMarkdownText(block.content || '')}</div>`;
        target.appendChild(warn);
      } else if (block.type === 'kpi' && Array.isArray(block.items)) {
        const grid = document.createElement('div');
        grid.className = 'ai-block-kpi-grid';
        block.items.slice(0, 3).forEach((item) => {
          const box = document.createElement('div');
          box.className = `ai-kpi-box ${escapeHtml(item.tone || 'primary')}`;
          box.innerHTML = `<small>${escapeHtml(item.label)}</small><strong>${escapeHtml(item.value)}</strong><span>${escapeHtml(item.sub || '')}</span>`;
          grid.appendChild(box);
        });
        target.appendChild(grid);
        if (block.items.length > 3) {
          const more = detail('Angka lainnya');
          block.items.slice(3).forEach((item) => {
            const p = document.createElement('p'); p.textContent = item.label + ': ' + item.value + (item.sub ? ' — ' + item.sub : ''); more.appendChild(p);
          });
        }
      } else if (block.type === 'chart') {
        target.appendChild(renderChartBlock(block, idx));
      } else if (block.type === 'table') {
        target.appendChild(renderTableBlock(block));
      } else if (block.type === 'insight' && Array.isArray(block.items)) {
        const ins = document.createElement('section');
        ins.className = 'ai-block-insight';
        ins.innerHTML = `<h4><i class="bi bi-lightbulb" aria-hidden="true"></i>${escapeHtml(block.title || 'Temuan & Metodologi')}</h4><ul>${block.items.map((it) => `<li>${renderMarkdownText(it).replace(/^<p>|<\/p>$/g, '')}</li>`).join('')}</ul>`;
        target.appendChild(ins);
      }
    });

    const queries = [...(msg.metadata?.sql_diagnostics || []), ...(msg.metadata?.queries || [])];
    const showQueryAudit = document.getElementById('aiWorkspace')?.dataset.showQueryAudit !== '0' && msg.metadata?.show_query_audit !== false;
    if (showQueryAudit && msg.metadata?.plan_diagnostics) {
      const details = document.createElement('details'); details.className = 'ai-sql-audit';
      const summary = document.createElement('summary'); summary.textContent = 'Rencana AI · Diagnostik developer';
      const pre = document.createElement('pre'); pre.className = 'ai-sql-code';
      pre.textContent = JSON.stringify(msg.metadata.plan_diagnostics, null, 2);
      details.append(summary, pre); card.appendChild(details);
    }
    if (showQueryAudit && !emptyResult && Array.isArray(queries) && queries.length > 0) {
      const details = document.createElement('details');
      details.className = 'ai-sql-audit';
      const summary = document.createElement('summary');
      summary.innerHTML = `<i class="bi bi-code-square me-1"></i>Lihat ${queries.length} query · Jejak analisis`;
      details.appendChild(summary);

      queries.forEach((q) => {
        const pre = document.createElement('pre');
        pre.className = 'ai-sql-code';
        const status = q.executed === false ? 'Belum dieksekusi' : (q.success === false ? 'Gagal' : 'Berhasil');
        const reason = q.error ? `\n-- ${q.error_code || q.failure_stage || 'error'}: ${q.error}` : '';
        pre.textContent = `-- ${q.step || 'Query'} · ${status} (${q.row_count ?? 0} baris · ${q.duration_ms ?? 0} ms)${reason}\n${q.sql}`;
        details.appendChild(pre);
      });
      card.appendChild(details);
    }

    const actions = document.createElement('div');
    actions.className = 'ai-msg-actions';
    const awaitingClarification = msg.metadata?.analysis_status === 'clarification' || msg.metadata?.intent === 'clarification_needed';
    const kind = msg.metadata?.response_kind;
    const analytical = !emptyResult && !awaitingClarification && (!kind || kind === 'analysis');
    const capabilities = msg.metadata?.actions || { table: blocks.some(b => b.type === 'table'), chart: blocks.some(b => b.type === 'chart') };
    const reportRef = escapeHtml(msg.metadata?.report_ref || '');
    actions.innerHTML = `
      <button type="button" class="btn btn-soft btn-sm" data-ai-copy><i class="bi bi-clipboard" aria-hidden="true"></i> Salin Jawaban</button>
      ${analytical && capabilities.table ? `<button type="button" class="btn btn-soft btn-sm" data-ai-reformat="table" data-report-ref="${reportRef}"><i class="bi bi-table" aria-hidden="true"></i> Tampilkan Tabel</button>` : ''}
      ${analytical && capabilities.chart ? `<button type="button" class="btn btn-soft btn-sm" data-ai-reformat="chart" data-report-ref="${reportRef}"><i class="bi bi-bar-chart" aria-hidden="true"></i> Tampilkan Grafik</button>` : ''}
    `;
    actions.querySelector('[data-ai-copy]').addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(msg.content || '');
        window.KerangkaCatalog?.notify('success', 'Ringkasan analisis berhasil disalin ke clipboard.', 'Disalin');
      } catch (_) {
        window.KerangkaCatalog?.notify('info', msg.content || '', 'Ringkasan Analisis');
      }
    });
    card.appendChild(actions);

    article.appendChild(card);
    return article;
  };

  const rememberConversation = (uid) => {
    if (!window.history?.replaceState) return;
    const url = new URL(location.href);
    url.searchParams.set('c', uid || 'new');
    window.history.replaceState(window.history.state, '', url);
  };
  const getWorkspace = () => document.getElementById('aiWorkspace');
  const getEndpointBase = (workspace) => {
    const attr = workspace?.dataset.endpointBase;
    if (attr && attr.startsWith('/')) return attr.replace(/\/$/, '');
    return '/asisten-ai';
  };
  const getCsrf = () => ({
    name: document.querySelector('meta[name="csrf-token-name"]')?.content || 'csrf_test_name',
    value: document.querySelector('meta[name="csrf-token-value"]')?.content || '',
  });

  const updateExportLinks = (workspace, uid, hasRows, reportRef = null) => {
    const exportToolbar = workspace?.querySelector('#aiExportToolbar');
    if (!exportToolbar) return;
    exportToolbar.hidden = !uid || !hasRows;
    if (!uid) return;
    const base = getEndpointBase(workspace);
    for (const fmt of ['csv', 'excel', 'pdf']) {
      const link = exportToolbar.querySelector(`[data-ai-export="${fmt}"]`);
      if (link) link.href = `${base}/export/${encodeURIComponent(uid)}/${fmt}${reportRef ? `?report_ref=${encodeURIComponent(reportRef)}` : ''}`;
    }
  };

  const updateContextBadge = (workspace, ctx) => {
    const contextBadge = workspace?.querySelector('#aiActiveContextBadge');
    if (!contextBadge || !ctx) return;
    const parts = [];
    if (ctx.domain) parts.push(`Domain: ${ctx.domain}`);
    if (ctx.year) {
      parts.push(ctx.comparison_year ? `${ctx.year} vs ${ctx.comparison_year}` : `Tahun ${ctx.year}`);
    }
    const suggestions = workspace.querySelector('#aiFollowupChips');
    if (suggestions) {
      suggestions.replaceChildren();
      const options = ctx.domain === 'sales' ? [['Bandingkan periode', 'Bandingkan dengan bulan sebelumnya.'], ['Rata-rata harian', 'Sekarang hitung rata-rata transaksi per hari.']]
        : ctx.domain === 'products_inventory' ? [['Stok menipis', 'Barang apa yang stoknya menipis?'], ['Nilai per lokasi', 'Bandingkan nilai stok berdasarkan lokasi.']] : [];
      if (!ctx.last_empty_result && !ctx.pending_request && ctx.last_rows?.length && (!ctx.dialogue?.response_kind || ctx.dialogue.response_kind === 'analysis')) options.slice(0, 2).forEach(([label, prompt]) => { const button = document.createElement('button'); button.type = 'button'; button.className = 'ai-chip'; button.dataset.aiPrompt = prompt; suggestions.appendChild(button); button.textContent = label; });
      suggestions.hidden = !suggestions.childElementCount;
    }
    contextBadge.textContent = parts.length ? parts.join(' · ') : 'Konteks Aktif';
  };

  const scrollToBottom = (workspace) => {
    const viewport = workspace?.querySelector('#aiMessagesViewport');
    if (viewport) viewport.scrollTo({ top: viewport.scrollHeight, behavior: 'smooth' });
  };

  const renderConversation = (workspace, conv) => {
    if (!workspace) return;
    const stream = workspace.querySelector('#aiMessageStream');
    const emptyState = workspace.querySelector('#aiEmptyState');
    const titleEl = workspace.querySelector('#aiActiveTitle');
    if (!stream || !emptyState || !titleEl) return;

    disposeCharts(stream);
    stream.replaceChildren();
    if (!conv || !Array.isArray(conv.messages) || conv.messages.length === 0) {
      emptyState.hidden = false;
      titleEl.textContent = conv?.title || 'Percakapan Baru';
      updateExportLinks(workspace, conv?.conversation_uid || '', false);
      updateContextBadge(workspace, conv?.context || {});
      return;
    }

    emptyState.hidden = true;
    titleEl.textContent = conv.title || 'Percakapan Analisis';
    conv.messages.forEach((m) => stream.appendChild(createMessageElement(m)));
    const latest = conv.messages.filter(m => m.role === 'assistant').at(-1);
    const hasRows = !latest?.metadata?.empty_result && !conv.context?.last_empty_result && (latest?.metadata?.actions ? latest.metadata.actions.export : (!conv.context?.dialogue?.response_kind || conv.context.dialogue.response_kind === 'analysis') && Array.isArray(conv.context?.last_rows) && conv.context.last_rows.length > 0);
    updateExportLinks(workspace, conv.conversation_uid, hasRows, latest?.metadata?.report_ref);
    updateContextBadge(workspace, conv.context || {});
    scrollToBottom(workspace);
  };

  const upsertSidebarConversation = (workspace, uid, title, messageCount) => {
    const convList = workspace?.querySelector('#aiConversationList');
    if (!convList) return;
    workspace.querySelector('#aiHistoryEmpty')?.remove();
    let item = convList.querySelector(`[data-conv-item="${CSS.escape(uid)}"]`);
    if (!item) {
      item = document.createElement('div');
      item.className = 'ai-conv-item';
      item.dataset.convItem = uid;
      item.setAttribute('role', 'listitem');
      item.innerHTML = `
        <button type="button" class="ai-conv-select" data-conv-select="${escapeHtml(uid)}">
          <i class="bi bi-chat-left-text" aria-hidden="true"></i>
          <span class="ai-conv-copy">
            <strong data-conv-title></strong>
            <small></small>
          </span>
        </button>
        <div class="dropdown ai-conv-actions"><button type="button" class="icon-button" data-bs-toggle="dropdown" aria-label="Tindakan percakapan"><i class="bi bi-three-dots"></i></button><div class="dropdown-menu dropdown-menu-end">
          <button type="button" class="dropdown-item" data-conv-rename="${escapeHtml(uid)}" aria-label="Ganti nama percakapan">Ganti nama</button>
          <button type="button" class="dropdown-item text-danger" data-conv-delete="${escapeHtml(uid)}" aria-label="Hapus percakapan">Hapus percakapan</button>
        </div>
        </div>
      `;
      convList.prepend(item);
    }
    item.querySelector('[data-conv-title]').textContent = title;
    item.querySelector('[data-bs-toggle="dropdown"]')?.setAttribute('aria-label', 'Tindakan percakapan ' + title);
    item.querySelector('[data-conv-rename]').dataset.convCurrentTitle = title;
    if (typeof messageCount === 'number') {
      item.querySelector('small').textContent = `${messageCount} pesan`;
    }
    convList.querySelectorAll('.ai-conv-item').forEach((el) => {
      el.classList.toggle('active', el.dataset.convItem === uid);
    });
  };

  const submitQuestion = async (workspace, questionText, presentation = null, reportRef = null) => {
    if (!workspace) return;
    const text = String(questionText || '').trim();
    const sendBtn = workspace.querySelector('#btnSendAiMessage');
    const emptyState = workspace.querySelector('#aiEmptyState');
    const stream = workspace.querySelector('#aiMessageStream');
    const input = workspace.querySelector('#aiMessageInput');
    const thinking = workspace.querySelector('#aiThinkingIndicator');
    const titleEl = workspace.querySelector('#aiActiveTitle');

    if (!text || !sendBtn || sendBtn.disabled) return;

    emptyState.hidden = true;
    const userBubble = createMessageElement({ role: 'user', content: text });
    stream.appendChild(userBubble);
    const generation = workspace._aiGeneration || 0;
    const failed = (message, errorCode = null, report = null) => {
      if (!workspace.isConnected || generation !== (workspace._aiGeneration || 0)) return;
      const notice = document.createElement('div'); notice.className = 'ai-send-error'; notice.setAttribute('role', 'alert');
      const structured = report?.metadata?.response_kind === 'error' && Array.isArray(report.blocks);
      if (structured) {
        stream.appendChild(createMessageElement({role:'assistant',content:message,blocks:report.blocks,metadata:report.metadata,created_at:report.metadata.generated_at || 'Baru saja'}));
      } else {
        const explanation = document.createElement('span'); explanation.textContent = message;
        notice.append(explanation);
      }
      if (!['analysis_not_supported', 'sql_validation_failed', 'sql_repair_scope_mismatch', 'business_filter_mismatch', 'business_period_mismatch', 'business_grouping_mismatch', 'business_requirement_mismatch', 'business_member_mismatch', 'member_reference_invalid', 'provider_invalid_plan', 'provider_plan_mismatch', 'provider_route_mismatch', 'result_limit_exceeded', 'provider_reasoning_unsupported', 'provider_output_incomplete'].includes(errorCode)) {
        const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-soft btn-sm'; retry.textContent = 'Coba lagi';
        retry.addEventListener('click', () => { if (!sendBtn.disabled && generation === (workspace._aiGeneration || 0)) { userBubble.remove(); submitQuestion(workspace, text, presentation, reportRef); } });
        notice.append(retry);
      }
      if (notice.children.length) userBubble.appendChild(notice);
      if (!presentation && !input.value) input.value = text;
    };
    input.value = '';
    input.style.height = 'auto';
    sendBtn.disabled = true;
    thinking.hidden = false;
    scrollToBottom(workspace);

    const csrf = getCsrf();
    const activeUid = workspace.dataset.activeUid || '';
    const body = new URLSearchParams();
    body.set(csrf.name, csrf.value);
    body.set('message', text);
    if (activeUid) body.set('conversation_uid', activeUid);
    if (presentation) { body.set('action', 'reformat'); body.set('format', presentation); }
    if (reportRef) body.set('report_ref', reportRef);

    try {
      const endpoint = `${getEndpointBase(workspace)}/message`;
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        },
        body: body.toString(),
      });
      const result = await window.KerangkaHTTP.readJSON(response);
      if (!workspace.isConnected || generation !== (workspace._aiGeneration || 0)) return;
      if (response.status === 401 && result.redirect) { location.assign(result.redirect); return; }
      if (!response.ok || !result.success) {
        if (result.conversation_uid) {
          workspace.dataset.activeUid = result.conversation_uid;
      rememberConversation(result.conversation_uid);
          upsertSidebarConversation(workspace, result.conversation_uid, result.conversation_title || 'Percakapan Analisis', result.conversation_message_count);
        }
        failed(result.message || result.summary || 'Analisis belum berhasil. Coba kembali.', result.error_code, result);
        return;
      }

      workspace.dataset.activeUid = result.conversation_uid;
      rememberConversation(result.conversation_uid);
      titleEl.textContent = result.conversation_title || titleEl.textContent;

      stream.appendChild(
        createMessageElement({
          role: 'assistant',
          content: result.summary,
          blocks: result.blocks,
          metadata: result.metadata,
          created_at: result.metadata?.generated_at || 'Baru saja',
        })
      );

      const hasRows = !result.metadata?.empty_result && !result.context?.last_empty_result && (result.metadata?.actions ? result.metadata.actions.export : Array.isArray(result.context?.last_rows) && result.context.last_rows.length > 0);
      updateExportLinks(workspace, result.conversation_uid, hasRows, result.metadata?.report_ref);
      updateContextBadge(workspace, result.context || {});
      upsertSidebarConversation(workspace, result.conversation_uid, result.conversation_title || 'Percakapan Analisis', result.conversation_message_count);
      scrollToBottom(workspace);
    } catch (_) {
      failed('Koneksi terputus. Pertanyaan Anda tetap tersedia untuk dicoba kembali.');
    } finally {
      thinking.hidden = true;
      sendBtn.disabled = false;
      if (workspace.isConnected && generation === (workspace._aiGeneration || 0)) input.focus();
    }
  };

  const initWorkspace = (rootContainer = document) => {
    const workspace = rootContainer?.querySelector?.('[data-ai-workspace]') || getWorkspace();
    if (!workspace || workspace.dataset.initialized === '1') return;
    workspace.dataset.initialized = '1';

    try {
      const initialConv = JSON.parse(workspace.dataset.initialConversation || 'null');
      if (initialConv) renderConversation(workspace, initialConv);
    } catch (_) {}
  };

  // Document-level event delegation (bekerja penuh baik saat SSR maupun navigasi SPA)
  document.addEventListener('click', async (e) => {
    // Preset AI Provider di dalam Modal
    const presetBtn = e.target.closest('[data-ai-preset-provider]');
    if (presetBtn) {
      const form = document.getElementById('aiProviderForm');
      if (form) {
        form.elements.namedItem('provider_name').value = presetBtn.dataset.aiPresetProvider;
        form.elements.namedItem('base_url').value = presetBtn.dataset.aiPresetUrl;
        form.elements.namedItem('model_id').value = presetBtn.dataset.aiPresetModel;
      }
      return;
    }

    const workspace = getWorkspace();
    if (!workspace) return;

    const formatBtn = e.target.closest('[data-ai-reformat]');
    if (formatBtn && workspace.contains(formatBtn)) {
      const format = formatBtn.dataset.aiReformat;
      submitQuestion(workspace, format === 'table' ? 'Ubah menjadi tabel.' : 'Tampilkan grafiknya.', format, formatBtn.dataset.reportRef || null);
      return;
    }
    if (e.target.closest('[data-ai-close-history]')) {
      workspace.classList.remove('ai-sidebar-open');
      workspace.querySelector('#btnToggleAiSidebar')?.setAttribute('aria-expanded', 'false');
      workspace.querySelector('#btnToggleAiSidebar')?.focus();
      return;
    }
    const promptBtn = e.target.closest('[data-ai-prompt]');
    if (promptBtn && workspace.contains(promptBtn)) {
      e.preventDefault();
      initWorkspace(workspace);
      submitQuestion(workspace, promptBtn.dataset.aiPrompt);
      return;
    }

    if (e.target.closest('#btnNewAiChat')) {
      workspace._aiGeneration = (workspace._aiGeneration || 0) + 1;
      workspace._aiDrafts ||= new Map();
      workspace._aiDrafts.set(workspace.dataset.activeUid || '', workspace.querySelector('#aiMessageInput').value);
      workspace.querySelector('#aiMessageInput').value = '';
      workspace.dataset.activeUid = '';
      rememberConversation('');
      renderConversation(workspace, null);
      workspace.querySelectorAll('.ai-conv-item').forEach((el) => el.classList.remove('active'));
      workspace.classList.remove('ai-sidebar-open');
      workspace.querySelector('#aiMessageInput')?.focus();
      return;
    }

    if (e.target.closest('#btnToggleAiSidebar')) {
      workspace.classList.toggle('ai-sidebar-open');
      workspace.querySelector('#btnToggleAiSidebar').setAttribute('aria-expanded', String(workspace.classList.contains('ai-sidebar-open')));
      return;
    }

    const selectBtn = e.target.closest('[data-conv-select]');
    if (selectBtn && workspace.contains(selectBtn)) {
      const uid = selectBtn.dataset.convSelect;
      const selection = workspace._aiGeneration = (workspace._aiGeneration || 0) + 1;
      workspace._aiDrafts ||= new Map();
      workspace._aiDrafts.set(workspace.dataset.activeUid || '', workspace.querySelector('#aiMessageInput').value);
      try {
        const res = await fetch(`${getEndpointBase(workspace)}/conversations/${encodeURIComponent(uid)}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await window.KerangkaHTTP.readJSON(res);
        if (selection !== workspace._aiGeneration || !workspace.isConnected) return;
        if (data.success && data.conversation) {
          workspace.querySelector('#aiMessageInput').value = workspace._aiDrafts.get(data.conversation.conversation_uid) || '';
          workspace.dataset.activeUid = data.conversation.conversation_uid;
          rememberConversation(data.conversation.conversation_uid);
          renderConversation(workspace, data.conversation);
          workspace.querySelectorAll('.ai-conv-item').forEach((el) => {
            el.classList.toggle('active', el.dataset.convItem === data.conversation.conversation_uid);
          });
          workspace.classList.remove('ai-sidebar-open');
        }
      } catch (_) {}
      return;
    }

    const renameBtn = e.target.closest('[data-conv-rename]');
    if (renameBtn && workspace.contains(renameBtn)) {
      const modalEl = document.getElementById('aiRenameModal');
      document.getElementById('aiRenameUid').value = renameBtn.dataset.convRename;
      document.getElementById('aiRenameInput').value = renameBtn.dataset.convCurrentTitle || '';
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }

    const deleteBtn = e.target.closest('[data-conv-delete]');
    if (deleteBtn && workspace.contains(deleteBtn)) {
      const uid = deleteBtn.dataset.convDelete;
      const confirmed = window.KerangkaWorkspace?.confirm
        ? await window.KerangkaWorkspace.confirm({
            title: 'Hapus percakapan?',
            message: 'Seluruh riwayat pesan dan konteks analisis pada percakapan ini akan dihapus.',
            accept: 'Hapus',
            cancel: 'Batal',
          })
        : confirm('Hapus percakapan ini?');
      if (!confirmed) return;

      const csrf = getCsrf();
      const body = new URLSearchParams({ [csrf.name]: csrf.value });
      const res = await fetch(`${getEndpointBase(workspace)}/conversations/${encodeURIComponent(uid)}/delete`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const data = await window.KerangkaHTTP.readJSON(res);
      if (data.success) {
        workspace.querySelector(`[data-conv-item="${CSS.escape(uid)}"]`)?.remove();
        if (workspace.dataset.activeUid === uid) {
          workspace._aiGeneration = (workspace._aiGeneration || 0) + 1;
          workspace.dataset.activeUid = '';
          renderConversation(workspace, null);
        }
        window.KerangkaCatalog?.notify('info', 'Percakapan telah dihapus.', 'Dihapus');
      }
    }
  });

  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.id === 'aiChatForm') {
      e.preventDefault();
      const workspace = getWorkspace();
      initWorkspace(workspace);
      const input = workspace?.querySelector('#aiMessageInput');
      if (input) submitQuestion(workspace, input.value);
      return;
    }

    if (form.id === 'aiRenameForm') {
      e.preventDefault();
      const workspace = getWorkspace();
      const uid = document.getElementById('aiRenameUid')?.value;
      const newTitle = document.getElementById('aiRenameInput')?.value.trim();
      if (!workspace || !uid || !newTitle) return;

      const csrf = getCsrf();
      const body = new URLSearchParams({ [csrf.name]: csrf.value, title: newTitle });
      const res = await fetch(`${getEndpointBase(workspace)}/conversations/${encodeURIComponent(uid)}/rename`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const data = await window.KerangkaHTTP.readJSON(res);
      if (data.success) {
        upsertSidebarConversation(workspace, uid, data.title);
        if (workspace.dataset.activeUid === uid) {
          workspace.querySelector('#aiActiveTitle').textContent = data.title;
        }
        bootstrap.Modal.getInstance(document.getElementById('aiRenameModal'))?.hide();
        window.KerangkaCatalog?.notify('success', data.message, 'Berhasil');
      }
      return;
    }

    if (form.id === 'aiProviderForm') {
      e.preventDefault();
      if (form._aiSaving) return;
      const workspace = getWorkspace();
      const csrf = getCsrf();
      const formData = new FormData(form);
      formData.set(csrf.name, csrf.value);

      const endpoint = workspace ? `${getEndpointBase(workspace)}/provider-config` : '/asisten-ai/provider-config';
      const submitButton = form.querySelector('button[type="submit"]');
      form._aiSaving = true;
      if (submitButton) submitButton.disabled = true;
      try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const data = await window.KerangkaHTTP.readJSON(res);
      if (data.success) {
        const badge = document.getElementById('aiProviderBadge');
        if (badge && data.provider) {
          badge.textContent = `Model: ${data.provider.model_id} · ${data.provider.configured ? 'Dikonfigurasi · koneksi belum diuji' : 'AI online belum diatur'}`;
        }
        const reasoningStatus = document.getElementById('aiReasoningStatus');
        if (reasoningStatus && data.provider) reasoningStatus.textContent = data.provider.reasoning_capabilities?.effort_supported
          ? 'Provider menyatakan model mendukung pengaturan level reasoning.'
          : 'Dukungan level reasoning belum diperiksa atau belum dinyatakan oleh provider.';
        form.elements.namedItem('api_key').value = '';
        bootstrap.Modal.getInstance(document.getElementById('aiProviderModal'))?.hide();
        window.KerangkaCatalog?.notify('success', data.message, 'Konfigurasi AI');
      } else {
        window.KerangkaCatalog?.notify('error', data.message || 'Gagal menyimpan konfigurasi.', 'Validasi');
      }
      } catch {
        window.KerangkaCatalog?.notify('error', 'Konfigurasi belum dapat dikonfirmasi tersimpan. Periksa koneksi lalu coba kembali; isian tetap tersedia.', 'Koneksi bermasalah');
      } finally {
        form._aiSaving = false;
        if (submitButton) submitButton.disabled = false;
      }
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !document.querySelector('.modal.show')) {
      const workspace = getWorkspace();
      if (workspace?.classList.contains('ai-sidebar-open')) { workspace.classList.remove('ai-sidebar-open'); workspace.querySelector('#btnToggleAiSidebar')?.setAttribute('aria-expanded', 'false'); workspace.querySelector('#btnToggleAiSidebar')?.focus(); }
    }
    if (e.target.id === 'aiMessageInput' && e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      const workspace = getWorkspace();
      initWorkspace(workspace);
      submitQuestion(workspace, e.target.value);
    }
  });

  document.addEventListener('input', (e) => {
    if (e.target.id === 'aiMessageInput') {
      e.target.style.height = 'auto';
      e.target.style.height = `${Math.min(140, e.target.scrollHeight)}px`;
    } else if (e.target.id === 'aiHistorySearchInput') {
      const workspace = getWorkspace();
      const term = e.target.value.trim().toLocaleLowerCase('id-ID');
      workspace?.querySelectorAll('.ai-conv-item').forEach((item) => {
        const title = item.querySelector('[data-conv-title]')?.textContent.toLocaleLowerCase('id-ID') || '';
        item.hidden = term !== '' && !title.includes(term);
      });
    }
  });

  if (document.readyState !== 'loading') {
    initWorkspace(document);
  } else {
    document.addEventListener('DOMContentLoaded', () => initWorkspace(document));
  }

  window.KerangkaAiChat = { init: initWorkspace, createMessageElement, renderMarkdownText };
})();
