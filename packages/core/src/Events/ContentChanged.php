<?php

declare(strict_types=1);

namespace LynxCms\Core\Events;

/**
 * An event after which visitors may see different content: listen to this interface to clear
 * caches, rebuild search indexes or ping a CDN, instead of to every event one by one.
 *
 * @api
 */
interface ContentChanged
{
    /**
     * The pages whose own content or list of children changed, or null when any page may show
     * the change (a language or site text). Pages that only show the changed page elsewhere, such
     * as through a page reference, are not listed: callers that cache those follow references.
     *
     * @return list<int>|null
     */
    public function affectedPages(): ?array;
}
