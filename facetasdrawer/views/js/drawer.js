(() => {
  'use strict';
  const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const clone = value => JSON.parse(JSON.stringify(value));
  const icon = (name) => `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">${({filter:'<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2.5" fill="currentColor"/><circle cx="15" cy="17" r="2.5" fill="currentColor"/>',close:'<path d="m6 6 12 12M6 18 18 6"/>',plus:'<path d="M5 12h14M12 5v14"/>',arrow:'<path d="M4 12h15m-5-5 5 5-5 5"/>'})[name]}</svg>`;
  const empty = () => ({groups:{}, min:null, max:null, stock:false});
  const count = state => Object.values(state.groups).reduce((n, a) => n + a.length, 0) + Number(state.stock) + Number(state.min !== null || state.max !== null);
  function mount(root) {
    if (root.fd) return;
    let config;
    try { config = JSON.parse(root.dataset.fdConfig); } catch (_) { return; }
    // Classic renders facets in a hidden mobile sidebar. Keep the trigger in the listing.
    const listing = root.id === 'search_filters' ? document.querySelector('#products') : null;
    if (listing && !listing.contains(root)) listing.prepend(root);
    if (listing) document.body.classList.add('fd-listing-ready');
    const l = config.labels;
    let applied = clone(config.state || empty());
    applied.groups = {...applied.groups};
    let draft = clone(applied);
    let opener;
    const money = value => new Intl.NumberFormat(config.locale || 'en', {style:'currency',currency:config.currency || 'USD',minimumFractionDigits:0}).format(value);
    const formatPrice = state => `${money(state.min ?? config.bounds.min)} – ${money(state.max ?? config.bounds.max)}`;
    function chips(state) {
      let html = '';
      for (const [key, ids] of Object.entries(state.groups)) {
        const group = config.groups.find(g => g.id === key);
        for (const id of ids) {
          const option = group?.options.find(o => String(o.id) === String(id));
          html += `<button type="button" class="fd-chip" data-remove-group="${escape(key)}" data-remove-id="${escape(id)}" aria-label="${escape(l.remove)}: ${escape(option?.label || id)}">${escape(option?.label || id)} ${icon('close')}</button>`;
        }
      }
      if (state.min !== null || state.max !== null) html += `<button type="button" class="fd-chip" data-remove-special="price">${escape(formatPrice(state))}${icon('close')}</button>`;
      if (state.stock) html += `<button type="button" class="fd-chip" data-remove-special="stock">${escape(l.stock)}${icon('close')}</button>`;
      return html;
    }
    root.innerHTML = `<div class="fd-toolbar"><button type="button" class="fd-trigger">${icon('filter')}<span>${escape(l.filters)}</span><span class="fd-badge" hidden></span></button><div class="fd-applied"></div></div>`;
    const dialog = document.createElement('dialog');
    dialog.className = 'fd-dialog';
    dialog.setAttribute('aria-label', l.filters);
    document.body.append(dialog);
    const trigger = root.querySelector('.fd-trigger');
    trigger.setAttribute('aria-haspopup', 'dialog');
    function renderApplied() {
      root.querySelector('.fd-applied').innerHTML = chips(applied);
      const badge = root.querySelector('.fd-badge');
      badge.textContent = count(applied);
      badge.hidden = !count(applied);
    }
    function renderDialog() {
      dialog.innerHTML = `<form class="fd-form" novalidate>
        <header class="fd-head"><div class="fd-eyebrow">${escape(l.refine)}</div><button type="button" class="fd-icon-button fd-close" aria-label="${escape(l.close)}">${icon('close')}</button><h2>${escape(l.filters)}<span class="fd-selection-count"></span></h2><p>${escape(l.intro)}</p></header>
        <div class="fd-scroll"><div class="fd-draft-chips"></div>
        ${config.groups.filter(group => group.displayed !== false || draft.groups[group.id]?.length).map((group,index) => `<details class="fd-section" data-fd-section="${escape(group.id)}" ${index < 2 || draft.groups[group.id]?.length ? 'open' : ''}><summary>${escape(group.label)}<span class="fd-group-count" data-count-group="${escape(group.id)}"></span>${icon('plus')}</summary><div class="fd-options">${group.options.length > 7 ? `<input type="search" class="fd-option-search" placeholder="${escape(l.search)}" aria-label="${escape(l.search)}: ${escape(group.label)}">` : ''}${group.options.map((option,optionIndex) => `<label class="fd-option" data-label="${escape(option.label)}" data-option-index="${optionIndex}"><input type="checkbox" data-group="${escape(group.id)}" value="${escape(option.id)}"><span class="fd-check"></span>${/^#[0-9a-f]{6}$/i.test(option.color || '') ? `<span class="fd-swatch" style="--swatch:${option.color}"></span>` : ''}<span>${escape(option.label)}</span><span class="fd-option-count">${Number(option.count) || 0}</span></label>`).join('')}<p class="fd-no-options" hidden>${escape(l.empty)}</p>${group.options.length > 7 ? `<button type="button" class="fd-more" aria-expanded="false">${escape(l.more || 'Show all options')} (${group.options.length})</button>` : ''}</div></details>`).join('')}
        ${config.priceVisible !== false ? `<details class="fd-section fd-price" open><summary>${escape(l.price)}${icon('plus')}</summary><div class="fd-price-fields"><label><span>${escape(l.min)}</span><div><span>${escape(config.currency)}</span><input name="min" aria-label="${escape(l.min)}" type="number" min="0" step="any" placeholder="${config.bounds.min}"></div></label><span>—</span><label><span>${escape(l.max)}</span><div><span>${escape(config.currency)}</span><input name="max" aria-label="${escape(l.max)}" type="number" min="0" step="any" placeholder="${config.bounds.max}"></div></label></div><input class="fd-range" name="range" type="range" min="0" max="${config.bounds.max}" step="1" aria-label="${escape(l.max)}"><div class="fd-range-labels"><span>${escape(money(0))}</span><span>${escape(money(config.bounds.max))}</span></div></details>` : ''}
        <label class="fd-stock"><span><strong>${escape(l.stock)}</strong><small>${escape(l.stockHint)}</small></span><input type="checkbox" name="stock" role="switch"><span class="fd-switch"></span></label>
        </div><footer class="fd-footer"><p class="fd-error" role="alert" hidden></p><div class="fd-footer-actions"><button type="button" class="fd-clear">${escape(l.clear)}</button><button type="submit" class="fd-apply"><span>${escape(l.apply)}</span>${icon('arrow')}</button></div></footer></form>`;
      syncControls();
    }
    function updateDraft() {
      dialog.querySelector('.fd-draft-chips').innerHTML = chips(draft);
      dialog.querySelector('.fd-selection-count').textContent = count(draft) ? ` / ${count(draft)}` : '';
      dialog.querySelectorAll('[data-count-group]').forEach(el => { el.textContent = draft.groups[el.dataset.countGroup]?.length || ''; });
      // Server counts describe the last applied state. Hide them when the draft changes.
      const dirty = JSON.stringify(draft) !== JSON.stringify(applied);
      dialog.querySelectorAll('.fd-option-count').forEach(el => { el.hidden = dirty; });
      updateOptionVisibility();
      dialog.dispatchEvent(new CustomEvent('fd:draft', {bubbles:true,detail:clone(draft)}));
    }
    const searchText = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase(config.locale || 'en');
    function updateOptionVisibility() {
      dialog.querySelectorAll('.fd-options').forEach(options => {
        const query = searchText(options.querySelector('.fd-option-search')?.value.trim() || '');
        const more = options.querySelector('.fd-more');
        const expanded = more?.getAttribute('aria-expanded') === 'true';
        options.querySelectorAll('.fd-option').forEach(label => {
          const selected = label.querySelector('input').checked;
          label.hidden = query ? !searchText(label.dataset.label).includes(query) : !expanded && Number(label.dataset.optionIndex) >= 7 && !selected;
        });
        options.querySelector('.fd-no-options').hidden = !!options.querySelector('.fd-option:not([hidden])');
        if (more) more.hidden = !!query;
      });
    }
    function syncControls() {
      dialog.querySelectorAll('[data-group]').forEach(el => { el.checked = (draft.groups[el.dataset.group] || []).includes(el.value); });
      for (const key of ['min','max']) if (dialog.querySelector(`[name=${key}]`)) dialog.querySelector(`[name=${key}]`).value = draft[key] ?? '';
      if (dialog.querySelector('[name=range]')) dialog.querySelector('[name=range]').value = draft.max ?? config.bounds.max;
      dialog.querySelector('[name=stock]').checked = draft.stock;
      dialog.querySelector('.fd-error').hidden = true;
      updateDraft();
    }
    function close() { dialog.close(); }
    function open() {
      opener = document.activeElement;
      draft = clone(applied);
      renderDialog();
      const page = document.documentElement;
      // Keep the catalog width unchanged when its scrollbar disappears. Clear any
      // theme scrollbar gutter while locked so the backdrop reaches the viewport edge.
      const scrollbarWidth = Math.max(0, window.innerWidth - page.clientWidth);
      const paddingRight = parseFloat(getComputedStyle(page).paddingRight) || 0;
      page.style.setProperty('--fd-locked-padding-right', `${paddingRight + scrollbarWidth}px`);
      dialog.showModal();
      page.classList.add('fd-lock');
      dialog.querySelector('.fd-close').focus();
    }
    function apply(state) {
      const event = new CustomEvent('fd:apply', {bubbles:true,cancelable:true,detail:clone(state)});
      if (!root.dispatchEvent(event)) {
        applied = clone(state);
        renderApplied();
        close();
        return;
      }
      const url = new URL(window.location.href);
      ['page','q','from-xhr','ajax','action'].forEach(key => url.searchParams.delete(key));
      if (count(state)) url.searchParams.set('q', `fd1.${btoa(JSON.stringify(state)).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')}`);
      window.location.assign(url.href);
    }
    function remove(state, button) {
      if (button.dataset.removeGroup) {
        const key = button.dataset.removeGroup;
        state.groups[key] = state.groups[key].filter(id => id !== button.dataset.removeId);
        if (!state.groups[key].length) delete state.groups[key];
      } else if (button.dataset.removeSpecial === 'price') { state.min = state.max = null; }
      else if (button.dataset.removeSpecial === 'stock') state.stock = false;
    }
    root.addEventListener('click', event => {
      if (event.target.closest('.fd-trigger')) open();
      const button = event.target.closest('.fd-chip');
      if (button) { const state = clone(applied); remove(state,button); apply(state); }
    });
    dialog.addEventListener('close', () => {
      if (dialog.open) return;
      document.documentElement.classList.remove('fd-lock');
      document.documentElement.style.removeProperty('--fd-locked-padding-right');
      if (opener?.isConnected) opener.focus();
    });
    dialog.addEventListener('click', event => {
      if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) close(); }
      if (event.target.closest('.fd-close')) close();
      const more = event.target.closest('.fd-more');
      if (more) {
        const expanded = more.getAttribute('aria-expanded') !== 'true';
        more.setAttribute('aria-expanded', String(expanded));
        const size = more.closest('.fd-options').querySelectorAll('.fd-option').length;
        more.textContent = expanded ? (l.less || 'Show fewer options') : `${l.more || 'Show all options'} (${size})`;
        updateOptionVisibility();
      }
      if (event.target.closest('.fd-clear')) { draft = empty(); syncControls(); }
      const button = event.target.closest('.fd-chip');
      if (button) { remove(draft,button); syncControls(); }
    });
    dialog.addEventListener('input', event => {
      const el = event.target;
      if (el.matches('.fd-option-search')) {
        updateOptionVisibility();
        return;
      }
      if (el.dataset.group) {
        const key = el.dataset.group;
        draft.groups[key] = [...dialog.querySelectorAll('[data-group]')].filter(input => input.dataset.group === key && input.checked).map(input => input.value);
        if (!draft.groups[key].length) delete draft.groups[key];
      }
      if (el.name === 'stock') draft.stock = el.checked;
      if (el.name === 'min' || el.name === 'max') draft[el.name] = el.value === '' ? null : Number(el.value);
      if (el.name === 'range') { draft.max = Number(el.value); dialog.querySelector('[name=max]').value = el.value; }
      updateDraft();
    });
    dialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const focusable = [...dialog.querySelectorAll('button, a[href], input, select, textarea, summary, [tabindex]')]
        .filter(el => !el.disabled && el.tabIndex >= 0 && el.getClientRects().length);
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    dialog.addEventListener('submit', event => {
      event.preventDefault();
      if (!event.target.checkValidity() || (draft.min !== null && draft.max !== null && draft.min > draft.max)) {
        const error = dialog.querySelector('.fd-error'); error.textContent = l.invalid; error.hidden = false; return;
      }
      apply(draft);
    });
    root.fd = {open, destroy() { close(); dialog.remove(); }};
    renderApplied();
  }
  function init() {
    document.querySelectorAll('[data-fd-config]').forEach(mount);
  }
  const roots = new Set();
  function refresh() {
    roots.forEach(root => { if (!root.isConnected) { root.fd?.destroy(); roots.delete(root); } });
    init();
    document.querySelectorAll('[data-fd-config]').forEach(root => roots.add(root));
  }
  refresh();
  new MutationObserver(records => { if (records.some(r => [...r.addedNodes, ...r.removedNodes].some(n => n.nodeType === 1 && (n.matches?.('[data-fd-config]') || n.querySelector?.('[data-fd-config]'))))) refresh(); }).observe(document.body,{childList:true,subtree:true});
})();
