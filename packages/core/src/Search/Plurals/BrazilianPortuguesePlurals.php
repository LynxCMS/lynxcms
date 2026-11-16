<?php

declare(strict_types=1);

namespace LynxCms\Core\Search\Plurals;

/**
 * Brazilian Portuguese plurals (ADR-0119): the same rules as Portuguese, whose exceptions already
 * hold both spellings (ônibus and ónibus, tênis and ténis). Registered for pt-BR on its own, so a
 * site can replace it without touching pt-PT.
 *
 * Extend it to add or change rules for a regional locale (ADR-0119).
 *
 * @api
 */
class BrazilianPortuguesePlurals extends PortuguesePlurals {}
