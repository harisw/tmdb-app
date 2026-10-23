<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TitleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'rating' => $this->rating,
            'release_date' => $this->release_date?->format('Y'),
            'runtime' => $this->runtime,
            'overview' => $this->overview,
            'backdrop_path' => $this->backdrop_path,
            'poster_path' => $this->poster_path,
            'genres' => $this->whenLoaded('genres'),
            'keywords' => $this->whenLoaded('keywords'),
            'title_providers' => $this->whenLoaded('titleProviders'),
            'user_state' => $this->relationLoaded('userState') && $this->userState
                ? (new UserTitleStateResource($this->userState))->resolve($request)
                : null,
        ];
    }
}
