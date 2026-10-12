<?php

namespace App\Http\Controllers;

use App\Data\DTOs\UserTitleEventData;
use App\Domain\Enums\UserTitleList;
use App\Http\Requests\PostTitleEventRequest;
use App\Http\Resources\TitleResource;
use App\Http\Resources\UserTitleStateResource;
use App\Services\TitleInteractionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function postTitleEvent(PostTitleEventRequest $req, TitleInteractionService $service): UserTitleStateResource
    {
        return new UserTitleStateResource($service->record(
            UserTitleEventData::from($req)->setUser($req->user())
        ));
    }

    public function titleLists(Request $req): Response
    {
        $user = $req->user();

        $lists = collect(UserTitleList::cases())->map(fn (UserTitleList $list) => [
            'value' => $list->value,
            'name' => $list->toName(),
            'url' => $list->toUrl(),
            'titles' => TitleResource::collection(
                $user->titles()
                    ->wherePivot($list->value, true)
                    ->listing()
                    ->take(self::PAGINATE_SIZE)
                    ->get()
            )->resolve(),
        ]);

        return Inertia::render('AllUserLists', [
            'lists' => $lists,
        ]);
    }

    public function showTitleList(Request $req, UserTitleList $type): Response
    {
        return Inertia::render('TitleByUserList', [
            'list' => [
                'value' => $type->value,
                'name' => $type->toName(),
            ],
            'titles' => TitleResource::collection(
                $req->user()
                    ->titles()
                    ->wherePivot($type->value, true)
                    ->listing()
                    ->paginate(self::PAGINATE_SIZE)
            ),
        ]);
    }
}
