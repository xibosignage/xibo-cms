require('../style/quickswitcher.scss');

(function () {
  'use strict';

  const STR_PLACEHOLDER = 'Search layouts, media, displays…';
  const STR_NO_RESULTS = 'No results found';
  const STR_FILTER_TIP = 'Tip: use the checkboxes to filter which item types are returned.';

  const isMac = navigator.platform && /Mac/.test(navigator.platform);
  const HOTKEY = (e) => (isMac ? e.metaKey : e.ctrlKey) && e.key && e.key.toLowerCase() === 'k';

  let overlay = null;
  let box = null;
  let input = null;
  let list = null;
  let items = [];
  let idx = 0;
  let aborter = null;

  const createElement = (tag, className) => {
    const el = document.createElement(tag);
    if (className) el.className = className;
    return el;
  };

  const debounce = (fn, ms) => {
    let t = null;
    return (...args) => {
      if (t) clearTimeout(t);
      t = setTimeout(() => fn(...args), ms);
    };
  };

  function openQuickSwitcher() {
    if (box) return;

    overlay = createElement('div', 'quickswitcher-overlay');
    box = createElement('div', 'quickswitcher');

    input = createElement('input');
    input.type = 'search';
    input.placeholder = STR_PLACEHOLDER;
    input.setAttribute('aria-label', 'Quick switcher search');

    const top = createElement('div', 'quickswitcher-top');
    top.appendChild(input);

    const filters = createElement('div', 'quickswitcher-filters');
    const types = [
      ['all', 'All'],
      ['layout', 'Layouts'],
      ['campaign', 'Campaigns'],
      ['playlist', 'Playlists'],
      ['display', 'Displays'],
      ['media', 'Media'],
      ['navigation', 'Navigation']
    ];

    types.forEach(([value, labelText]) => {
      const id = `quickswitcher-type-${value}`;
      const label = createElement('label', 'quickswitcher-filter-label');
      const cb = createElement('input');
      cb.type = 'checkbox';
      cb.className = 'quickswitcher-type-checkbox';
      cb.value = value;
      cb.id = id;
      cb.checked = true;

      const span = createElement('span', 'quickswitcher-filter-text');
      span.textContent = labelText;

      label.appendChild(cb);
      label.appendChild(span);
      filters.appendChild(label);
    });

    const foldersNote = createElement('div', 'quickswitcher-folders-note');
    foldersNote.textContent = STR_FILTER_TIP;

    list = createElement('div', 'quickswitcher-list');
    list.setAttribute('role', 'listbox');

    box.appendChild(top);
    box.appendChild(filters);
    box.appendChild(foldersNote);
    box.appendChild(list);

    document.body.appendChild(overlay);
    document.body.appendChild(box);

    overlay.addEventListener('click', closeQuickSwitcher);
    input.addEventListener('input', debounce(fetchResults, 120));

    filters.addEventListener('change', (e) => {
      const checkboxes = Array.from(box.querySelectorAll('.quickswitcher-type-checkbox'));
      const master = checkboxes.find(cb => cb.value === 'all');
      if (!master) return;
      const others = checkboxes.filter(cb => cb.value !== 'all');
      const target = e && e.target;

      if (target && target.value === 'all') {
        if (target.checked) others.forEach(cb => cb.checked = true);
        else others.forEach(cb => cb.checked = false);
      } else if (target) {
        if (!target.checked) master.checked = false;
        else if (others.every(cb => cb.checked)) master.checked = true;
      }

      debounce(fetchResults, 50)();
    });

    document.addEventListener('keydown', navHandler, true);
    setTimeout(() => input.focus(), 0);
    fetchResults();
  }

  function closeQuickSwitcher() {
    if (aborter) try { aborter.abort(); } catch (e) {}
    document.removeEventListener('keydown', navHandler, true);
    if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
    if (box && box.parentNode) box.parentNode.removeChild(box);
    overlay = box = input = list = null;
    items = [];
    idx = 0;
  }

  function navHandler(e) {
    if (!box) return;
    if (e.key === 'Escape') { e.preventDefault(); closeQuickSwitcher(); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); return; }
    if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); return; }
    if (e.key === 'Enter') {
      e.preventDefault(); if (items[idx]) openItem(items[idx]);
    }
  }

  function move(delta) {
    if (!list) return;
    idx = Math.max(0, Math.min(items.length - 1, idx + delta));
    updateSelection();
    const row = list.children[idx];
    if (row && typeof row.scrollIntoView === 'function') row.scrollIntoView({ block: 'nearest' });
  }

  function updateSelection() {
    if (!list) return;
    Array.from(list.children).forEach((row, i) => {
      const selected = i === idx;
      row.setAttribute('aria-selected', selected ? 'true' : 'false');
      row.classList.toggle('quickswitcher-active', selected);
    });
  }

  function render() {
    if (!list) return;
    list.innerHTML = '';
    const q = input && input.value ? input.value.trim() : '';

    if (!items || items.length === 0) {
      if (q !== '') {
        const none = createElement('div', 'quickswitcher-no-results');
        none.setAttribute('role', 'status');
        none.textContent = STR_NO_RESULTS;
        list.appendChild(none);
      }
      updateSelection();
      return;
    }

    items.forEach((r, i) => {
      const row = createElement('div', 'quickswitcher-item');
      row.setAttribute('role', 'option');
      row.setAttribute('aria-selected', i === idx ? 'true' : 'false');

      const typeSpan = createElement('span', 'quickswitcher-type');
      typeSpan.textContent = r.type || '';
      const labelSpan = createElement('span', 'quickswitcher-label');
      labelSpan.innerHTML = escapeHtml(r.label || '');

      row.appendChild(typeSpan);
      row.appendChild(labelSpan);

      if (r.hint) {
        const hintSpan = createElement('span', 'quickswitcher-hint');
        hintSpan.innerHTML = escapeHtml(r.hint);
        row.appendChild(hintSpan);
      }

      row.addEventListener('mouseenter', () => { idx = i; updateSelection(); });
      row.addEventListener('click', (e) => { e.preventDefault(); openItem(r); });

      list.appendChild(row);
    });

    updateSelection();
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
  }

  function openItem(item) {
    if (!item || !item.url) return;
    window.location.href = item.url;
  }

  async function fetchResults() {
    const q = (input && input.value) ? input.value.trim() : '';
    if (aborter) try { aborter.abort(); } catch (e) {}
    aborter = new AbortController();

    try {
      const checked = box ? Array.from(box.querySelectorAll('.quickswitcher-type-checkbox')).filter(c => c.checked).map(c => c.value) : ['all'];
      const typeParam = (!checked.includes('all')) ? checked.join(',') : 'all';

      const url = `/quickswitcher/search?q=${encodeURIComponent(q)}&type=${encodeURIComponent(typeParam)}`;
      const res = await fetch(url, { signal: aborter.signal, credentials: 'same-origin' });
      if (!res.ok) throw new Error('Network error');
      const json = await res.json();
      items = json.results || [];
      idx = 0;
      render();
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      items = [];
      render();
      if (window && window.location && window.location.hostname === 'localhost') console.warn('quickswitcher fetch error', err);
    }
  }

  window.addEventListener('keydown', (e) => {
    const tag = (e.target && e.target.tagName || '').toLowerCase();
    if ((tag === 'input' || tag === 'textarea' || (e.target && e.target.isContentEditable)) && !(e.metaKey || e.ctrlKey)) return;
    if (HOTKEY(e)) { e.preventDefault(); openQuickSwitcher(); }
  });

  window.__xiboquickswitcher = { open: openQuickSwitcher, close: closeQuickSwitcher };
})();