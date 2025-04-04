<?php

/*
 * This file is part of the Neos.Restore.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Restore\Ui\Controller;

use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Security\Context;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Domain\Service\WorkspaceService;
use Neos\Neos\FrontendRouting\SiteDetection\SiteDetectionResult;
use Neos\Neos\Utility\NodeTypeWithFallbackProvider;
use Neos\Workspace\Ui\ViewModel\Sorting;

/**
 * The Neos Restore module controller
 *
 * @internal for communication within the Restore UI only
 */
#[Flow\Scope('singleton')]
class RestoreController extends AbstractModuleController
{
    use NodeTypeWithFallbackProvider;

    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected SiteRepository $siteRepository;

    #[Flow\Inject]
    protected PropertyMapper $propertyMapper;

    #[Flow\Inject]
    protected Context $securityContext;

    #[Flow\Inject]
    protected UserService $userService;

    #[Flow\Inject]
    protected PackageManager $packageManager;

    #[Flow\Inject]
    protected WorkspaceService $workspaceService;

    #[Flow\Inject]
    protected Translator $translator;


    /**
     * Display a list of unpublished content
     */
    public function indexAction(Sorting|null $sorting = null): void
    {
        $sorting ??= new Sorting(
            sortBy: 'title',
            sortAscending: true
        );

        $currentUser = $this->userService->getCurrentUser();
        if ($currentUser === null) {
            throw new \RuntimeException('No user authenticated', 1718308216);
        }

        $contentRepositoryId = SiteDetectionResult::fromRequest($this->request->getHttpRequest())->contentRepositoryId;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $workspaceListItems = $this->getWorkspaceListItems($contentRepository);
        $workspaceListItems = match($sorting->sortBy) {
            'title' => $workspaceListItems->sortByTitle($sorting->sortAscending),
        };

        $this->view->assignMultiple([
            'workspaceListItems' => $workspaceListItems,
            'flashMessages' => $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush(),
            'sorting' => $sorting,
        ]);
    }

    public function restoreChangeAction(): void
    {
        $this->addFlashMessage($this->getModuleLabel('restore.hasBeenRestored'));
        $this->forward('index');
    }

    public function hardDeleteAction(): void {

        $this->addFlashMessage($this->getModuleLabel('restore.hasBeenHardDeleted'));
        $this->forward('index');
    }

    public function syncWorkspaceAction(): void {

        $this->addFlashMessage($this->getModuleLabel('restore.workspaceHasBeenSynchronized'));
        $this->forward('index');
    }
}
