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

namespace Neos\Workspace\Ui\Controller;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Dimension\ContentDimensionId;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePointSet;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\UntagSubtree;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindAncestorNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\SearchTerm\SearchTerm;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceStatus;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Security\Authorization\PrivilegeManager;
use Neos\Flow\Security\Context;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\NodeLabel\NodeLabelGeneratorInterface;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Domain\Service\WorkspaceService;
use Neos\Neos\Domain\SubtreeTagging\NeosSubtreeTag;
use Neos\Neos\FrontendRouting\SiteDetection\SiteDetectionResult;
use Neos\Neos\Security\Authorization\ContentRepositoryAuthorizationService;
use Neos\Workspace\Ui\Domain\TrashBin;
use Neos\Workspace\Ui\Domain\TrashBin\TrashBinPagination;
use Neos\Workspace\Ui\ViewModel\Restore\RestoreListItem;
use Neos\Workspace\Ui\ViewModel\Restore\RestoreListItems;
use Neos\Workspace\Ui\Domain\TrashBin\TrashBinSorting;
use Neos\Workspace\Ui\ViewModel\Restore\RestoreListItemVariantDetails;
use Neos\Workspace\Ui\ViewModel\Restore\RestoreListItemVariantDetailsCollection;

/**
 * The Neos Restore module controller
 *
 * @internal for communication within the Restore UI only
 */
