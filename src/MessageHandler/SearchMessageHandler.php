<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\MessageHandler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\BlobType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use Doctrine\ORM\EntityManagerInterface;
use InspiredMinds\ContaoSearchAndReplace\Entity\SearchAndReplaceJob;
use InspiredMinds\ContaoSearchAndReplace\Message\SearchMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SearchMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $db,
    ) {
    }

    public function __invoke(SearchMessage $message): void
    {
        if (!$job = $this->entityManager->getRepository(SearchAndReplaceJob::class)->findOneBy(['id' => $message->jobId])) {
            return;
        }

        /** @var SearchAndReplaceJob $job */
        if ($job->searchFinished || !$job->tables) {
            return;
        }

        $schemaManager = $this->db->createSchemaManager();

        // Go through all tables of the database
        foreach ($schemaManager->listTables() as $table) {
            // Only search in defined tables
            if (!\in_array($table->getName(), $job->tables, true)) {
                continue;
            }

            // Get the primary key
            if (!$pk = ($table->getPrimaryKey()?->getColumns()[0] ?? null)) {
                continue;
            }

            // Only search in string columns
            $searchColumns = [];

            foreach ($table->getColumns() as $column) {
                $type = $column->getType();
                $search = match (true) {
                    $type instanceof StringType => true,
                    $type instanceof TextType => true,
                    $type instanceof BlobType => true,
                    $type instanceof JsonType => true,
                    $type instanceof SimpleArrayType => true,
                    default => false,
                };

                if ($search) {
                    $searchColumns[] = $column->getName();
                }
            }

            if (!$searchColumns) {
                continue;
            }

            $result = $this->db->createQueryBuilder()
                ->select(array_merge([$pk], $searchColumns))
                ->from($table->getName())
                ->executeQuery()
            ;

            foreach ($result->iterateAssociative() as $row) {
                foreach ($searchColumns as $searchColumn) {
                    $content = $row[$searchColumn];

                    if (preg_match($job->searchFor, (string) $content, $matches)) {
                        $preview = preg_replace($job->searchFor, $job->replaceWith, (string) $content);

                        $job->addSearchResult($table->getName(), $searchColumn, $pk, (string) $row[$pk], $content, $preview);

                        $this->entityManager->persist($job);
                        $this->entityManager->flush();
                    }
                }
            }

            $result->free();
        }

        $job->searchFinished = true;

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->db->close();
    }
}
