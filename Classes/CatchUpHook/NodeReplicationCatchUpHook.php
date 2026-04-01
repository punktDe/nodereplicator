<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\CatchUpHook;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Feature\NodeCreation\Event\NodeAggregateWithNodeWasCreated;
use Neos\ContentRepository\Core\Feature\NodeModification\Event\NodePropertiesWereSet;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\EventStore\Model\EventEnvelope;
use PunktDe\NodeReplicator\Domain\Model\ReplicationTask;
use PunktDe\NodeReplicator\Domain\Service\ReplicationInternalContext;
use PunktDe\NodeReplicator\Domain\Service\ReplicationQueue;
use Psr\Log\LoggerInterface;

final class NodeReplicationCatchUpHook implements CatchUpHookInterface
{
    /**
     * @var array<int, array{nodeAggregateId: string, workspaceName: string, originDimensionSpacePoint: string, eventType: string, propertyName?: string|null, propertyValue?: mixed, updateEmptyOnly?: bool, createHidden?: bool}>
     */
    private array $pendingTasks = [];

    public function __construct(
        private readonly ContentGraphReadModelInterface $contentGraphReadModel,
        private readonly NodeTypeManager $nodeTypeManager,
        private readonly ReplicationQueue $replicationQueue,
        private readonly ReplicationInternalContext $replicationInternalContext,
        private readonly LoggerInterface $logger,
        private readonly array $queueSettings,
    ) {
    }

    public function onBeforeCatchUp(SubscriptionStatus $subscriptionStatus): void
    {
    }

