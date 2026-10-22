<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Items;

/**
 * A field type whose value is a list of items with their own values, such as a repeater or a
 * matrix (ADR-0044). Its table has one row per item (language_id 0: order, on/off, depth, type and
 * shared values) and one per item and language with translations; the field itself is shared.
 *
 * @api
 */
interface StoresItems {}
