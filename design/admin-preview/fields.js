// The field screens preview: the kind picker, label-first naming, and the blocks and repeater
// builder whose preview follows each field's width.
const $ = (selector) => document.querySelector(selector);
const icon = (name, cls = 'i') => `<svg class="${cls}"><use href="#i-${name}"/></svg>`;

const KINDS = [
    ['Text', [['text', 'type', 'Single line', 'A title, a name, a short phrase.'], ['textarea', 'text', 'Paragraphs', 'Plain, rich text or Markdown.'], ['url', 'link', 'Web address', 'A link to a page or another site.'], ['email', 'link', 'Email address', 'Checked when the page is saved.']]],
    ['Numbers and dates', [['integer', 'hash', 'Whole number', 'A count, a duration, a price in cents.'], ['decimal', 'hash', 'Decimal number', 'A price or a rating.'], ['date', 'cal', 'Date', 'With or without a time.'], ['time', 'cal', 'Time of day', 'Such as 09:30.']]],
    ['Choices', [['choice', 'list', 'Choice', 'One or more options from a list you set.'], ['toggle', 'toggle', 'On or off', 'Yes or no, such as "Featured".']]],
    ['Pages and media', [['page', 'file', 'Pages', 'Links to other pages, such as related courses.'], ['page_table', 'list', 'Table of pages', 'Pages made for this one, such as sessions.'], ['images', 'image', 'Images', 'With focus points and descriptions.'], ['files', 'file', 'Files', 'PDFs and documents.']]],
    ['Structure', [['repeater', 'repeat', 'Repeater', 'A list of items with the same fields.'], ['matrix', 'blocks', 'Blocks', 'A page built from blocks of different kinds.']]],
];

const WIDTHS = [[25, 'Quarter'], [33, 'Third'], [50, 'Half'], [66, 'Two thirds'], [75, 'Three quarters'], [100, 'Full']];

const BLOCKS = {
    text_image: { label: 'Text and image', icon: 'image', pages: 12, fields: [['Heading', 'heading', 'Single line', 'type', 100, false], ['Body', 'body', 'Paragraphs', 'text', 100, true, true], ['Image', 'image', 'Images', 'image', 50, true], ['Image position', 'image_position', 'Choice', 'list', 50, false], ['Background', 'background', 'Choice', 'list', 50, false], ['Padding', 'padding', 'Choice', 'list', 50, false]] },
    quote: { label: 'Quote', icon: 'quote', pages: 5, fields: [['Quote', 'quote_text', 'Paragraphs', 'text', 100, true, true], ['Who said it', 'quote_author', 'Single line', 'type', 66, false], ['Role', 'quote_role', 'Single line', 'type', 33, false]] },
    gallery: { label: 'Gallery', icon: 'image', pages: 3, fields: [['Images', 'images', 'Images', 'image', 100, true, true], ['Columns', 'gallery_columns', 'Choice', 'list', 33, false]] },
    call: { label: 'Call to action', icon: 'link', pages: 9, fields: [['Heading', 'heading', 'Single line', 'type', 100, true], ['Button text', 'cta_text', 'Single line', 'type', 50, true], ['Link', 'cta_link', 'Web address', 'link', 50, true]] },
};
const CREDITS = { label: 'Credit', fields: [['Partner', 'partner', 'Single line', 'type', 50, true], ['Role', 'role', 'Single line', 'type', 50, true], ['Logo', 'logo', 'Images', 'image', 33, false], ['Website', 'website', 'Web address', 'link', 66, false]] };
let current = 'text_image';
let isRepeater = false;

function renderKinds() {
    $('#kinds').innerHTML = KINDS.map(([group, kinds]) => `<div class="kind-group"><span>${group}</span><div class="kind-grid">${kinds.map(([key, ic, name, text]) =>
        `<button type="button" class="kind" role="radio" data-kind="${key}" aria-checked="${key === 'textarea'}">${icon(ic)}<b>${name}</b><small>${text}</small></button>`).join('')}</div></div>`).join('');
    $('#kinds').insertAdjacentHTML('beforeend', '<button type="button" class="btn ghost change-kind">Change what it holds</button>');
    $('#kinds').classList.add('chosen');
    document.querySelectorAll('.kind').forEach((kind) => kind.addEventListener('click', () => {
        document.querySelectorAll('.kind').forEach((other) => other.setAttribute('aria-checked', String(other === kind)));
        $('#kind-settings h3').textContent = kind.querySelector('b').textContent;
        $('#kinds').classList.add('chosen');
    }));
    $('.change-kind').addEventListener('click', () => $('#kinds').classList.remove('chosen'));
}

function block() { return isRepeater ? CREDITS : BLOCKS[current]; }

function renderTypes() {
    $('#type-list').innerHTML = Object.entries(BLOCKS).map(([key, type]) => `<li><button type="button" class="type" data-type="${key}" aria-current="${key === current}">${icon('grip', 'i grip')}${icon(type.icon, 'i ic')}<b>${type.label}</b><small>${type.fields.length} fields · on ${type.pages} pages</small></button></li>`).join('');
    document.querySelectorAll('.type').forEach((button) => button.addEventListener('click', () => { current = button.dataset.type; renderBuilder(); }));
}

