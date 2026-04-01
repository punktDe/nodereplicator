<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Replicator;

/*
 *  (c) 2017-2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\AbstractDimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\NodeVariation\Command\CreateNodeVariant;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use PunktDe\NodeReplicator\Domain\Model\ReplicationTask;
use PunktDe\NodeReplicator\Domain\Service\ReplicationInternalContext;

#[Flow\Scope(value: "singleton")]
class NodeReplicator
{
    /** @var array<string, bool> Guard against recursion when updating properties */
    protected static array $currentlyUpdatingNodeIds = [];

    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly ContentRepositoryRegistry $contentRepositoryRegistry,
        protected readonly ReplicationInternalContext $replicationInternalContext,
    ) {
    }

    public function createNodeVariants(Node $node, bool $createHidden = false): void
    {
        $this->replicationInternalContext->enter();
        try {
            $nodeAddress = NodeAddress::fromNode($node);
            $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
            $workspaceName = $nodeAddress->workspaceName;
            $sourceOrigin = $node->originDimensionSpacePoint;

            $targetOrigins = $this->getTargetOriginDimensionSpacePoints($contentRepository, $node);
            foreach ($targetOrigins as $targetOrigin) {
                $subgraph = $contentRepository->getContentGraph($workspaceName)->getSubgraph(
                    $targetOrigin->toDimensionSpacePoint(),
                    VisibilityConstraints::createEmpty()
                );
                if ($subgraph->findNodeById($nodeAddress->aggregateId) !== null) {
                    $this->logReplicationAction($targetOrigin, $nodeAddress->aggregateId->value, 'Node was not replicated, as it already exists in target dimension', LogLevel::DEBUG);
                    continue;
                }

                $contentRepository->handle(CreateNodeVariant::create(
                    $workspaceName,
                    $nodeAddress->aggregateId,
                    $sourceOrigin,
                    $targetOrigin
                ));
                $this->logReplicationAction($targetOrigin, $nodeAddress->aggregateId->value, 'Node was replicated to target dimension.');

                if ($createHidden) {
                    $contentRepository->handle(SetNodeProperties::create(
                        $workspaceName,
                        $nodeAddress->aggregateId,
                        $targetOrigin,
                        PropertyValuesToWrite::fromArray(['hidden' => true])
                    ));
                }
            }
        } finally {
            $this->replicationInternalContext->leave();
        }
    }

    public function removeNodeVariants(Node $node): void
    {
        $this->replicationInternalContext->enter();
        try {
            $nodeAddress = NodeAddress::fromNode($node);
            $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
            $targetOrigins = $this->getTargetOriginDimensionSpacePoints($contentRepository, $node);

            foreach ($targetOrigins as $targetOrigin) {
                $subgraph = $contentRepository->getContentGraph($nodeAddress->workspaceName)->getSubgraph(
                    $targetOrigin->toDimensionSpacePoint(),
                    VisibilityConstraints::createEmpty()
                );
                if ($subgraph->findNodeById($nodeAddress->aggregateId) === null) {
                    continue;
                }

                $contentRepository->handle(RemoveNodeAggregate::create(
                    $nodeAddress->workspaceName,
                    $nodeAddress->aggregateId,
                    $targetOrigin->toDimensionSpacePoint(),
                    NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS
                ));
                $this->logReplicationAction($targetOrigin, $nodeAddress->aggregateId->value, 'Node variant was removed.');
            }
        } finally {
            $this->replicationInternalContext->leave();
        }
    }

    public function updateContent(Node $node, string $propertyName, mixed $newValue, bool $updateEmptyOnly): void
    {
        $nodeAddress = NodeAddress::fromNode($node);
        if (isset(self::$currentlyUpdatingNodeIds[$nodeAddress->aggregateId->value])) {
            return;
        }
        self::$currentlyUpdatingNodeIds[$nodeAddress->aggregateId->value] = true;
        $this->replicationInternalContext->enter();

        try {
            $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
            $targetOrigins = $this->getTargetOriginDimensionSpacePoints($contentRepository, $node);

            foreach ($targetOrigins as $targetOrigin) {
                $subgraph = $contentRepository->getContentGraph($nodeAddress->workspaceName)->getSubgraph(
                    $targetOrigin->toDimensionSpacePoint(),
                    VisibilityConstraints::createEmpty()
                );
                $variantNode = $subgraph->findNodeById($nodeAddress->aggregateId);
                if ($variantNode === null) {
                    $this->logReplicationAction($targetOrigin, $nodeAddress->aggregateId->value, 'Node content was not updated, as the variant does not exist.', LogLevel::DEBUG);
                    continue;
                }
                if ($updateEmptyOnly && !empty($variantNode->getProperty($propertyName))) {
                    continue;
                }

                $contentRepository->handle(SetNodeProperties::create(
                    $nodeAddress->workspaceName,
                    $nodeAddress->aggregateId,
                    $targetOrigin,
                    PropertyValuesToWrite::fromArray([$propertyName => $newValue])
                ));
                $this->logReplicationAction($targetOrigin, $nodeAddress->aggregateId->value, sprintf('Property %s of the node was updated', $propertyName));
            }
        } finally {
            $this->replicationInternalContext->leave();
            unset(self::$currentlyUpdatingNodeIds[$nodeAddress->aggregateId->value]);
        }
    }

    /**
     * Writes every declared property value from the source dimension variant onto all sibling dimension variants.
     * Used when {@see createNodeVariants} only creates structure; target variants stay empty until properties are set.
     */
    public function replicateAllDeclaredPropertiesToTargetDimensions(Node $sourceNode): void
    {
        $nodeAddress = NodeAddress::fromNode($sourceNode);
        if (isset(self::$currentlyUpdatingNodeIds[$nodeAddress->aggregateId->value])) {
            return;
        }
        self::$currentlyUpdatingNodeIds[$nodeAddress->aggregateId->value] = true;
        $this->replicationInternalContext->enter();

        try {
            $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
            $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($sourceNode->nodeTypeName->value);
            if ($nodeType === null) {
                return;
            }

            $values = [];
            foreach (array_keys($nodeType->getProperties()) as $propertyName) {
                if (!$sourceNode->hasProperty($propertyName)) {
                    continue;
                }
                $values[$propertyName] = $sourceNode->getProperty($propertyName);
            }
            if ($values === []) {
                return;
            }

            $toWrite = PropertyValuesToWrite::fromArray($values);
            $targetOrigins = $this->getTargetOriginDimensionSpacePoints($contentRepository, $sourceNode);

            foreach ($targetOrigins as $targetOrigin) {
                $subgraph = $contentRepository->getContentGraph($nodeAddress->workspaceName)->getSubgraph(
                    $targetOrigin->toDimensionSpacePoint(),
                    VisibilityConstraints::createEmpty()
                );
                if ($subgraph->findNodeById($nodeAddress->aggregateId) === null) {
                    $this->logReplicationAction($targetOrigin, $nodeAddress->aggregateId->value, 'Properties were not replicated; variant missing.', LogLevel::DEBUG);
                    continue;
                }

                $contentRepository->handle(SetNodeProperties::create(
                    $nodeAddress->workspaceName,
                    $nodeAddress->aggregateId,
                    $targetOrigin,
                    $toWrite
                ));
                $this->logReplicationAction($targetOrigin, $nodeAddress->aggregateId->value, 'Declared properties replicated from source dimension.');
            }
        } finally {
            $this->replicationInternalContext->leave();
            unset(self::$currentlyUpdatingNodeIds[$nodeAddress->aggregateId->value]);
        }
    }

    public function processTask(ReplicationTask $task): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get(
            ContentRepositoryId::fromString($task->getContentRepositoryId())
        );
        $workspaceName = WorkspaceName::fromString($task->getWorkspaceName());
        $nodeAggregateId = NodeAggregateId::fromString($task->getNodeAggregateId());
        $originDimensionSpacePoint = OriginDimensionSpacePoint::fromArray(json_decode($task->getOriginDimensionSpacePoint(), true, 512, JSON_THROW_ON_ERROR));

        $contentGraph = $contentRepository->getContentGraph($workspaceName);
        $subgraph = $contentGraph->getSubgraph(
            $originDimensionSpacePoint->toDimensionSpacePoint(),
            VisibilityConstraints::createEmpty()
        );
        $node = $subgraph->findNodeById($nodeAggregateId);

        if ($task->getEventType() === ReplicationTask::EVENT_TYPE_CREATE) {
            if ($node === null) {
                $this->logReplicationAction(
                    $originDimensionSpacePoint,
                    $task->getNodeAggregateId(),
                    'Replication task: node not found for create.',
                    LogLevel::WARNING
                );
                return;
            }
            $this->createNodeVariants($node, $task->isCreateHidden());
        } elseif ($task->getEventType() === ReplicationTask::EVENT_TYPE_UPDATE) {
            if ($node === null) {
                $this->logReplicationAction(
                    $originDimensionSpacePoint,
                    $task->getNodeAggregateId(),
                    'Replication task: node not found for update.',
                    LogLevel::WARNING
                );
                return;
            }
            $propertyName = $task->getPropertyName();
            if ($propertyName === null || $propertyName === '') {
                $this->logReplicationAction(
                    $originDimensionSpacePoint,
                    $task->getNodeAggregateId(),
                    'Replication task: update skipped; property name missing.',
                    LogLevel::WARNING
                );
                return;
            }
            $propertyValue = $this->decodeStoredPropertyValue($task->getPropertyValue());
            $this->updateContent($node, $propertyName, $propertyValue, $task->isUpdateEmptyOnly());
        } elseif ($task->getEventType() === ReplicationTask::EVENT_TYPE_REMOVE) {
            $nodeAggregate = $contentGraph->findNodeAggregateById($nodeAggregateId);
            if ($nodeAggregate === null) {
                $this->logReplicationAction(
                    $originDimensionSpacePoint,
                    $task->getNodeAggregateId(),
                    'Replication task remove: node aggregate already fully removed.',
                    LogLevel::DEBUG
                );
                return;
            }
            $this->replicationInternalContext->enter();
            try {
                $referenceOrigin = null;
                foreach ($nodeAggregate->occupiedDimensionSpacePoints as $occupiedOrigin) {
                    $referenceOrigin = $occupiedOrigin;
                    break;
                }
                if ($referenceOrigin === null) {
                    return;
                }
                $contentRepository->handle(RemoveNodeAggregate::create(
                    $workspaceName,
                    $nodeAggregateId,
                    $referenceOrigin->toDimensionSpacePoint(),
                    NodeVariantSelectionStrategy::STRATEGY_ALL_VARIANTS
                ));
                $this->logReplicationAction($referenceOrigin, $nodeAggregateId->value, 'Node aggregate fully removed (replicated delete).');
            } finally {
                $this->replicationInternalContext->leave();
            }
        }
    }

    /**
     * @return OriginDimensionSpacePoint[]
     */
    protected function getTargetOriginDimensionSpacePoints(ContentRepository $contentRepository, Node $node): array
    {
        $nodeAddress = NodeAddress::fromNode($node);
        $contentGraph = $contentRepository->getContentGraph($nodeAddress->workspaceName);
        $subgraph = $contentGraph->getSubgraph(
            $nodeAddress->dimensionSpacePoint,
            $node->visibilityConstraints
        );
        $parentNode = $subgraph->findParentNode($nodeAddress->aggregateId);
        if ($parentNode === null) {
            return [];
        }

        $parentAggregate = $contentGraph->findNodeAggregateById($parentNode->aggregateId);
        if ($parentAggregate === null) {
            return [];
        }

        $sourceOrigin = $node->originDimensionSpacePoint;
        $targets = [];
        foreach ($parentAggregate->occupiedDimensionSpacePoints as $occupiedOrigin) {
            if (!$occupiedOrigin->equals($sourceOrigin)) {
                $targets[] = $occupiedOrigin;
            }
        }
        return $targets;
    }

    protected function logReplicationAction(AbstractDimensionSpacePoint $dimensionSpacePoint, string $nodeId, string $message, string $logLevel = LogLevel::INFO): void
    {
        $coordinates = $dimensionSpacePoint->coordinates;
        $parts = [];
        foreach ($coordinates as $dimension => $value) {
            $parts[] = $dimension . ':' . $value;
        }
        $dimensionString = implode('|', $parts);
        $this->logger->log($logLevel, sprintf('[NodeIdentifier: %s, TargetDimension: %s] %s', $nodeId, $dimensionString, $message), LogEnvironment::fromMethodName(__METHOD__));
    }

    /**
     * @param string|null $stored JSON from {@see ReplicationQueue}; legacy tasks may still be PHP-serialized
     */
    private function decodeStoredPropertyValue(?string $stored): mixed
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        try {
            return json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return unserialize($stored, ['allowed_classes' => false]);
        }
    }
}
