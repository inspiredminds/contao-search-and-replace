<?php

declare(strict_types=1);

namespace InspiredMinds\ContaoSearchAndReplace\EventListener;

use Contao\DataContainer;
use Contao\DC_Table;
use InspiredMinds\ContaoSearchAndReplace\Event\GetEditUrlEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener]
class GetDcTableEditUrlListener
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function __invoke(GetEditUrlEvent $event): void
    {
        if (!$driver = DataContainer::getDriverForTable($event->table)) {
            return;
        }

        if (!is_a($driver, DC_Table::class, true)) {
            return;
        }

        if (!$do = $this->findModuleFromTableId($event->table, $event->id)) {
            return;
        }

        $event->setEditUrl(
            $this->urlGenerator->generate(
                'contao_backend',
                [
                    'do' => $do,
                    'id' => $event->id,
                    'table' => $event->table,
                    'act' => 'edit',
                ],
            ),
        );
    }

    private function findModuleFromTableId(string $table, int $id, array|null $filteredModules = null): string|null
    {
        $modules = [];

        foreach (null === $filteredModules ? $GLOBALS['BE_MOD'] : [$filteredModules] as $group) {
            foreach ($group as $do => $module) {
                if (\in_array($table, $module['tables'] ?? [], true)) {
                    $modules[$do] = $module;
                }
            }
        }

        if (1 === \count($modules)) {
            return array_keys($modules)[0];
        }

        if (!$record = $this->getCurrentRecord($id, $table)) {
            return null;
        }

        if (isset($record['ptable'], $record['pid'])) {
            return $this->findModuleFromTableId($record['ptable'], (int) $record['pid'], $modules);
        }

        return array_keys($modules)[0] ?? null;
    }

    private function getCurrentRecord(int $id, string $table): array|null
    {
        return (new \ReflectionClass(DC_Table::class))
            ->newInstanceWithoutConstructor()
            ->getCurrentRecord($id, $table)
        ;
    }
}
