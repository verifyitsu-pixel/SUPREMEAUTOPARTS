(() => {
  const menu = document.querySelector('[data-menu-toggle]');
  const nav = document.querySelector('#primary-nav');
  if (menu && nav) menu.addEventListener('click', () => {
    const open = menu.getAttribute('aria-expanded') === 'true';
    menu.setAttribute('aria-expanded', String(!open));
    nav.classList.toggle('is-open', !open);
  });

  const form = document.querySelector('[data-live-search]');
  const results = form?.querySelector('[data-search-results]');
  const input = form?.querySelector('input[type="search"]');
  let timer;
  input?.addEventListener('input', () => {
    clearTimeout(timer);
    const query = input.value.trim();
    if (query.length < 2) { results.hidden = true; results.replaceChildren(); return; }
    timer = setTimeout(async () => {
      try {
        const response = await fetch(`${saStore.restUrl}search?q=${encodeURIComponent(query)}`);
        if (!response.ok) return;
        const items = await response.json();
        results.innerHTML = items.length ? items.map(item => `<a class="search-result" href="${escapeUrl(item.url)}"><img src="${escapeUrl(item.image)}" alt=""><span>${escapeHtml(item.title)}</span><small>${item.price || ''}</small></a>`).join('') : '<span class="search-result">No matching parts</span>';
        results.hidden = false;
      } catch (_) { results.hidden = true; }
    }, 220);
  });
  document.addEventListener('click', event => { if (form && !form.contains(event.target)) results.hidden = true; });

  document.querySelectorAll('[data-sa-vehicle-selector]').forEach(selector => {
    const fields = ['vehicle_year','vehicle_make','vehicle_model'].map(name => selector.elements[name]);
    const save = selector.querySelector('[data-save-vehicle]');
    const update = () => { save.hidden = !fields.every(field => field?.value); };
    fields.forEach(field => field?.addEventListener('change', update)); update();
    save?.addEventListener('click', () => {
      const vehicle = Object.fromEntries(fields.map(field => [field.name, field.value]));
      const garage = JSON.parse(localStorage.getItem('saGarage') || '[]').filter(item => JSON.stringify(item) !== JSON.stringify(vehicle));
      localStorage.setItem('saGarage', JSON.stringify([vehicle, ...garage].slice(0, 5)));
      save.textContent = 'Saved to garage ✓';
    });
  });
  function escapeHtml(value='') { const el=document.createElement('span'); el.textContent=value; return el.innerHTML; }
  function escapeUrl(value='') { try { const u=new URL(value,location.origin); return ['http:','https:'].includes(u.protocol)?u.href:''; } catch { return ''; } }
})();
