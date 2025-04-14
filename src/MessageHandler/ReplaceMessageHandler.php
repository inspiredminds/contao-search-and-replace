<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\MessageHandler;

use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use InspiredMinds\ContaoSearchAndReplace\Entity\SearchAndReplaceJob;
use InspiredMinds\ContaoSearchAndReplace\Message\ReplaceMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
class ReplaceMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $db,
        private readonly BackupManager $backupManager,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(ReplaceMessage $message): void
    {
        if (!$job = $this->entityManager->getRepository(SearchAndReplaceJob::class)->findOneBy(['id' => $message->jobId])) {
            return;
        }

        /** @var SearchAndReplaceJob $job */
        if (!$job->searchFinished) {
            throw new RecoverableMessageHandlingException('Search for this message is not finished.');
        }

        if ($job->replaceFinished || !$job->results || !$job->replaceUids) {
            return;
        }

        // Create backup before replacing
        $this->backupManager->create($this->backupManager->createCreateConfig());

        foreach ($job->replaceUids as $uid) {
            if (!$result = ($job->results[$uid] ?? null)) {
                continue;
            }

            $content = $this->db->createQueryBuilder()
                ->select($this->db->quoteIdentifier($result['column']))
                ->from($this->db->quoteIdentifier($result['table']))
                ->where(\sprintf('%s = :id', $result['pk']))
                ->setParameter('id', $result['id'])
                ->fetchOne()
            ;

            if (!$content) {
                continue;
            }

            $replace = preg_replace($job->searchFor, $job->replaceWith, (string) $content);

            unset($content);

            try {
                $this->db->update(
                    $result['table'],
                    [$result['column'] => $replace],
                    [$result['pk'] => $result['id']],
                );
            } catch (Exception $e) {
                $this->logger?->error(\sprintf('Error during search & replace job "%s"', $job->id), ['exception' => $e]);
            } finally {
                unset($replace);
            }
        }

        $job->replaceFinished = true;

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->db->close();
    }
}
