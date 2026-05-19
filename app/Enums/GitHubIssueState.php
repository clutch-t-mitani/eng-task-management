<?php

namespace App\Enums;

enum GitHubIssueState: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $state): string => $state->value,
            self::cases(),
        );
    }
}
