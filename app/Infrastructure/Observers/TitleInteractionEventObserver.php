<?php

namespace App\Infrastructure\Observers;

use App\Models\TitleInteractionEvent;
use App\Services\TitleInteractionService;

readonly class TitleInteractionEventObserver
{
    public function __construct(private TitleInteractionService $service) {}

    public function created(TitleInteractionEvent $interactionEvent): void
    {
        $this->service->createOrUpdateState($interactionEvent);
    }
}
