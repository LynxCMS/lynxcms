<ul @class(['lynx-menu-items', 'lynx-menu-level-'.$level])>
    @foreach ($items as $item)
        <li @class(['lynx-menu-item', 'has-children' => $item->hasChildren(), 'is-active' => $item->isActive, 'is-active-trail' => $item->isInActiveTrail])>
            @if ($item->hasChildren())
                <details @if ($item->isInActiveTrail) open @endif>
                    <summary>@include('lynx-menus::menus.partials.link', ['item' => $item])</summary>
                    @include('lynx-menus::menus.partials.accordion-items', ['items' => $item->children, 'level' => $level + 1])
                </details>
            @else
                @include('lynx-menus::menus.partials.link', ['item' => $item])
            @endif
        </li>
    @endforeach
</ul>
