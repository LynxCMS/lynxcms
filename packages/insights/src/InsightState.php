<?php

declare(strict_types=1);

namespace LynxCms\Insights;

/**
 * Where an insight stands. Open insights wait for an editor; a newer report supersedes the open
 * ones of earlier reports, so only the latest report has open insights. An approved insight is
 * approving while its plan's steps run on the queue (ADR-0157).
 *
 * @api
 */
enum InsightState: string
{
    case Open = 'open';
    case Approving = 'approving';
    case Approved = 'approved';
    case Dismissed = 'dismissed';
    case Archived = 'archived';
    case Superseded = 'superseded';
}
