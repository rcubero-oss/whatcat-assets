'use strict';
for (const field of document.querySelectorAll('textarea[data-word-limit]')) {
  const counter = document.getElementById(field.getAttribute('aria-describedby'));
  const limit = Number(field.dataset.wordLimit);
  const update = () => {
    const value = field.value.trim();
    const count = value ? value.split(/\s+/u).length : 0;
    const over = count > limit;
    counter.textContent = `${count} / ${limit} palabras` +
      (over ? ` · Superó el límite. Quite ${count - limit} ${count - limit === 1 ? 'palabra' : 'palabras'}.` :
        count === limit ? ' · Llegó al límite de 100 palabras.' : '');
    counter.dataset.state = over ? 'over' : count === limit ? 'limit' : 'normal';
    field.setCustomValidity(over ? 'La respuesta debe tener un máximo de 100 palabras.' : '');
    field.setAttribute('aria-invalid', String(over));
  };
  field.addEventListener('input', update);
  field.addEventListener('change', update);
  field.form?.addEventListener('reset', () => setTimeout(update, 0));
  update();
}
