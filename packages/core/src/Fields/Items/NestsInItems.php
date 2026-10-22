<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

/**
 * An item field type whose value may sit inside a repeater or matrix item as one JSON value, such
 * as images (ADR-0046). Other item fields cannot be subfields: items do not nest.
 *
 * @api
 */
interface NestsInItems {}
