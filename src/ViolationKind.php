<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

enum ViolationKind: string
{
    case MissingCitation = 'missing_citation';
    case UnknownSource = 'unknown_source';
    case UnsupportedNumber = 'unsupported_number';
    case UnsupportedTerm = 'unsupported_term';
}