#[Flow\Scope('singleton')]
class RestoreController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    #[Flow\Inject]
    protected NodeLabelGeneratorInterface $nodeLabelGenerator;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    #[Flow\Inject]
    protected Context $securityContext;

    #[Flow\Inject]
    protected UserService $userService;

    #[Flow\Inject]
    protected Translator $translator;

    #[Flow\Inject]
    protected TrashBin $trashBin;

    #[Flow\Inject]
    protected PrivilegeManager $privilegeManager;

    #[Flow\Inject]
    protected ContentRepositoryAuthorizationService $authorizationService;

    #[Flow\Inject]
    protected WorkspaceService $workspaceService;

    public function indexAction(): void
    {
        $currentUser = $this->userService->getCurrentUser();
        if (!$currentUser) {
            throw new \Exception('No user is logged in', 1761047616);
        }
        $siteDetectionResult = SiteDetectionResult::fromRequest($this->request->getHttpRequest());
        $workspace = $this->workspaceService->getPersonalWorkspaceForUser($siteDetectionResult->contentRepositoryId, $currentUser->getId());

        $this->redirect(actionName: 'show', arguments: ['workspaceName' => $workspace->workspaceName->value]);
    }

    /**
     * Display a list of unpublished content
     */
    public function showAction(WorkspaceName $workspaceName, ?string $sorting = null, int $page = 1, string $searchTerm = ''): void
    {
        $searchTermObject = $searchTerm ? SearchTerm::fulltext($searchTerm) : null;
        $contentRepositoryId = SiteDetectionResult::fromRequest($this->request->getHttpRequest())->contentRepositoryId;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $sortingObject = $sorting ? TrashBinSorting::fromJsonString($sorting) : TrashBinSorting::default();

        $numberOfItems =  $this->trashBin->countItemsByWorkspaceName($contentRepositoryId, $workspaceName, $searchTermObject);
        //Todo: check if node is available in live workspace
        $offset =  ($page -1) * TrashBinPagination::DEFAULT_LIMIT;
        $pagination ??= TrashBinPagination::create($offset, TrashBinPagination::DEFAULT_LIMIT);
        $numberOfPages = (int)ceil($numberOfItems / TrashBinPagination::DEFAULT_LIMIT);
        $displayPagination = $this->paginagtionRange($numberOfPages, $page);


        $contentGraph = $contentRepository->getContentGraph($workspaceName);
        $liveContentGraph = $contentRepository->getContentGraph(WorkspaceName::forLive());


        $listItems = [];
        foreach ($this->trashBin->findItemsByWorkspaceNameWithParameters(
            contentRepositoryId: $contentRepositoryId,
            workspaceName: $workspaceName,
            sorting: $sortingObject,
            pagination: $pagination,
            searchTerm: $searchTermObject,
        ) as $trashBinItem) {

            $nodeAggregate = $contentGraph->findNodeAggregateById($trashBinItem->nodeAggregateId);

            $details = [];
            foreach (
                $nodeAggregate->occupiedDimensionSpacePoints->getIntersection(
                OriginDimensionSpacePointSet::fromDimensionSpacePointSet($trashBinItem->affectedDimensionSpacePoints)
                ) as $originDimensionSpacePoint
            ) {
                $subgraph = $contentGraph->getSubgraph(
                    $originDimensionSpacePoint->toDimensionSpacePoint(),
                    VisibilityConstraints::createEmpty()
                );
                $removedNode = $nodeAggregate->getNodeByOccupiedDimensionSpacePoint($originDimensionSpacePoint);

                $dimensionValueLabels = [];
                foreach ($originDimensionSpacePoint->coordinates as $id => $coordinate) {
                    $contentDimension = new ContentDimensionId($id);
                    $dimensionValueLabels[] = $contentRepository->getContentDimensionSource()
                        ->getDimension($contentDimension)
                        ?->getValue($coordinate)
                        ?->configuration['label'] ?? $coordinate;
                }

                $details[] = new RestoreListItemVariantDetails(
                    label: $this->nodeLabelGenerator->getLabel($removedNode),
                    ancestorLabels: array_map(
                        fn (Node $ancestor): string => $this->nodeLabelGenerator->getLabel($ancestor),
                        iterator_to_array($subgraph->findAncestorNodes(
                            $trashBinItem->nodeAggregateId,
                            FindAncestorNodesFilter::create()
                        )->reverse())
                    ),
                    dimensionValueLabels: $dimensionValueLabels
                );
            }
            $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($nodeAggregate->nodeTypeName);

            $user = $this->userService->findUserById($trashBinItem->userId);

            $listItems[] = new RestoreListItem(
                nodeAggregateId: $trashBinItem->nodeAggregateId,
                icon: $nodeType?->getFullConfiguration()['ui']['icon'],
                // @todo translate
                nodeTypeLabel: $nodeAggregate->nodeTypeName->value,
                details: RestoreListItemVariantDetailsCollection::fromArray($details),
                deletionUserName: $user
                    ? $user->getName()->getFullName()
                    : '[deleted user]',
                deleteTime: $trashBinItem->deleteTime,
            );
        }

        $this->view->assignMultiple([
            'workspaceName' => $workspaceName->value,
            'activeWorkspaceName' => $workspaceName->value,
            'restoreListItems' => $listItems ? RestoreListItems::fromArray($listItems) : array(),
            'flashMessages' => $this->controllerContext->getFlashMessageContainer()->getMessagesAndFlush(),
            'sorting' => $sortingObject,
            'searchTerm' => $searchTerm,
            'pagination' => $displayPagination,
            'currentPage' => $page,
            'enableRestoreButtons' => $this->authorizationService->getWorkspacePermissions(
                $contentRepositoryId,
                $workspaceName,
                $this->securityContext->getRoles(),
                $this->userService->getCurrentUser()?->getId(),
            )->write
        ]);
    }

    protected function paginagtionRange(int $numberOfPages, int $currentPage): array
    {
        $maximumNumberOfLinks = TrashBinPagination::MAXIMUM_NUMBER_OF_LINKS;
        if ($maximumNumberOfLinks > $numberOfPages) {
            $maximumNumberOfLinks = $numberOfPages;
        }
        $delta = floor($maximumNumberOfLinks / 2);
        $displayRangeStart = $currentPage - $delta;
        $displayRangeEnd = $currentPage + $delta + ($maximumNumberOfLinks % 2 === 0 ? 1 : 0);
        if ($displayRangeStart < 1) {
            $displayRangeEnd -= $displayRangeStart - 1;
        }
        if ($displayRangeEnd > $numberOfPages) {
            $displayRangeStart -= ($displayRangeEnd - $numberOfPages);
        }
        $displayRangeStart = (integer)max($displayRangeStart, 1);
        $displayRangeEnd = (integer)min($displayRangeEnd, $numberOfPages);

        $pages = [];
        for ($i = $displayRangeStart; $i <= $displayRangeEnd; $i++) {
            $pages[] = ['number' => $i, 'isCurrent' => ($i === $currentPage)];
        }

        $pagination = [
            'pages' => $pages,
            'current' => $currentPage,
            'numberOfPages' => $numberOfPages,
            'displayRangeStart' => $displayRangeStart,
            'displayRangeEnd' => $displayRangeEnd,
            'hasLessPages' => $displayRangeStart > 2,
            'hasMorePages' => $displayRangeEnd + 1 < $numberOfPages
        ];

        if ($currentPage < $numberOfPages) {
            $pagination['nextPage'] = $currentPage + 1;
        }
        if ($currentPage > 1) {
            $pagination['previousPage'] = $currentPage - 1;
        }
        return $pagination;
    }

    public function restoreNodeConfirmationAction(WorkspaceName $workspaceName, NodeAggregateId $nodeAggregateId): void
    {
        $contentRepositoryId = SiteDetectionResult::fromRequest($this->request->getHttpRequest())->contentRepositoryId;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $nodeAggregate = $contentRepository->getContentGraph($workspaceName)->findNodeAggregateById($nodeAggregateId);

        // @todo validate that
        // * the node is still removed
        // inform about
        // * there might be more variants restored than selected in the UI (due to split trash items); which ones?

        //@todo only show the child nodes that are not explicitly tagged as removed since they will not be restored automatically
        //@todo also load children of children
        $restoreChildNodes = $contentRepository->getContentGraph($workspaceName)->findChildNodeAggregates($nodeAggregateId);
        $childNodes = [];
        foreach ($restoreChildNodes as $restoreChildNode) {
            // @todo get node label
            $childNodes[$restoreChildNode->nodeAggregateId->value] = $restoreChildNode->nodeName->value;
        }
        // TODO: additionallyRestoredAncestors
        $this->view->assignMultiple([
            'nodeAggregateId' => $nodeAggregateId->value,
            'nodeLabel' => $nodeAggregate->nodeName,
            'workspaceName' => $workspaceName->value,
            'additionallyRestoredDescendants' => $childNodes,
            'additionallyRestoredAncestors' => $childNodes,
        ]);
    }

    public function restoreNodeAction(WorkspaceName $workspaceName, NodeAggregateId $nodeAggregateId): void
    {
        // @todo check before failing;
        $contentRepositoryId = SiteDetectionResult::fromRequest($this->request->getHttpRequest())->contentRepositoryId;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $nodeAggregate = $contentRepository->getContentGraph($workspaceName)->findNodeAggregateById($nodeAggregateId);
        $coveredDimensionSpacePoints = iterator_to_array($nodeAggregate->coveredDimensionSpacePoints);


        $contentRepository->handle(UntagSubtree::create(
            workspaceName: $workspaceName,
            nodeAggregateId: $nodeAggregateId,
            coveredDimensionSpacePoint: reset($coveredDimensionSpacePoints),
            nodeVariantSelectionStrategy: NodeVariantSelectionStrategy::STRATEGY_ALL_VARIANTS,
            tag: NeosSubtreeTag::removed(),
        ));

        $this->addFlashMessage($this->getModuleLabel('restore.feedback.hasBeenRestored'));
        $this->forward(actionName: 'show', arguments: ['workspaceName' => $workspaceName->value]);
    }

    public function getModuleLabel(string $id, array $arguments = [], mixed $quantity = null): string
    {
        return $this->translator->translateById(
            $id,
            $arguments,
            $quantity,
            null,
            'Main',
            'Neos.Workspace.Ui'
        ) ?: $id;
    }

}
