<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

enum ItemStatus: string
{
    /** Clean on the first check. */
    case Passed = 'passed';

    /** Failed, then the repair round fixed it. */
    case Repaired = 'repaired';

    /** Still failing after repair, so its text was replaced by its cited sources. */
    case Reverted = 'reverted';

    /** Still failing and it cites no real source, so there is nothing safe to show. */
    case Dropped = 'dropped';
}
