<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use InspiredMinds\ContaoSearchAndReplace\Controller\Backend\SearchAndReplaceController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD, priority: -255)]
class BackendMenuListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        if (!$searchAndReplace = $tree->getChild('system')?->getChild('search_and_replace')) {
            return;
        }

        $searchAndReplace
            ->setUri($this->urlGenerator->generate(SearchAndReplaceController::class))
            ->setLinkAttribute('title', $this->translator->trans('MOD.search_and_replace.1', [], 'contao_modules'))
            ->setCurrent(SearchAndReplaceController::class === $this->requestStack->getCurrentRequest()->get('_controller'))
        ;
    }
}
