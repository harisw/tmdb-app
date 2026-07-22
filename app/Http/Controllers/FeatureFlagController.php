<?php

namespace App\Http\Controllers;

use App\Utils\Enum\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;

class FeatureFlagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/FeatureFlags', [
            'flags' => collect(FeatureFlag::cases())->map(fn (FeatureFlag $flag) => [
                'key' => $flag->value,
                'label' => $flag->label(),
                'description' => $flag->description(),
                'active' => Feature::for('global')->active($flag->value),
            ])->all(),
        ]);
    }

    public function update(Request $request, string $flag): RedirectResponse
    {
        $feature = FeatureFlag::tryFrom($flag) ?? abort(404);

        $request->validate(['active' => 'required|boolean']);

        $active = $request->boolean('active');

        // Always the 'global' scope: in a web request Pennant would otherwise
        // default to the authenticated user, storing a row that the scheduler
        // (which has no user) never reads.
        $active
            ? Feature::for('global')->activate($feature->value)
            : Feature::for('global')->deactivate($feature->value);

        return back()->with(
            'message',
            "{$feature->label()} ".($active ? 'enabled' : 'disabled').'.'
        );
    }
}
