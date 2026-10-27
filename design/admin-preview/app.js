// The preview's controls: screen, theme, branding, the editor's language and the tree's toggles.
const admin = document.getElementById('admin');
const crumbs = document.getElementById('crumbs');
const CRUMBS = {
    pages: '<strong>Pages</strong>',
    editor: '<a href="#pages">Pages</a><svg class="i"><use href="#i-chev"/></svg><a href="#pages">Courses</a><svg class="i"><use href="#i-chev"/></svg><strong>Budgets for small projects</strong>',
};

function press(group, active) {
    group.querySelectorAll('button').forEach((button) => button.setAttribute('aria-pressed', String(button === active)));
}

const FONTS = {
    inter: '"Inter"',
    geist: '"Geist"',
    manrope: '"Manrope"',
    figtree: '"Figtree"',
    atkinson: '"Atkinson Hyperlegible Next"',
};
let currentScreen = 'pages';
let currentFont = 'geist';

function applyFont(key) {
    currentFont = key;
    document.documentElement.style.setProperty('--sans', `${FONTS[key]}, ui-sans-serif, system-ui, sans-serif`);
    press(document.querySelector('[aria-label="Font"]'), document.querySelector(`[data-font="${key}"]`));
}

// The hash holds the screen and the font (#editor-geist), so two tabs can compare fonts side by side.
// Links inside the admin carry only the screen, and keep the font in use.
function route() {
    const [name, font] = location.hash.slice(1).split('-');
    showScreen(name);
    if (FONTS[font]) applyFont(font);
}

function showScreen(name) {
    const screen = CRUMBS[name] ? name : 'pages';
    currentScreen = screen;
    document.getElementById('screen-pages').hidden = screen !== 'pages';
    document.getElementById('screen-editor').hidden = screen !== 'editor';
    crumbs.innerHTML = CRUMBS[screen];
    press(document.querySelector('[aria-label="Screen"]'), document.querySelector(`[data-screen="${screen}"]`));
    admin.classList.remove('nav-open');
}

document.querySelectorAll('[data-screen]').forEach((button) => {
    button.addEventListener('click', () => { location.hash = `${button.dataset.screen}-${currentFont}`; });
});
document.querySelectorAll('[data-font]').forEach((button) => {
    button.addEventListener('click', () => { location.hash = `${currentScreen}-${button.dataset.font}`; });
});
window.addEventListener('hashchange', route);
route();

document.querySelectorAll('[data-theme-pick]').forEach((button) => {
    button.addEventListener('click', () => {
        const theme = button.dataset.themePick;
        if (theme === 'system') document.documentElement.removeAttribute('data-theme');
        else document.documentElement.setAttribute('data-theme', theme);
        press(button.parentElement, button);
    });
});

document.querySelectorAll('[data-brand]').forEach((button) => {
    button.addEventListener('click', () => {
        const isClient = button.dataset.brand === 'client';
        admin.classList.toggle('client', isClient);
        document.querySelector('.brand-name').textContent = isClient ? 'Castor & Otters' : 'Lynx';
        press(button.parentElement, button);
    });
});

document.querySelectorAll('[data-lang]').forEach((button) => {
    button.addEventListener('click', () => {
        const isPortuguese = button.dataset.lang === 'pt';
        const editor = document.getElementById('screen-editor');
        editor.classList.toggle('lang-pt', isPortuguese);
        document.getElementById('f-title').value = isPortuguese ? 'Orçamentos para pequenos projetos' : 'Budgets for small projects';
        document.getElementById('f-summary').classList.toggle('follows-en', isPortuguese);
        document.getElementById('f-body').classList.toggle('follows-en', isPortuguese);
        // The preview switches every field together; in the admin each field's tabs are its own.
        document.querySelectorAll('[data-lang]').forEach((tab) => tab.setAttribute('aria-pressed', String(tab.dataset.lang === button.dataset.lang)));
    });
});

const coursesToggle = document.getElementById('twist-courses');
coursesToggle.addEventListener('click', () => {
    const isOpen = coursesToggle.getAttribute('aria-expanded') === 'true';
    coursesToggle.setAttribute('aria-expanded', String(!isOpen));
    coursesToggle.setAttribute('aria-label', `${isOpen ? 'Expand' : 'Collapse'} Courses`);
    document.querySelector('.courses').hidden = isOpen;
});

document.querySelectorAll('.row[role="treeitem"]').forEach((row) => {
    row.addEventListener('click', (event) => {
        if (event.target.closest('a, button')) return;
        document.querySelectorAll('.row[aria-selected="true"]').forEach((selected) => selected.removeAttribute('aria-selected'));
        row.setAttribute('aria-selected', 'true');
    });
});

document.querySelectorAll('[data-view]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById('screen-pages').classList.toggle('is-simple', button.dataset.view === 'simple');
        press(button.parentElement, button);
    });
});

document.querySelectorAll('[data-hints]').forEach((button) => {
    button.addEventListener('click', () => {
        admin.classList.toggle('hints-on', button.dataset.hints === 'on');
        press(button.parentElement, button);
    });
});

const moreToggle = document.getElementById('more-acts');
moreToggle.addEventListener('click', () => {
    const isOpen = moreToggle.getAttribute('aria-expanded') === 'true';
    moreToggle.setAttribute('aria-expanded', String(!isOpen));
    document.querySelector('.more-acts').hidden = isOpen;
});

document.querySelectorAll('.chip').forEach((chip) => {
    chip.addEventListener('click', () => press(chip.parentElement, chip));
});

document.getElementById('menu-btn').addEventListener('click', () => admin.classList.toggle('nav-open'));
