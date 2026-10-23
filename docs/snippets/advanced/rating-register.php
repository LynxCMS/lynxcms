<?php

declare(strict_types=1);

use App\Lynx\Fields\RatingType;
use LynxCms\Admin\Editor\Converters\IntegerConverter;
use LynxCms\Admin\Editor\FieldInputs;

// #region register
// In a service provider's boot() method:
lynx()->fieldTypes()->register(RatingType::class);

// With lynxcms/admin, editors type the rating in the integer input.
if (class_exists(FieldInputs::class)) {
    app(FieldInputs::class)->register('rating', 'lynx-admin::inputs.integer', IntegerConverter::class);
}
// #endregion register
