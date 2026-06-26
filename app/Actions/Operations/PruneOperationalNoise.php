<?php

namespace App\Actions\Operations;

use App\Models\NotificationDelivery;
use App\Models\ProviderWebhookEvent;
use App\Models\SystemHealthSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PruneOperationalNoise
{
    /**
     * @return array{notification_deliveries: int, provider_webhook_events: int, system_health_snapshots: int}
     */
    public function handle(): array
    {
        return [
            'notification_deliveries' => $this->prune(NotificationDelivery::query(), 'notification_deliveries'),
            'provider_webhook_events' => $this->prune(ProviderWebhookEvent::query(), 'provider_webhook_events'),
            'system_health_snapshots' => $this->prune(SystemHealthSnapshot::query(), 'system_health_snapshots'),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function prune(Builder $query, string $key): int
    {
        $days = $this->retentionDays($key);
        $deleted = $query->where('created_at', '<', now()->subDays($days))->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    private function retentionDays(string $key): int
    {
        $days = config("lartisan.retention.prune_after_days.{$key}");

        if (is_int($days)) {
            return max(1, $days);
        }

        if (is_string($days) && ctype_digit($days)) {
            return max(1, (int) $days);
        }

        return 365;
    }
}
