<?php

namespace App\Http\Controllers;

use App\Data\DTOs\UserTitleEventData;
use App\Domain\Enums\TitleEventType;
use App\Http\Requests\OnboardingRequest;
use App\Http\Resources\TitleResource;
use App\Services\RecommendationService;
use App\Services\TitleInteractionService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request, RecommendationService $recommendations): Response
    {
        $round = max(0, $request->integer('round')) % RecommendationService::ONBOARDING_ROUNDS;

        return Inertia::render('Onboarding', [
            'titles' => TitleResource::collection($recommendations->onboardingTitles($round)),
            'round' => $round,
            'minPicks' => OnboardingRequest::MIN_PICKS,
            'maxPicks' => OnboardingRequest::MAX_PICKS,
        ]);
    }

    public function store(OnboardingRequest $request, TitleInteractionService $interactions, UserService $users): RedirectResponse
    {
        $user = $request->user();

        foreach ($request->validated('title_ids') as $titleId) {
            $interactions->record(UserTitleEventData::from([
                'title_id' => $titleId,
                'type' => TitleEventType::LIKE->value,
                'value' => null,
            ])->setUser($user));
        }

        $users->computeUserTaste($user);

        return redirect('/');
    }
}
