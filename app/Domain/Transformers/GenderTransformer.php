<?php

namespace App\Domain\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

class GenderTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return match ((int) $value) {
            1 => 'female',
            2 => 'male',
            default => 'not_specified',
        };
    }
}
