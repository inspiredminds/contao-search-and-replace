<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\Controller\Backend;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use InspiredMinds\ContaoBackendFormsBundle\Form\BackendForm;
use InspiredMinds\ContaoSearchAndReplace\Entity\SearchAndReplaceJob;
use InspiredMinds\ContaoSearchAndReplace\Message\SearchMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '%contao.backend.route_prefix%/search-and-replace', name: self::class, defaults: ['_scope' => 'backend'])]
class SearchAndReplaceController extends AbstractBackendController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Connection $db,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly array $ignoredTables = [],
        private readonly array $defaultTables = [],
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'search_and_replace');

        System::loadLanguageFile('default');

        $form = $this->buildForm($request);

        if ($form->validate()) {
            // Create the search & replace job
            $job = new SearchAndReplaceJob(
                $form->fetch('search'),
                $form->fetch('replace'),
                $form->fetch('tables'),
                (bool) $form->fetch('case_insensitive'),
            );

            $this->entityManager->persist($job);
            $this->entityManager->flush();

            // Process the job
            $this->messageBus->dispatch(new SearchMessage($job->id));

            // Show search results
            return $this->redirectToRoute(ConfirmSearchAndReplaceController::class, ['jobId' => $job->id]);
        }

        return $this->render('@ContaoSearchAndReplace/backend/search_and_replace.html.twig', [
            'form' => $form->generate(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function getTables(): array
    {
        $schemaManager = $this->db->createSchemaManager();

        $tables = array_map(static fn (Table $table): string => $table->getName(), $schemaManager->listTables());
        $tables = array_filter($tables, fn (string $table): bool => !\in_array($table, $this->ignoredTables, true));

        sort($tables);

        return $tables;
    }

    private function buildForm(Request $request): BackendForm
    {
        $form = new BackendForm('search-and-replace', 'POST', static fn (): bool => 'search-and-replace' === $request->request->get('FORM_SUBMIT'));

        $form->addFormField('search', [
            'label' => [
                $this->translator->trans('form.search_for', [], 'search_and_replace'),
                $this->translator->trans('form.search_for_description', [], 'search_and_replace'),
            ],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
        ]);

        $form->addFormField('replace', [
            'label' => [
                $this->translator->trans('form.replace_with', [], 'search_and_replace'),
                $this->translator->trans('form.replace_with_description', [], 'search_and_replace'),
            ],
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
        ]);

        $tables = $this->getTables();
        $height = round(min(14, \count($tables)) * 22.4) - 11;

        $form->addFormField('tables', [
            'label' => [
                $this->translator->trans('form.tables', [], 'search_and_replace'),
                $this->translator->trans('form.tables_description', [], 'search_and_replace'),
            ],
            'inputType' => 'select',
            'options' => $this->getTables(),
            'eval' => ['multiple' => true, 'tl_class' => 'clr', 'mandatory' => true, 'style' => \sprintf('height: calc(%spx)', $height)],
        ]);

        $form->addFormField('case_insensitive', [
            'label' => [
                $this->translator->trans('form.case_insensitive', [], 'search_and_replace'),
                $this->translator->trans('form.case_insensitive_description', [], 'search_and_replace'),
            ],
            'inputType' => 'checkbox',
        ]);

        $form->addSubmitFormField($this->translator->trans('form.search_submit', [], 'search_and_replace'));

        if (Request::METHOD_POST !== $request->getMethod()) {
            $form->getWidget('tables')->value = $this->defaultTables;
        }

        return $form;
    }
}
