<?php

namespace App\Services;

use App\Support\Sla\BusinessCalendar;
use DateTimeInterface;

class SlaDeadlineCalculator
{
    public function __construct(
        private readonly SlaBusinessClock $businessClock,
        private readonly SlaKpiFeatureGate $featureGate,
    ) {}

    public function calculateFromTicketCreation(string $priority, DateTimeInterface $ticketCreatedAt): ?array
    {
        if (! $this->featureGate->canPublish()
            || ! $this->featureGate->isTicketCreatedOnOrAfterEffectiveAt($ticketCreatedAt)) {
            return null;
        }

        $tier = config('sla.priority_mapping.'.$priority);
        $targets = config('sla.targets_work_seconds.'.$tier);
        if (! is_string($tier)
            || ! is_array($targets)
            || ! isset($targets['response'], $targets['resolution'])
            || ! is_int($targets['response'])
            || ! is_int($targets['resolution'])
            || $targets['response'] < 0
            || $targets['resolution'] < 0) {
            return null;
        }

        try {
            $calendar = BusinessCalendar::fromConfig(config('sla.calendar', []));
        } catch (\Throwable) {
            return null;
        }

        $responseDeadline = $this->businessClock->deadlineAfterWorkingSeconds(
            $ticketCreatedAt,
            $targets['response'],
            $calendar,
        );
        $resolutionDeadline = $this->businessClock->deadlineAfterWorkingSeconds(
            $ticketCreatedAt,
            $targets['resolution'],
            $calendar,
        );

        if ($responseDeadline === null || $resolutionDeadline === null) {
            return null;
        }

        return [
            'tier' => $tier,
            'response' => $responseDeadline,
            'resolution' => $resolutionDeadline,
        ];
    }
}
