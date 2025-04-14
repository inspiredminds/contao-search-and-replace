<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

#[AsCronJob('daily')]
class PurgeOldSearchesCronJob
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function __invoke(): void
    {
        $this->db->executeQuery(
            'DELETE FROM search_and_replace_job WHERE createdAt < ?',
            [(new \DateTimeImmutable())->modify('-1 weeks')],
            [Types::DATETIME_IMMUTABLE],
        );
    }
}
