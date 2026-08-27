<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTitleEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title_id' => $this->title_id,
            'event_type' => $this->event_type,
            'value' => $this->value,
        ];
    }
}
