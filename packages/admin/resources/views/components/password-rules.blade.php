@props(['for' => 'password', 'confirm' => null])
{{--
    The app's password rules (Password::defaults()), ticked in the browser as the user types in
    the input #{{ $for }}. With "confirm", one more line says whether #{{ $confirm }} matches.
    The server checks the rules again when the form is sent.
--}}
@php($rules = (new \LynxCms\Admin\Auth\PasswordRules)->all())
<ul id="{{ $for }}-rules" data-password-rules class="grid gap-1 text-sm" aria-label="{{ __('lynx-admin::auth.password_rules.heading') }}"
    x-data="{
        rules: @js($rules),
        value: '',
        repeat: '',
        init() {
            const input = document.getElementById(@js($for));
            const repeat = @js($confirm) ? document.getElementById(@js($confirm)) : null;
            input?.addEventListener('input', () => this.value = input.value);
            repeat?.addEventListener('input', () => this.repeat = repeat.value);
        },
        matches() { return this.repeat !== '' && this.repeat === this.value; },
        met(rule) {
            const length = [...this.value].length;
            if (rule.min) return length >= rule.min;
            if (rule.max) return length > 0 && length <= rule.max;
            if (rule.pattern) return new RegExp(rule.pattern, 'u').test(this.value);
            return null;
        },
    }">
    @foreach ($rules as $index => $rule)
        <li class="flex items-center gap-2" x-bind:class="met(rules[{{ $index }}]) ? 'text-foreground' : 'text-muted-foreground'">
            @if ($rule['key'] === 'uncompromised')
                <x-lucide-shield class="size-4 shrink-0" aria-hidden="true" />
            @else
                <x-lucide-circle-check class="size-4 shrink-0 text-success" aria-hidden="true" x-show="met(rules[{{ $index }}])" x-cloak />
                <x-lucide-circle class="size-4 shrink-0" aria-hidden="true" x-show="! met(rules[{{ $index }}])" />
                <span class="sr-only" x-text="met(rules[{{ $index }}]) ? @js(__('lynx-admin::auth.password_rules.met')) : @js(__('lynx-admin::auth.password_rules.not_met'))"></span>
            @endif
            <span>{{ $rule['label'] }}</span>
        </li>
    @endforeach
    @if ($confirm)
        <li class="flex items-center gap-2" x-bind:class="matches() ? 'text-foreground' : 'text-muted-foreground'">
            <x-lucide-circle-check class="size-4 shrink-0 text-success" aria-hidden="true" x-show="matches()" x-cloak />
            <x-lucide-circle class="size-4 shrink-0" aria-hidden="true" x-show="! (matches())" />
            <span>{{ __('lynx-admin::auth.password_rules.match') }}</span>
        </li>
    @endif
</ul>
