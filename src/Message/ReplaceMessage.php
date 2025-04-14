<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\Message;

class ReplaceMessage
{
    public function __construct(public readonly string $jobId)
    {
    }
}
