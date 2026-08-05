<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\EventListener;

use ContaoCommunityAlliance\DcGeneral\Data\ModelId;
use InspiredMinds\ContaoSearchAndReplace\Event\GetEditUrlEvent;
use MetaModels\IFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener]
class GetMetaModelsEditUrlListener
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly IFactory $metaModelsFactory,
    ) {
    }

    public function __invoke(GetEditUrlEvent $event): void
    {
        if (!$this->metaModelsFactory?->getMetaModel($event->table)) {
            return;
        }

        $event->setEditUrl(
            $this->urlGenerator->generate(
                'metamodels.metamodel',
                [
                    'tableName' => $event->table,
                    'act' => 'edit',
                    'id' => ModelId::fromValues($event->table, $event->id)->getSerialized(),
                ],
            ),
        );
    }
}
