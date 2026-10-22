{{-- One entry: a link, or plain text for section labels. $tag lets presets use a button for sections. --}}
@php
    $tag = ($tag ?? 'span') === 'button' ? 'button' : 'span';
    $icons = app(\LynxCms\Menus\View\IconRenderer::class);
    $content = $icons->render($item->iconBefore, 'before').e($item->label).$icons->render($item->iconAfter, 'after');
@endphp
@if ($item->hasUrl())
<a href="{{ $item->url }}" @if ($item->target) target="{{ $item->target }}" @endif @if ($item->rel) rel="{{ $item->rel }}" @endif @if ($item->isActive) aria-current="page" @endif @class(['lynx-menu-link', (string) $item->class => $item->class !== null])>{!! $content !!}</a>
@else
<{{ $tag }} @class(['lynx-menu-section', (string) $item->class => $item->class !== null]) @if ($tag === 'button') type="button" aria-haspopup="true" @endif>{!! $content !!}</{{ $tag }}>
@endif
