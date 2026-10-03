(() => {
  const root = document.getElementById('uwsb-products');
  if (!root) {
    document.querySelectorAll('.uwsb-traits input[type="range"]').forEach((input) => {
      input.addEventListener('input', () => { if (input.nextElementSibling) input.nextElementSibling.value = input.value; });
    });
    return;
  }

  const renumber = () => {
    [...root.querySelectorAll('[data-product]')].forEach((card, index) => {
      const number = card.querySelector('[data-product-number]');
      if (number) number.textContent = String(index + 1);
      card.querySelectorAll('[name]').forEach((el) => {
        el.name = el.name.replace(/products\[\d+\]/, 'products[' + index + ']');
      });
    });
  };

  const bindRanges = (scope = document) => {
    scope.querySelectorAll('.uwsb-traits input[type="range"]').forEach((input) => {
      input.addEventListener('input', () => { if (input.nextElementSibling) input.nextElementSibling.value = input.value; });
    });
  };

  document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-uwsb-add-product]');
    if (add) {
      const first = root.querySelector('[data-product]');
      if (!first) return;
      const clone = first.cloneNode(true);
      clone.querySelectorAll('input,textarea').forEach((el) => {
        if (el.name.includes('[currency]')) el.value = 'UAH';
        else el.value = '';
        if (el.hasAttribute('required')) el.removeAttribute('required');
      });
      root.appendChild(clone);
      renumber();
      return;
    }

    const remove = event.target.closest('[data-uwsb-remove-product]');
    if (remove) {
      const cards = root.querySelectorAll('[data-product]');
      if (cards.length <= 1) return;
      remove.closest('[data-product]').remove();
      renumber();
    }
  });

  bindRanges();
  renumber();
})();