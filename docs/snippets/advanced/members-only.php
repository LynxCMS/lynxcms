<?php

declare(strict_types=1);

use App\Lynx\MembersOnly;
use Illuminate\Http\Request;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageGates;
use LynxCms\Core\Pages\Visibility\PageVisibility;

// The field, once (or in your schema files):
lynx()->fields->add('members_only', 'toggle', label: 'Members only');
lynx()->templates->update('post', ['add' => ['members_only']]);

// #region register
// In a service provider's boot() method:
app(PageVisibility::class)->add(new MembersOnly);

// The rule keeps guests from finding the pages; the gate guards their URL.
app(PageGates::class)->add(fn (Page $page, Request $request) => $request->user() === null && $page->members_only === true
    ? redirect()->guest('/login')
    : null);
// #endregion register
