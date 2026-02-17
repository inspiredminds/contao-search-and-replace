<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\MessageHandler;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
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
        private readonly ContaoFramework $contaoFramework,
        private readonly int $batchSize = 100,
        private readonly int $contextLength = 48,
        private readonly int $totalLength = 360,
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
        $this->contaoFramework->initialize();

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

            $searchColumns = array_diff($searchColumns, [$pk]);
            $selectColumns = array_map(fn (string $column): string => $this->db->quoteIdentifier($column), [$pk, ...$searchColumns]);

            $qb = $this->db->createQueryBuilder()
                ->select(...$selectColumns)
                ->from($this->db->quoteIdentifier($table->getName()))
                ->setMaxResults($this->batchSize)
            ;

            $offset = 0;

            while ($rows = $qb->fetchAllAssociative()) {
                foreach ($rows as $row) {
                    foreach ($searchColumns as $searchColumn) {
                        $content = (string) $row[$searchColumn];

                        // Ignore binary data
                        if (!mb_check_encoding($content, 'UTF-8')) {
                            continue;
                        }

                        if (preg_match($job->getRegex(), $content, $matches)) {
                            $context = $this->getContext($content, $matches, $job->caseInsensitive);

                            if (in_array($searchColumn, ['cssID', 'headline', 'sectionHeadline', 'teaserCssID'])) {
                                $contentArray = unserialize($content);

                                foreach ($contentArray as $key => $value) {
                                    $contentArray[$key] = preg_replace($job->getRegex(), $job->replaceWith, $value);
                                }

                                $preview = serialize($contentArray);
                            } else {
                                $preview = preg_replace($job->getRegex(), $job->replaceWith, $context);
                            }

                            $job->addSearchResult($table->getName(), $searchColumn, $pk, (string) $row[$pk], $context, $preview);

                            $this->entityManager->persist($job);
                            $this->entityManager->flush();
                        }
                    }
                }

                $offset += $this->batchSize;
                $qb->setFirstResult($offset);
            }

            unset($rows, $row);
        }

        $job->searchFinished = true;

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        $this->db->close();
    }

    private function getContext(string $content, array $matches, bool $ci = false): string
    {
        $contexts = [];
        $chunks = [];

        preg_match_all('((^|(?:\b|^).{0,'.$this->contextLength.'}(?:\PL|\pL))(?:'.implode('|', array_map('preg_quote', $matches)).')((?:\PL|\pL).{0,'.$this->contextLength.'}(?:\b|$)|$))u'.($ci ? 'i' : ''), $content, $chunks);

        foreach ($chunks[0] as $c) {
            $contexts[] = ' '.$c.' ';
        }

        if (!$contexts) {
            return '';
        }

        $context = trim(StringUtil::substrHtml(implode('…', $contexts), $this->totalLength));

        return preg_replace('((?<=^|\PL|\pL)('.implode('|', array_map('preg_quote', $matches)).')(?=\PL|\pL|$))u'.($ci ? 'i' : ''), '<mark class="highlight">$1</mark>', StringUtil::specialchars($context));
    }
}
