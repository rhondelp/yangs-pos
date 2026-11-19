<?php

namespace App\Services\Settings;

use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Business settings the owner edits on the Settings page. Unsaved keys fall back to DEFAULTS.
 * Bound as a singleton, so one request reads the table once.
 */
class SettingService
{
    public const DEFAULTS = [
        'business_name' => 'YANGS',
        'business_address' => '',
        'business_tin' => '',
        'receipt_footer' => 'Thank you! Come again.',
        'clean_table_after_payment' => false,
        'require_approval_pin' => false,
        'allow_negative_stock' => false,
    ];

    private ?array $values = null;

    public function __construct(private ActivityLogger $logger) {}

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, mixed> every known setting with defaults filled in */
    public function all(): array
    {
        return $this->values ??= [
            ...self::DEFAULTS,
            ...DB::table('settings')->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')
                ->map(fn (?string $json) => json_decode($json, true))->all(),
        ];
    }

    /** Save known keys (others are ignored) and log only the values that changed. */
    public function set(array $values): void
    {
        DB::transaction(function () use ($values) {
            $current = $this->all();
            $old = $new = [];

            foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
                if ($current[$key] === $value) {
                    continue;
                }
                DB::table('settings')->updateOrInsert(['key' => $key], [
                    'value' => json_encode($value),
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
                $old[$key] = $current[$key];
                $new[$key] = $value;
            }

            if ($new) {
                $this->logger->log('settings.updated', null, $old, $new);
            }
            $this->values = null;
        });
    }
}
