(() => {
  const products = window.facetasCatalog;
  const root = document.querySelector('#preview-filters');
  let state = window.facetasInitial;
  let category = 'all';
  let savedOnly = false;
  let saved;
  try { saved = new Set(JSON.parse(localStorage.getItem('facetas-saved') || '[]')); } catch (_) { saved = new Set(); }
  const colors = {'1':'#b69b76','2':'#d7ceb8','3':'#efeee5','4':'#7c8361','5':'#c29792'};
  const heart = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 13 12 21 4 13C-2 7 6 0 12 7c6-7 14 0 8 6Z"/></svg>';
  const price = amount => new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:0}).format(amount);
  let toastTimer;
  function toast(message) { const el = document.querySelector('.toast'); el.textContent = message; el.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => { el.hidden = true; },2500); }
  function visible(s = state) {
    const search = document.querySelector('#product-search').value.toLowerCase();
    return products.filter(p => window.facetasMatches(p,s) && (category === 'all' || category === p.category) && (!savedOnly || saved.has(p.id)) && `${p.name} ${p.description}`.toLowerCase().includes(search));
  }
  function render() {
    const list = visible();
    const sort = document.querySelector('#sort').value;
    if (sort === 'price-asc') list.sort((a,b) => a.price-b.price);
    if (sort === 'price-desc') list.sort((a,b) => b.price-a.price);
    if (sort === 'name') list.sort((a,b) => a.name.localeCompare(b.name));
    document.querySelector('#result-count').textContent = `${list.length} ${savedOnly ? 'saved' : 'considered'} ${list.length === 1 ? 'piece' : 'pieces'}`;
    document.querySelector('#saved-count').textContent = saved.size;
    document.querySelector('#saved-toggle').setAttribute('aria-pressed',String(savedOnly));
    document.querySelector('#products').innerHTML = list.length ? list.map(p => `<article class="product-card"><div class="product-photo-wrap"><button class="product-image" data-product="${p.id}" aria-label="View ${p.name}"><img src="assets/${p.image}.jpg" alt="${p.name}" loading="lazy"></button>${p.badge ? `<span class="product-badge">${p.badge}</span>` : ''}<button class="save-button ${saved.has(p.id) ? 'saved' : ''}" data-save="${p.id}" aria-label="${saved.has(p.id) ? 'Unsave' : 'Save'} ${p.name}" aria-pressed="${saved.has(p.id)}">${heart}</button></div><div class="product-meta"><h2 class="product-name"><button data-product="${p.id}">${p.name}</button></h2><span class="product-price">${price(p.price)}</span></div><div class="product-bottom"><p class="product-subtitle">${p.description}</p><div class="swatches" aria-label="${p.description.split(' · ')[1]}"><span style="--color:${colors[p.color]}"></span></div></div></article>`).join('') : '<div class="empty-state"><span>ROOM FOR SOMETHING ELSE</span><h2>No pieces found. Yet.</h2><p>Try a different filter, or explore the whole collection.</p><button id="reset-collection">Explore all pieces</button></div>';
    document.querySelectorAll('[data-category]').forEach(el => { el.classList.toggle('selected',el.dataset.category === category); el.setAttribute('aria-pressed',String(el.dataset.category === category)); });
    document.querySelectorAll('[data-collection]').forEach(el => el.classList.toggle('active',el.dataset.collection === category));
  }
  function setCategory(value) { category = value; savedOnly = false; render(); }
  root.addEventListener('fd:apply',event => {
    event.preventDefault(); state = event.detail;
    const url = new URL(location.href); url.searchParams.delete('page');
    if (JSON.stringify(state) === JSON.stringify(window.facetasEmpty())) url.searchParams.delete('q');
    else url.searchParams.set('q',`fd1.${btoa(JSON.stringify(state)).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')}`);
    history.pushState(null,'',url); render();
  });
  window.addEventListener('popstate',() => location.reload());
  document.addEventListener('fd:draft',event => {
    const button = event.target.querySelector('.fd-apply span');
    if (button) button.textContent = `Show ${visible(event.detail).length} pieces`;
    event.target.querySelectorAll('[data-group]').forEach(input => {
      const probe = {...event.detail,groups:{...event.detail.groups,[input.dataset.group]:[input.value]}};
      const count = input.closest('label').querySelector('.fd-option-count');
      count.textContent = visible(probe).length; count.hidden = false;
    });
  });
  document.querySelector('#sort').addEventListener('change',render);
  document.querySelector('#product-search').addEventListener('input',render);
  document.querySelector('#search-toggle').addEventListener('click',() => { document.querySelector('#search-bar').hidden = false; document.querySelector('#product-search').focus(); document.querySelector('#search-bar').scrollIntoView({block:'center',behavior:'smooth'}); });
  document.querySelector('#search-close').addEventListener('click',() => { document.querySelector('#search-bar').hidden = true; document.querySelector('#product-search').value = ''; render(); document.querySelector('#search-toggle').focus(); });
  document.querySelector('#saved-toggle').addEventListener('click',() => { savedOnly = !savedOnly; render(); document.querySelector('.catalog').scrollIntoView({behavior:'smooth'}); });
  function toggleSave(id) {
    if (saved.has(id)) { saved.delete(id); toast('Piece removed from your collection'); } else { saved.add(id); toast('A good find. Saved to your collection.'); }
    try { localStorage.setItem('facetas-saved',JSON.stringify([...saved])); } catch (_) { /* Private browsing can disable persistence. */ }
    render();
  }
  const detail = document.querySelector('.product-dialog');
  document.addEventListener('click',event => {
    const tab = event.target.closest('[data-category],[data-collection]');
    if (tab) setCategory(tab.dataset.category || tab.dataset.collection);
    const save = event.target.closest('[data-save]');
    if (save) toggleSave(Number(save.dataset.save));
    const card = event.target.closest('[data-product]');
    if (card) {
      const p = products.find(p => p.id === Number(card.dataset.product));
      document.querySelector('#product-detail').innerHTML = `<img src="assets/${p.image}.jpg" alt="${p.name}"><div><span class="eyebrow">THE CONSIDERED COLLECTION</span><h2>${p.name}</h2><p>${p.description}</p><p class="detail-price">${price(p.price)}</p><p>${p.stock ? 'In stock. Ready for a new home.' : 'A little more patience. Currently out of stock.'}</p><button class="detail-save" data-save="${p.id}">${saved.has(p.id) ? 'Remove from' : 'Save to'} your collection</button><p class="preview-note">Sample piece in the interactive storefront preview.</p></div>`;
      detail.showModal();
    }
    if (event.target.closest('.detail-save')) { const button = event.target.closest('.detail-save'); button.textContent = `${saved.has(Number(button.dataset.save)) ? 'Remove from' : 'Save to'} your collection`; }
    if (event.target.closest('.product-close')) detail.close();
    if (event.target.closest('#reset-collection')) { const url = new URL(location.href); url.search = ''; location.assign(url); }
  });
  detail.addEventListener('click',event => { if (event.target === detail) detail.close(); });
  render();
})();
