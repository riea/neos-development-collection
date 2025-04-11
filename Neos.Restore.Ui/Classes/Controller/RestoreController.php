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

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Security\Context;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\NodeLabel\NodeLabelGeneratorInterface;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Domain\Service\WorkspaceService;
use Neos\Neos\FrontendRouting\SiteDetection\SiteDetectionResult;
use Neos\Neos\Utility\NodeTypeWithFallbackProvider;
use Neos\Restore\Ui\ViewModel\RestoreListItem;
use Neos\Restore\Ui\ViewModel\RestoreListItems;
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
    protected NodeLabelGeneratorInterface $nodeLabelGenerator;

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

        $contentRepositoryId = SiteDetectionResult::fromRequest($this->request->getHttpRequest())->contentRepositoryId;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $contentSubgraph = $contentRepository->getContentSubgraph(
            WorkspaceName::forLive(),
            DimensionSpacePoint::fromArray(['language' => 'de']),
        );
        $sitesRootNodeNode = $contentSubgraph->findRootNodeByType(NodeTypeNameFactory::forSites());

        $homepage = $contentSubgraph->findChildNodes(
            $sitesRootNodeNode->aggregateId,
            FindChildNodesFilter::create()
        );

        $children = $contentSubgraph->findChildNodes(
            $homepage[0]->aggregateId,
            FindChildNodesFilter::create()
        );
        $listItems = array();
        foreach ($children as $child) {
            $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($child->nodeTypeName);

            $listItems[] = new RestoreListItem(
                serializedNodeAddress: NodeAddress::fromNode($child)->toJson(),
                label: $this->nodeLabelGenerator->getLabel($child),
                icon: $nodeType?->getFullConfiguration()['ui']['icon'],
                nodeTypeLabel: $child->nodeTypeName->value,
                breadcrumb: array('TODO testing', 'testing2', 'testing3'),
                workspaceName: $child->workspaceName->value,
                deletionUserName: 'TODO last modified user',
                deletionDate: $child->timestamps->lastModified,
                isUserAllowedToEdit: true
            );
        }
        $this->view->assignMultiple([
            'restoreListItems' => RestoreListItems::fromArray($listItems),
            'flashMessages' => $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush(),
            'sorting' => $sorting,
        ]);
    }

    public function restoreNodeConfirmationAction(string $nodeAddressJson): void
    {
        $nodeAddress = NodeAddress::fromJsonString($nodeAddressJson);

        $this->view->assignMultiple([
            'nodeAddress' => $nodeAddressJson,
            'nodeLabel' => 'TODO Node Label',
            'targetWorkspaceOptions' => array ('user-workspace'=> 'User Workspace', 'workspace-name' => 'Workspace 1', 'workspace-name2' => 'Workspace 2'),
        ]);
    }
    public function restoreNodeAction(): void
    {
        $this->addFlashMessage($this->getModuleLabel('restore.feedback.hasBeenRestored'));
        $this->forward('index');
    }

    public function hardDeleteAction(): void {

        $this->addFlashMessage($this->getModuleLabel('restore.feedback.hasBeenHardDeleted'));
        $this->forward('index');
    }

    public function hardDeleteConfirmationAction(string $nodeAddressJson): void {

        $nodeAddress = NodeAddress::fromJsonString($nodeAddressJson);

        $this->view->assignMultiple([
            'nodeAddress' => $nodeAddressJson,
            'nodeLabel' => 'TODO Node Label'
        ]);
    }

    public function syncWorkspaceAction(): void {

        $this->addFlashMessage($this->getModuleLabel('restore.feedback.workspaceHasBeenSynchronized'));
        $this->forward('index');
    }


    public function getModuleLabel(string $id, array $arguments = [], mixed $quantity = null): string
    {
        return $this->translator->translateById(
            $id,
            $arguments,
            $quantity,
            null,
            'Main',
            'Neos.Restore.Ui'
        ) ?: $id;
    }
}
