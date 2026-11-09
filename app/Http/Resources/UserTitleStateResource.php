<?php

namespace App\Http\Resources;

use App\Models\UserTitleState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserTitleState
 */
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
