<?php

declare(strict_types=1);

namespace LynxCms\Insights;

/**
 * Where an insight stands. Open insights wait for an editor; a newer report supersedes the open
 * ones of earlier reports, so only the latest report has open insights.
 *
 * @api
 */
enum InsightState: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Dismissed = 'dismissed';
    case Archived = 'archived';
    case Superseded = 'superseded';
}
