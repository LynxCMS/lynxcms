// Lynx page cache (ADR-0064). A stored page holds a placeholder instead of the visitor's CSRF
// token, and <lynx-island> placeholders for parts rendered per visitor: fetch both in one request,
// swap the islands in, and put the token exactly where the placeholder is. Scripts can wait:
//   const token = await window.lynxCsrfToken();
//   document.addEventListener('lynx:cache-refreshed', (event) => event.detail.token);
//
// A visitor arriving on a stored page has no session yet, so every request the page makes to
// Laravel while it loads (ours, a favicon or image Laravel answers, an API call) starts one, and
// the browser keeps whichever session cookie came last: a token copied into the page can belong
// to another session. The XSRF-TOKEN cookie always comes with the session cookie, so requests that
// change data send it (X-XSRF-TOKEN) instead of the copied token (header or _token in the body),
// and plain forms fetch a fresh token when the session changed since theirs.
(() => {
    const placeholder = 'lynx-cache-csrf-placeholder';
    const script = document.currentScript;
    const endpoint = script && script.dataset.endpoint;
    let resolveToken;
    const token = new Promise((resolve) => {
        resolveToken = resolve;
    });
    let current = placeholder;
    let tokenCookie = null;

    window.lynxCsrfToken = () => token;

    const xsrfCookie = () => {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

        return match ? decodeURIComponent(match[1]) : null;
    };

    const sameOrigin = (url) => {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch {
            return false;
        }
    };

    const changesData = (method) => !/^(GET|HEAD|OPTIONS)$/i.test(method || 'GET');

    const holdsPlaceholder = () =>
        document.querySelector(`meta[content="${placeholder}"], input[value="${placeholder}"], [data-csrf="${placeholder}"]`) !== null ||
        (window.livewireScriptConfig && window.livewireScriptConfig.csrf === placeholder);

    // Puts the fresh token wherever the page holds the one it replaces.
    const replace = (fresh) => {
        document.querySelectorAll(`meta[content="${current}"]`).forEach((meta) => meta.setAttribute('content', fresh));
        document.querySelectorAll(`input[value="${current}"]`).forEach((input) => (input.value = fresh));
        document.querySelectorAll(`[data-csrf="${current}"]`).forEach((element) => element.setAttribute('data-csrf', fresh));

        if (window.livewireScriptConfig && window.livewireScriptConfig.csrf === current) {
            window.livewireScriptConfig.csrf = fresh;
        }

        current = fresh;
    };

    // Livewire and Alpine start components added to the page on their own, and run the @script
    // blocks the components carry in their effects.
    const fill = (islands, html) => {
        islands.forEach((island, index) => {
            if (typeof html[index] === 'string') {
                island.outerHTML = html[index];
            }
        });
    };

    // Whether the page already has this script, stylesheet or Livewire asset.
    const hasAsset = (node, key) => {
        const src = node.getAttribute('src');
        const href = node.getAttribute('href');

        return (src !== null && document.querySelector(`script[src="${CSS.escape(src)}"]`) !== null) ||
            (href !== null && document.querySelector(`link[href="${CSS.escape(href)}"]`) !== null) ||
            document.head.querySelector(`[data-lynx-asset="${CSS.escape(key)}"]`) !== null;
    };

    // A script parsed from HTML never runs, so it is made again; the promise waits for its file.
    const addAsset = (node, key) =>
        new Promise((resolve) => {
            let element = node;

            if (node.tagName === 'SCRIPT') {
                element = document.createElement('script');
                [...node.attributes].forEach((attribute) => element.setAttribute(attribute.name, attribute.value));
                element.textContent = node.textContent;
            }

            element.setAttribute('data-lynx-asset', key);

            if (element.tagName === 'SCRIPT' && element.src) {
                element.onload = () => resolve();
                element.onerror = () => resolve();
                document.head.appendChild(element);

                return;
            }

            document.head.appendChild(element);
            resolve();
        });

    // The @assets of Livewire components inside islands, added to <head> one after another before
    // the islands go in, as Livewire does, so their @script blocks can use what they load.
    const addAssets = async (assets) => {
        for (const [key, html] of Object.entries(assets || {})) {
            const parsed = new DOMParser().parseFromString(html, 'text/html');

            for (const node of [...parsed.head.children, ...parsed.body.children]) {
                if (!hasAsset(node, key)) {
                    await addAsset(node, key);
                }
            }
        }
    };

    // Asks for the session's token (and the islands' HTML); resolves with the token or null.
    const refresh = (islands = []) => {
        const query = new URLSearchParams({ url: window.location.href.split('#')[0] });
        islands.forEach((island) => query.append('islands[]', island.dataset.island));

        return fetch(`${endpoint}?${query}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : null))
            .then(async (data) => {
                await addAssets(data && data.assets);
                fill(islands, (data && data.islands) || []);

                if (data && data.token) {
                    replace(data.token);
                    tokenCookie = xsrfCookie();
                }

                return (data && data.token) || null;
            })
            .catch(() => null);
    };

    // A body without the copied token: Laravel reads _token before any header. JSON (Livewire's
    // updates), FormData and URL-encoded bodies; anything else goes as it is.
    const withoutToken = (body) => {
        if (body instanceof FormData || body instanceof URLSearchParams) {
            body.delete('_token');

            return body;
        }

        if (typeof body === 'string' && body.includes('"_token"')) {
            try {
                const data = JSON.parse(body);
                delete data._token;

                return JSON.stringify(data);
            } catch {
                return body;
            }
        }

        return body;
    };

    // fetch(): Livewire and fetch-based forms.
    const guardFetch = () => {
        const original = window.fetch;

        window.fetch = (input, init = {}) => {
            const request = input instanceof Request ? input : null;
            const cookie = xsrfCookie();

            if (cookie && sameOrigin(request ? request.url : String(input)) && changesData(init.method || (request && request.method))) {
                const headers = new Headers(init.headers || (request ? request.headers : undefined));
                headers.delete('X-CSRF-TOKEN');
                headers.set('X-XSRF-TOKEN', cookie);
                init = { ...init, headers, ...('body' in init ? { body: withoutToken(init.body) } : {}) };
            }

            return original.call(window, input, init);
        };
    };

    // XMLHttpRequest: axios (Precognition) and Livewire's uploads. axios may set X-XSRF-TOKEN
    // itself, so the header is set once.
    const guardXhr = () => {
        const open = XMLHttpRequest.prototype.open;
        const setHeader = XMLHttpRequest.prototype.setRequestHeader;
        const send = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.lynxGuarded = sameOrigin(url) && changesData(method);
            this.lynxXsrfSent = false;

            return open.call(this, method, url, ...rest);
        };

        XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            const header = String(name).toLowerCase();

            if (!this.lynxGuarded || !xsrfCookie() || (header !== 'x-csrf-token' && header !== 'x-xsrf-token')) {
                return setHeader.call(this, name, value);
            }

            this.lynxXsrfSent || setHeader.call(this, 'X-XSRF-TOKEN', xsrfCookie());
            this.lynxXsrfSent = true;
        };

        XMLHttpRequest.prototype.send = function (body) {
            if (this.lynxGuarded && xsrfCookie()) {
                this.lynxXsrfSent || setHeader.call(this, 'X-XSRF-TOKEN', xsrfCookie());
                this.lynxXsrfSent = true;
                body = withoutToken(body);
            }

            return send.call(this, body);
        };
    };

    // Plain forms send their _token field, which Laravel reads first: when the session changed
    // since that token came, fetch the session's token, then send the form again the usual way.
    // Forms that scripts send (Livewire, Alpine) go through fetch or XMLHttpRequest above.
    const guardForms = () => {
        const scripted = (form) => form.getAttributeNames().some((name) => /^(wire:submit|x-on:submit|@submit)/.test(name));

        document.addEventListener('submit', (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || event.defaultPrevented || scripted(form) || form.dataset.lynxResubmit) {
                return;
            }

            if (!form.querySelector('input[name="_token"]') || xsrfCookie() === tokenCookie) {
                return;
            }

            event.preventDefault();
            refresh().then(() => {
                form.dataset.lynxResubmit = '1';
                form.requestSubmit(event.submitter || undefined);
                delete form.dataset.lynxResubmit;
            });
        }, true);
    };

    const done = (fresh) => {
        resolveToken(fresh);
        document.dispatchEvent(new CustomEvent('lynx:cache-refreshed', { detail: { token: fresh } }));
    };

    const islands = Array.from(document.querySelectorAll('lynx-island[data-island]'));

    // Not a stored page: the token in the page is the visitor's own, and islands rendered in place.
    if (!endpoint || (!holdsPlaceholder() && islands.length === 0)) {
        const meta = document.querySelector('meta[name="csrf-token"]');
        done(meta ? meta.getAttribute('content') : null);

        return;
    }

    guardFetch();
    guardXhr();
    guardForms();
    refresh(islands).then(done);
})();
