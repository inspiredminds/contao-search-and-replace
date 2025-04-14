<?php

declare(strict_types=1);

namespace InspiredMinds\ContaoSearchAndReplace\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Symfony\Component\Uid\Ulid;

#[Entity]
#[Table(name: 'search_and_replace_job')]
class SearchAndReplaceJob
{
    #[Id]
    #[Column(unique: true)]
    #[GeneratedValue(strategy: 'NONE')]
    public string $id;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    public readonly \DateTimeImmutable $createdAt;

    #[Column(type: Types::JSON, nullable: true)]
    public array|null $results = null;

    #[Column(type: Types::JSON, nullable: true)]
    public array|null $replaceUids = null;

    #[Column]
    public bool $searchFinished = false;

    #[Column]
    public bool $replaceFinished = false;

    public function __construct(
        #[Column]
        public string $searchFor,
        #[Column]
        public string $replaceWith,
        #[Column(type: Types::JSON)]
        public array $tables,
    ) {
        $this->id = Ulid::generate();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function addSearchResult(string $table, string $column, string $primaryKey, string $id, string $context, string $preview): self
    {
        if (!\is_array($this->results)) {
            $this->results = [];
        }

        $this->results[Ulid::generate()] = [
            'table' => $table,
            'column' => $column,
            'pk' => $primaryKey,
            'id' => $id,
            'context' => $context,
            'preview' => $preview,
        ];

        return $this;
    }
}
