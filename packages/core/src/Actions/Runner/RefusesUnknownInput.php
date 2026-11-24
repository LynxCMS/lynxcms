<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Runner;

/**
 * Marks an action whose input may only hold the top-level keys its rules() name. The runner
 * refuses any other key, so a misspelled or camelCase key ("hasUrl") fails instead of being
 * ignored without a word. For actions whose input is a set of optional changes, such as
 * templates.update, where an ignored key looks like a change that worked.
 *
 * @internal
 */
interface RefusesUnknownInput {}
