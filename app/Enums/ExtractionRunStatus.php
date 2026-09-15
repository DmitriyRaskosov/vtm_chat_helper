<?php

namespace App\Enums;

enum ExtractionRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case NeedsReview = 'needs_review';
    case Reviewed = 'reviewed';
    case Superseded = 'superseded';
    case Failed = 'failed';

    /**
     * Auto-dispatch must not enqueue the same window again.
     *
     * @return list<self>
     */
    public static function blockingSceneWindow(): array
    {
        return [
            self::Queued,
            self::Running,
            self::NeedsReview,
            self::Failed,
        ];
    }

    /**
     * Default inbox: windows that need ST attention.
     *
     * @return list<self>
     */
    public static function inboxDefault(): array
    {
        return [
            self::NeedsReview,
            self::Failed,
        ];
    }
}
