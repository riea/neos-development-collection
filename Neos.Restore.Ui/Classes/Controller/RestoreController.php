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

use Neos\ContentRepository\Core\Dimension\ContentDimensionId;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindAncestorNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateClassification;
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
use Neos\Neos\PendingChangesProjection\ChangeFinder;
use Neos\Neos\PendingChangesProjection\ChangeProjection;
use Neos\Neos\PendingChangesProjection\ChangeType;
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

        $currentUser = $this->userService->getCurrentUser();
        $currentUserWorkspace = $this->workspaceService->getPersonalWorkspaceForUser($contentRepositoryId, $currentUser->getId());
        $contentGraph = $contentRepository->getContentGraph(
            $currentUserWorkspace->workspaceName
        );
        $changeProjection = $contentRepository->projectionState(ChangeFinder::class);
        $changes = $changeProjection->findByContentStreamIdAndChangeType($currentUserWorkspace->currentContentStreamId, ChangeType::DELETED);

        $listItems = array();
        foreach ($changes as $change) {
            $subgraph = $contentGraph->getSubgraph(
                $change->originDimensionSpacePoint->toDimensionSpacePoint(),
                VisibilityConstraints::createEmpty()
            );
            $removedNode = $subgraph->findNodeById($change->nodeAggregateId);
            $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($removedNode->nodeTypeName);

            $breadcrumbs = [];
            foreach( $subgraph->findAncestorNodes($removedNode->aggregateId, FindAncestorNodesFilter::create())->reverse() as $ancestorNode) {
                if($ancestorNode->classification === NodeAggregateClassification::CLASSIFICATION_ROOT){
                    continue;
                }
                $breadcrumbs[] = $this->nodeLabelGenerator->getLabel($ancestorNode);
            }
            $dimensions = [];
            foreach ($removedNode->dimensionSpacePoint->coordinates as $id => $coordinate) {
                $contentDimension = new ContentDimensionId($id);
                $dimensions[] = $contentRepository->getContentDimensionSource()
                    ->getDimension($contentDimension)
                    ?->getValue($coordinate)
                    ?->configuration['label'] ?? $coordinate;
            }
            $listItems[] = new RestoreListItem(
                serializedNodeAddress: NodeAddress::fromNode($removedNode)->toJson(),
                label: $this->nodeLabelGenerator->getLabel($removedNode),
                icon: $nodeType?->getFullConfiguration()['ui']['icon'],
                nodeTypeLabel: $removedNode->nodeTypeName->value,
                breadcrumb: $breadcrumbs,
                dimensions: $dimensions,
                workspaceName: $removedNode->workspaceName->value,
                deletionUserName: 'TODO last modified user',
                deletionDate: $removedNode->timestamps->lastModified,
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
