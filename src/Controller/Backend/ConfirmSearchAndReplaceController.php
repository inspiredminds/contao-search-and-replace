<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\Controller\Backend;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Doctrine\ORM\EntityManagerInterface;
use InspiredMinds\ContaoSearchAndReplace\Entity\SearchAndReplaceJob;
use InspiredMinds\ContaoSearchAndReplace\Message\ReplaceMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '%contao.backend.route_prefix%/search-and-replace/confirm/{jobId}', name: self::class, defaults: ['_scope' => 'backend'])]
class ConfirmSearchAndReplaceController extends AbstractBackendController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Request $request, string $jobId): Response
    {
        $this->denyAccessUnlessGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, 'search_and_replace');

        if (!$job = $this->entityManager->getRepository(SearchAndReplaceJob::class)->findOneBy(['id' => $jobId])) {
            throw new PageNotFoundException(\sprintf('Could not find job "%s".', $jobId));
        }

        /** @var SearchAndReplaceJob $job */
        if ($request->request->has('replace')) {
            $job->replaceUids = $request->request->all('uids');

            $this->entityManager->persist($job);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new ReplaceMessage($job->id));

            return $this->redirectToRoute(SearchAndReplaceController::class);
        }

        return $this->render('@ContaoSearchAndReplace/backend/confirm_search_and_replace.html.twig', [
            'job' => $job,
            'request_token' => $this->csrfTokenManager->getDefaultTokenValue(),
            'back_route' => SearchAndReplaceController::class,
        ]);
    }
}
