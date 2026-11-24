{{-- Cloudflare Turnstile (ADR-0148): the widget hands its token to the component, and resets after each check. --}}
<div class="lynx-form-turnstile" wire:ignore data-turnstile
     x-data x-init="
        const render = () => window.turnstile.render($el, {
            sitekey: @js($siteKey),
            language: 'auto',
            callback: (token) => { $wire.turnstileToken = token },
            'expired-callback': () => { $wire.turnstileToken = '' },
        });
        window.turnstile ? render() : window.addEventListener('lynx-turnstile-ready', render, { once: true });
        $wire.on('lynx-turnstile-reset', () => window.turnstile?.reset($el));
     "></div>
@once
    <script>window.lynxTurnstileReady = () => window.dispatchEvent(new Event('lynx-turnstile-ready'));</script>
    <script src="{{ \LynxCms\Forms\Spam\Turnstile::SCRIPT }}" async defer></script>
@endonce
