<?php

namespace App\Http\Controllers;

use App\Data\DTOs\UserTitleEventData;
use App\Http\Requests\PostTitleEventRequest;
use App\Http\Resources\UserTitleEventResource;
use App\Services\TitleInteractionService;

class UserController extends Controller
{
    public function postTitleEvent(PostTitleEventRequest $req, TitleInteractionService $service): UserTitleEventResource
    {
        return new UserTitleEventResource($service->createEvent(
            UserTitleEventData::from($req)->setUser($req->user())
        ));
    }
}
