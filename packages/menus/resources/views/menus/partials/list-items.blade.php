<ul @class(['lynx-menu-items', 'lynx-menu-level-'.$level])>
    @foreach ($items as $item)
        <li @class(['lynx-menu-item', 'has-children' => $item->hasChildren(), 'is-active' => $item->isActive, 'is-active-trail' => $item->isInActiveTrail])>
            @include('lynx-menus::menus.partials.link', ['item' => $item, 'tag' => $tag ?? 'span'])
            @if ($item->hasChildren())
                @include('lynx-menus::menus.partials.list-items', ['items' => $item->children, 'level' => $level + 1])
            @endif
        </li>
    @endforeach
</ul>
