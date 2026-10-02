(() => {
  const toggle = document.querySelector('.sfc-menu-toggle');
  const nav = document.querySelector('.sfc-nav');
  if (toggle && nav) toggle.addEventListener('click', () => { const open = nav.classList.toggle('is-open'); toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); });
})();
