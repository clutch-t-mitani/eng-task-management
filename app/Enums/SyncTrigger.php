<?php

namespace App\Enums;

enum SyncTrigger: string
{
    case Webhook = 'webhook';
    case ManualResync = 'manual_resync';

    public function label(): string
    {
        return match ($this) {
            self::Webhook => 'Webhook',
            self::ManualResync => '手動再同期',
        };
    }
}
