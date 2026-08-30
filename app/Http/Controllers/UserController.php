<?php

namespace App\Http\Controllers;

use App\Data\DTOs\UserTitleEventData;
use App\Http\Requests\PostTitleEventRequest;
use App\Http\Resources\UserTitleStateResource;
use App\Services\TitleInteractionService;

class UserController extends Controller
{
    public function postTitleEvent(PostTitleEventRequest $req, TitleInteractionService $service): UserTitleStateResource
    {
        return new UserTitleStateResource($service->record(
            UserTitleEventData::from($req)->setUser($req->user())
        ));
    }
}
