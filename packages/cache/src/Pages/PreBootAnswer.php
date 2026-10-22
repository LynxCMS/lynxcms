<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * What the pre-boot server sends: header lines and a body.
 *
 * @internal
 */
final readonly class PreBootAnswer
{
    /** @param list<string> $headers */
    public function __construct(public array $headers, public string $body) {}

    /** Sends the answer and ends the request, before Laravel boots. */
    public function send(): never
    {
        foreach ($this->headers as $header) {
            header($header);
        }

        echo $this->body;

        exit;
    }
}