    public function onBeforeEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        if ($eventInstance instanceof NodeAggregateWasRemoved) {
            $this->handleNodeRemovedBefore($eventInstance);
        }
    }

    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        if ($eventInstance instanceof NodeAggregateWithNodeWasCreated) {
            $this->handleNodeCreated($eventInstance);
        } elseif ($eventInstance instanceof NodePropertiesWereSet) {
            $this->handleNodePropertiesSet($eventInstance);
        }
    }

    public function onAfterBatchCompleted(): void
    {
        $this->flushPendingTasks();
    }

    public function onAfterCatchUp(): void
    {
        $this->flushPendingTasks();

        $triggerViaShell = $this->queueSettings['triggerViaShell'] ?? false;
        if ($triggerViaShell && $this->replicationQueue->hasPendingTasks()) {
            $this->triggerShellCommand();
        }
    }

    private function handleNodeCreated(NodeAggregateWithNodeWasCreated $event): void
    {
        if ($this->replicationInternalContext->isActive()) {
            return;
        }
        if (!$this->isEventWorkspaceRelevant($event->workspaceName)) {
            return;
        }
        $nodeType = $this->nodeTypeManager->getNodeType($event->nodeTypeName);
        if ($nodeType === null || !$this->hasReplicationConfig($nodeType)) {
            return;
        }
        if (!$this->nodeCreateReplicationEnabled($nodeType) && !$this->nodeCreateHiddenEnabled($nodeType)) {
            return;
        }

        $this->pendingTasks[] = [
            'nodeAggregateId' => $event->nodeAggregateId->value,
            'workspaceName' => $event->workspaceName->value,
            'originDimensionSpacePoint' => json_encode($event->originDimensionSpacePoint),
            'eventType' => ReplicationTask::EVENT_TYPE_CREATE,
            'createHidden' => $this->nodeCreateHiddenEnabled($nodeType),
        ];
    }

    private function handleNodePropertiesSet(NodePropertiesWereSet $event): void
    {
        if ($this->replicationInternalContext->isActive()) {
            return;
        }
        if (!$this->isEventWorkspaceRelevant($event->workspaceName)) {
            return;
        }
        $subgraph = $this->contentGraphReadModel->getContentGraph($event->workspaceName)
            ->getSubgraph(
                $event->originDimensionSpacePoint->toDimensionSpacePoint(),
                VisibilityConstraints::createEmpty()
            );
        $node = $subgraph->findNodeById($event->nodeAggregateId);
        if ($node === null) {
            return;
        }

        $nodeType = $this->nodeTypeManager->getNodeType($node->nodeTypeName);
        if ($nodeType === null || !$this->hasReplicationConfig($nodeType)) {
            return;
        }

        foreach (array_keys($event->propertyValues->values) as $propertyName) {
            $updateEmptyOnly = $nodeType->getConfiguration('properties.' . $propertyName . '.options.replication.updateEmptyOnly') ?? false;
            $update = $nodeType->getConfiguration('properties.' . $propertyName . '.options.replication.update') ?? false;
            if (!$updateEmptyOnly && !$update) {
                continue;
            }
            $propertyValue = $node->getProperty($propertyName);
            $this->pendingTasks[] = [
                'nodeAggregateId' => $event->nodeAggregateId->value,
                'workspaceName' => $event->workspaceName->value,
                'originDimensionSpacePoint' => json_encode($event->originDimensionSpacePoint),
                'eventType' => ReplicationTask::EVENT_TYPE_UPDATE,
                'propertyName' => $propertyName,
                'propertyValue' => $propertyValue,
                'updateEmptyOnly' => (bool)$updateEmptyOnly,
            ];
        }
    }

    private function handleNodeRemovedBefore(NodeAggregateWasRemoved $event): void
    {
        if ($this->replicationInternalContext->isActive()) {
            return;
        }
        if (!$this->isEventWorkspaceRelevant($event->workspaceName)) {
            return;
        }
        $points = $event->affectedCoveredDimensionSpacePoints->points;
        if (empty($points)) {
            return;
        }
        $firstPoint = reset($points);
        $subgraph = $this->contentGraphReadModel->getContentGraph($event->workspaceName)
            ->getSubgraph(
                $firstPoint,
                VisibilityConstraints::createEmpty()
            );
        $node = $subgraph->findNodeById($event->nodeAggregateId);
        if ($node === null) {
            return;
        }

        $nodeType = $this->nodeTypeManager->getNodeType($node->nodeTypeName);
        if ($nodeType === null || !$this->hasReplicationConfig($nodeType)) {
            return;
        }
        if (!$this->nodeRemoveReplicationEnabled($nodeType)) {
            return;
        }

        $originDimensionSpacePoint = OriginDimensionSpacePoint::fromDimensionSpacePoint($firstPoint);
        $this->pendingTasks[] = [
            'nodeAggregateId' => $event->nodeAggregateId->value,
            'workspaceName' => $event->workspaceName->value,
            'originDimensionSpacePoint' => json_encode($originDimensionSpacePoint),
            'eventType' => ReplicationTask::EVENT_TYPE_REMOVE,
        ];
    }

    private function isEventWorkspaceRelevant(WorkspaceName $workspaceName): bool
    {
        if (!($this->queueSettings['liveWorkspaceOnly'] ?? false)) {
            return true;
        }
        return $workspaceName->isLive();
    }

    private function hasReplicationConfig(NodeType $nodeType): bool
    {
        return $nodeType->hasConfiguration('options.replication');
    }

    private function nodeCreateReplicationEnabled(NodeType $nodeType): bool
    {
        return (bool)($nodeType->getConfiguration('options.replication.structure.create') ?? false);
    }

    private function nodeCreateHiddenEnabled(NodeType $nodeType): bool
    {
        return (bool)($nodeType->getConfiguration('options.replication.structure.createHidden') ?? false);
    }

    private function nodeRemoveReplicationEnabled(NodeType $nodeType): bool
    {
        return (bool)($nodeType->getConfiguration('options.replication.structure.remove') ?? false);
    }

    private function flushPendingTasks(): void
    {
        foreach ($this->pendingTasks as $task) {
            try {
                $this->replicationQueue->enqueue(
                    $task['nodeAggregateId'],
                    $task['workspaceName'],
                    $task['originDimensionSpacePoint'],
                    $task['eventType'],
                    $task['propertyName'] ?? null,
                    $task['propertyValue'] ?? null,
                    $task['updateEmptyOnly'] ?? false,
                    $task['createHidden'] ?? false,
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to enqueue replication task: ' . $e->getMessage(), ['task' => $task]);
            }
        }
        $this->pendingTasks = [];
    }

    private function triggerShellCommand(): void
    {
        $shellCommand = $this->queueSettings['shellCommand'] ?? 'flow nodereplicator:replication:processqueue';
        if (!function_exists('exec')) {
            $this->logger->warning('Cannot trigger replication: exec() is disabled in php.ini');
            return;
        }

        $flowPathRoot = defined('FLOW_PATH_ROOT') ? FLOW_PATH_ROOT : getcwd();
        $command = sprintf(
            'cd %s && ./%s > /dev/null 2>&1 &',
            escapeshellarg($flowPathRoot),
            escapeshellarg($shellCommand)
        );
        exec($command, $output, $returnCode);
        if ($returnCode !== 0) {
            $this->logger->warning('Failed to trigger replication: ' . implode(' ', $output));
        }
    }
}
