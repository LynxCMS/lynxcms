@props(['folder' => false])
{{ \LynxCms\Admin\Tree\TreeUi::icon($folder ? 'folder' : 'file-text', 'size-4 shrink-0 text-muted-foreground') }}
