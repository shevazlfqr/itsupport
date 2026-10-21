<?php

namespace App\Services;

use App\Support\Sla\BusinessCalendar;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class SlaKpiFeatureGate
{
    public function canPublish(): bool
    {
        if (! config('sla.enabled')
            || ! config('sla.policy_approved')
            || ! config('sla.acceptance_approved')) {
            return false;
        }

        $effectiveAt = $this->effectiveAt();
        if ($effectiveAt === null || $effectiveAt->greaterThan(now('UTC'))) {
            return false;
        }

        $targets = config('sla.targets_work_seconds', []);
        $mapping = config('sla.priority_mapping', []);
        foreach (array_keys(config('helpdesk.priorities', [])) as $priority) {
            $tier = $mapping[$priority] ?? null;
            if (! is_string($tier) || ! isset($targets[$tier])) {
                return false;
            }
        }

        try {
            $calendar = BusinessCalendar::fromConfig(config('sla.calendar', []));

            return $calendar->covers(CarbonImmutable::now($calendar->timezone));
        } catch (\Throwable) {
            return false;
        }
    }

    public function isTicketCreatedOnOrAfterEffectiveAt(DateTimeInterface $ticketCreatedAt): bool
    {
        $effectiveAt = $this->effectiveAt();
        if ($effectiveAt === null) {
            return false;
        }

        return CarbonImmutable::instance($ticketCreatedAt)
            ->utc()
            ->greaterThanOrEqualTo($effectiveAt);
    }

    private function effectiveAt(): ?CarbonImmutable
    {
        $effectiveAt = config('sla.effective_at');
        if (! is_string($effectiveAt) || trim($effectiveAt) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($effectiveAt, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
