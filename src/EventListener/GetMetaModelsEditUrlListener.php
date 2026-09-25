<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\EventListener;

use ContaoCommunityAlliance\DcGeneral\Data\ModelId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use InspiredMinds\ContaoSearchAndReplace\Event\GetEditUrlEvent;
use MetaModels\IFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener]
class GetMetaModelsEditUrlListener
{
    public function __construct(
        private readonly Connection $db,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly IFactory $metaModelsFactory,
    ) {
    }

    public function __invoke(GetEditUrlEvent $event): void
    {
        $table = $event->table;
        $id = $event->id;

        // Check if this is a MetaModel record directly
        if (str_starts_with($table, 'mm_') && $this->db->fetchOne('SELECT TRUE FROM tl_metamodel WHERE tableName = ?', [$table])) {
            $mmTable = $table;
            $mmItemId = $id;
        } else {
            // Check if this is a MetaModel attribute
            if (!str_starts_with($table, 'tl_metamodel_')) {
                return;
            }

            $schemaManager = $this->db->createSchemaManager();
            $columns = $schemaManager->listTableColumns($table);

            // We only support records with an "id" column for now
            if (3 !== \count(array_filter($columns, static fn (Column $column) => \in_array($column->getName(), ['id', 'att_id', 'item_id'], true)))) {
                return;
            }

            // Fetch the MetaModel attribute record
            if (!$attrRecord = $this->db->fetchAssociative(\sprintf('SELECT att_id, item_id FROM %s WHERE id = ?', $this->db->quoteIdentifier($table)), [$id])) {
                return;
            }

            $mmItemId = $attrRecord['item_id'];
            $attId = $attrRecord['att_id'];

            if (!$mmId = $this->db->fetchOne('SELECT pid FROM tl_metamodel_attribute WHERE id = ?', [$attId])) {
                return;
            }

            $mmTable = $this->metaModelsFactory->translateIdToMetaModelName($mmId);
        }

        $event->setEditUrl(
            $this->urlGenerator->generate(
                'metamodels.metamodel',
                [
                    'tableName' => $mmTable,
                    'act' => 'edit',
                    'id' => ModelId::fromValues($mmTable, $mmItemId)->getSerialized(),
                ],
            ),
        );
    }
}
