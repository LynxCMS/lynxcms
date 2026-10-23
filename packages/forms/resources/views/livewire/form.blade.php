{{-- A form on a page (lynxcms/forms). Publish the views (lynx-forms-views) to restyle them; the
     classes are hooks for resources/css/forms.css, which the host app may import and theme. --}}
<div class="lynx-form" data-form="{{ $definition->key() }}">
    @if ($isSent)
        <div class="lynx-form-sent" role="status">{{ $definition->text('success')->in() }}</div>
    @elseif ($refusal)
        {{-- A form members answer once each (Form::oncePerUser()): a guest, or a second answer --}}
        <div class="lynx-form-sent" role="status">
            {{ $refusal }}
            @if (auth()->guest() && Route::has('login'))
                <a href="{{ route('login') }}" class="lynx-form-link">{{ __('lynx-forms::forms.sign_in') }}</a>
            @endif
        </div>
    @else
        <div @class(['lynx-form-layout', 'has-aside' => $aside !== null])>
            <form wire:submit="{{ $isLastStep ? 'submit' : 'next' }}" class="lynx-form-body" novalidate>
                <x-honeypot livewire-model="extraFields" />

                @if (count($steps) > 1)
                    <div class="lynx-form-steps">
                        <p class="lynx-form-step-count">{{ __('lynx-forms::forms.step', ['current' => $step + 1, 'total' => count($steps)]) }}</p>
                        <ol class="lynx-form-step-list">
                            @foreach ($steps as $index => $formStep)
                                <li @class(['is-current' => $index === $step, 'is-done' => $index < $step]) @if ($index === $step) aria-current="step" @endif>{{ $formStep->title->in() }}</li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if ($errors->isNotEmpty())
                    <p class="lynx-form-problem" role="alert">{{ __('lynx-forms::forms.check_fields') }}</p>
                @endif
                @if ($problem)
                    <p class="lynx-form-problem" role="alert">{{ $problem }}</p>
                @endif

                @if ($summary !== [])
                    <section class="lynx-form-summary" aria-label="{{ __('lynx-forms::forms.summary') }}">
                        <p class="lynx-form-help">{{ __('lynx-forms::forms.summary') }}</p>
                        <dl>
                            @foreach ($summary as $line)
                                <div><dt>{{ $line['label'] }}</dt><dd>{{ $line['value'] }}</dd></div>
                            @endforeach
                        </dl>
                    </section>
                @endif

                <div class="lynx-form-fields">
                    @foreach ($fields as $field)
                        @include('lynx-forms::fields.field', ['field' => $field, 'path' => 'data.'.$field->name])
                    @endforeach
                </div>

                <div class="lynx-form-actions">
                    @if ($step > 0)
                        <button type="button" class="lynx-form-button is-secondary" wire:click="back">{{ __('lynx-forms::forms.back') }}</button>
                    @endif
                    <button type="submit" class="lynx-form-button" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit,next">{{ $isLastStep ? __('lynx-forms::forms.send') : __('lynx-forms::forms.next') }}</span>
                        <span wire:loading wire:target="submit,next">{{ $isLastStep ? __('lynx-forms::forms.sending') : '…' }}</span>
                    </button>
                </div>
            </form>

            @if ($aside !== null)
                <aside class="lynx-form-aside">@include($aside)</aside>
            @endif
        </div>
    @endif
</div>