function renderBuilder() {
    if (!isRepeater) renderTypes();
    $('#t-label').value = block().label;
    $('#subfields').innerHTML = block().fields.map(([label, name, kind, ic, width, required], index) => `<li class="sub">${icon('grip', 'i grip')}${icon(ic, 'i ic')}
        <span class="who"><b>${label}${required ? '<span class="req" aria-label="required">*</span>' : ''}</b><small><span class="mono">${name}</span> · ${kind}</small></span>
        <label class="req-toggle"><input type="checkbox" ${required ? 'checked' : ''} data-required="${index}">Required</label>
        <label class="width" title="Width in the page editor"><i style="--w:${width}%"></i><select data-width="${index}" aria-label="Width of ${label}">${WIDTHS.map(([value, word]) => `<option value="${value}" ${value === width ? 'selected' : ''}>${word}</option>`).join('')}</select></label>
        <button type="button" class="more" aria-label="Label, help and remove for ${label}">${icon('dots')}</button></li>`).join('');
    document.querySelectorAll('[data-width]').forEach((select) => select.addEventListener('change', () => {
        block().fields[select.dataset.width][4] = Number(select.value);
        renderBuilder();
    }));
    document.querySelectorAll('[data-required]').forEach((box) => box.addEventListener('change', () => {
        block().fields[box.dataset.required][5] = box.checked;
        renderBuilder();
    }));
    renderMock();
}

function renderMock() {
    const header = isRepeater ? 'Studio Lumen · Lighting' : `${block().label}`;
    $('#block-mock').innerHTML = `<div class="bar">${icon('grip', 'i grip')}${header}</div><div class="grid">${block().fields.map(([label, , , , width, required, tall]) =>
        `<span class="cell${tall ? ' tall' : ''}" style="--w:${width}%"><span>${label}${required ? ' *' : ''}</span><i></i></span>`).join('')}</div>`;
}

const SCREENS = {
    new: { crumbs: '<a href="#new">Fields</a>' + icon('chev') + '<strong>New field</strong>' },
    matrix: { crumbs: '<a href="#new">Fields</a>' + icon('chev') + '<strong>Page blocks</strong>' },
    repeater: { crumbs: '<a href="#new">Fields</a>' + icon('chev') + '<strong>Credits</strong>' },
};

function route() {
    const screen = SCREENS[location.hash.slice(1)] ? location.hash.slice(1) : 'new';
    $('#crumbs').innerHTML = SCREENS[screen].crumbs;
    $('#screen-new').hidden = screen !== 'new';
    $('#screen-items').hidden = screen === 'new';
    isRepeater = screen === 'repeater';
    $('#builder').classList.toggle('single', isRepeater);
    $('#items-title').textContent = isRepeater ? 'Credits' : 'Page blocks';
    $('#items-name').textContent = isRepeater ? 'credits' : 'page_blocks';
    $('#items-kind').textContent = isRepeater ? 'Repeater' : 'Blocks';
    $('#items-tab').innerHTML = isRepeater ? 'Fields of each item <span class="badge">4</span>' : 'Block types <span class="badge">4</span>';
    $('#header-field label').textContent = isRepeater ? 'Header of a closed item' : 'Header of a closed block';
    $('#t-header').value = isRepeater ? '{partner} · {role}' : '{heading}';
    $('#limits-noun').textContent = 'Pages have';
    document.querySelectorAll('#limits .unit').forEach((unit) => { unit.textContent = isRepeater ? 'items' : 'blocks'; });
    $('#l-add').placeholder = isRepeater ? 'Add a credit' : 'Add a block';
    const noun = isRepeater ? 'item' : 'block';
    document.querySelectorAll('[data-noun]').forEach((word) => { word.textContent = word.dataset.noun.replaceAll('block', noun).replaceAll('Block', noun[0].toUpperCase() + noun.slice(1)); });
    $('#ft-label').value = isRepeater ? 'Credits' : 'Page blocks';
    $('#ft-name').textContent = isRepeater ? 'credits' : 'page_blocks';
    showTab('builder');
    document.querySelectorAll('[data-screen]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.screen === screen)));
    renderBuilder();
}

function showTab(tab) {
    $('#builder').hidden = tab !== 'builder';
    $('#fieldtab').hidden = tab !== 'field';
    $('#limits').hidden = tab !== 'limits';
    document.querySelectorAll('[data-tab]').forEach((link) => link.setAttribute('aria-current', link.dataset.tab === tab ? 'page' : 'false'));
}

document.querySelectorAll('[data-tab]').forEach((link) => link.addEventListener('click', (event) => { event.preventDefault(); showTab(link.dataset.tab); }));
document.querySelectorAll('[data-screen]').forEach((button) => button.addEventListener('click', () => { location.hash = button.dataset.screen; }));
document.querySelectorAll('[data-theme-pick]').forEach((button) => button.addEventListener('click', () => {
    const theme = button.dataset.themePick;
    if (theme === 'system') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', theme);
    button.parentElement.querySelectorAll('button').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
}));
document.querySelectorAll('.seg.fit button').forEach((button) => button.addEventListener('click', () => {
    button.parentElement.querySelectorAll('button').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
}));
$('#f-label').addEventListener('input', (event) => {
    const name = event.target.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1');
    $('#f-name').value = name; $('#mock-name').textContent = name || 'name'; $('#mock-label').textContent = event.target.value || 'Label';
});
$('#menu-btn').addEventListener('click', () => $('#admin').classList.toggle('nav-open'));
window.addEventListener('hashchange', route);
renderKinds();
route();
