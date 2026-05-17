<?php

namespace App\Enums;

enum IssueStatus: int
{
    case Todo = 1;
    case InProgress = 2;
    case Testing = 3;
    case Done = 4;
    case OnHold = 5;

    public function label(): string
    {
        return match ($this) {
            self::Todo => '未着手',
            self::InProgress => '作業中',
            self::Testing => 'テスト中',
            self::Done => '完了',
            self::OnHold => '保留',
        };
    }

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): int => $status->value,
            self::cases(),
        );
    }
}
