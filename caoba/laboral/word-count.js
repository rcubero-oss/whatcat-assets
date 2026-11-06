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

// Keep native constraints, but present all errors together and beside each field.
const applicationForm = document.querySelector('form[enctype="multipart/form-data"]');
if (applicationForm) {
  const fields = [...applicationForm.querySelectorAll('input:not([type="hidden"]):not([name="website"]),select,textarea')];
  applicationForm.noValidate = true;
  const messageFor = field => {
    if (field.name === 'cv' && field.files.length) {
      const file = field.files[0];
      if (file.size > 5 * 1024 * 1024) return 'Adjunte un PDF de hasta 5 MB.';
      if (file.type && file.type !== 'application/pdf') return 'El currículum debe ser un archivo PDF válido.';
    }
    if (field.validity.valid) return '';
    if (field.validity.customError) return field.validationMessage;
    if (field.validity.valueMissing) return field.type === 'checkbox' ? 'Confirme la autorización para procesar su postulación.' : 'Complete este campo obligatorio.';
    if (field.name === 'email') return 'Ingrese un correo válido. Este formulario admite direcciones sin tildes ni ñ; use una dirección alternativa si corresponde.';
    if (field.name === 'linkedin') return 'Copie el enlace completo de su perfil de LinkedIn o deje este campo vacío.';
    return 'Revise el valor de este campo.';
  };
  const showField = (field, message) => {
    const id = `${field.id}-error`;
    let error = document.getElementById(id);
    if (!error) {
      error = document.createElement('small'); error.id = id; error.className = 'field-error';
      field.insertAdjacentElement('afterend', error);
      field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), id].filter(Boolean).join(' '));
    }
    error.textContent = message; error.hidden = !message;
    field.setAttribute('aria-invalid', String(Boolean(message)));
  };
  for (const field of fields) {
    showField(field, field.dataset.serverError || '');
    field.addEventListener('input', () => { field.dataset.serverError = ''; showField(field, messageFor(field)); });
    field.addEventListener('change', () => showField(field, messageFor(field)));
  }
  applicationForm.addEventListener('submit', event => {
    const errors = fields.map(field => ({field, message:messageFor(field)})).filter(item => item.message);
    for (const field of fields) showField(field, messageFor(field));
    let summary = document.getElementById('form-errors');
    if (!errors.length) { if (summary) summary.hidden = true; return; }
    event.preventDefault();
    if (!summary) {
      summary = document.createElement('div');summary.id='form-errors';summary.className='notice';
      summary.setAttribute('role','alert');summary.tabIndex=-1;applicationForm.before(summary);
    }
    summary.hidden=false;summary.replaceChildren();
    const heading=document.createElement('p');heading.textContent=`Hay ${errors.length} ${errors.length === 1 ? 'campo por revisar' : 'campos por revisar'}. Sus respuestas siguen en el formulario.`;summary.append(heading);
    const list=document.createElement('ul');
    for(const {field,message} of errors) {
      const item=document.createElement('li');const link=document.createElement('a');
      const label=/^q[0-6]$/.test(field.name) ? `Pregunta ${Number(field.name.slice(1))+1}` : ({name:'Nombre completo',email:'Correo electrónico',phone:'Teléfono',profile:'Perfil',education:'Formación',availability:'Fecha de incorporación',travel:'Disponibilidad para movilizarse',cv:'Currículum PDF',linkedin:'LinkedIn',consent:'Autorización de datos'}[field.name] || field.name);
      link.href=`#${field.id}`;link.textContent=`${label.slice(0,160)}: ${message}`;
      link.addEventListener('click',()=>field.focus());item.append(link);list.append(item);
    }
    summary.append(list);summary.focus();
  });
  const summary=document.getElementById('form-errors');
  if(summary) {summary.focus();summary.querySelectorAll('a[href^="#field-"]').forEach(link=>link.addEventListener('click',()=>document.getElementById(link.hash.slice(1))?.focus()));}
}
