<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\SchemalessAttributes\SchemalessAttributes;

class CountryProvider extends Model
{
    public $casts = [
        'extra_attributes' => SchemalessAttributes::class,
    ];
}
