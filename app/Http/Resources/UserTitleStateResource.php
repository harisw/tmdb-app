<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTitleStateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rate' => $this->rate,
            'watched' => $this->watched,
            'to_watch' => $this->to_watch,
            'liked' => $this->liked,
        ];
    }
}
