<?php

declare(strict_types=1);

namespace LynxCms\Seo\Http;

use LynxCms\Seo\RobotsTxt;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /robots.txt. A public/robots.txt file, when there is one, is served by the web server first.
 *
 * @internal
 */
final readonly class RobotsController
{
    public function __construct(private RobotsTxt $robots) {}

    public function __invoke(): Response
    {
        return response($this->robots->render())->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
