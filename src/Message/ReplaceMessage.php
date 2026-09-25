<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\Message;

use Contao\CoreBundle\Messenger\Message\NormalPriorityMessageInterface;

class ReplaceMessage implements NormalPriorityMessageInterface
{
    public function __construct(public readonly string $jobId)
    {
    }
}
