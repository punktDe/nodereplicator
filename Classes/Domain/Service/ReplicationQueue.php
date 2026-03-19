<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Domain\Service;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use PunktDe\NodeReplicator\Domain\Model\ReplicationTask;
use PunktDe\NodeReplicator\Domain\Repository\ReplicationTaskRepository;

#[Flow\Scope(value: "singleton")]
class ReplicationQueue
{
    public function __construct(
        private readonly ReplicationTaskRepository $replicationTaskRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
    ) {
    }

    public function enqueue(
        string $nodeAggregateId,
        string $workspaceName,
        string $originDimensionSpacePoint,
        string $eventType,
        ?string $propertyName = null,
        mixed $propertyValue = null,
        bool $updateEmptyOnly = false,
        bool $createHidden = false,
    ): void {
        $task = new ReplicationTask();
        $task->setNodeAggregateId($nodeAggregateId);
        $task->setWorkspaceName($workspaceName);
        $task->setOriginDimensionSpacePoint($originDimensionSpacePoint);
        $task->setEventType($eventType);
        $task->setPropertyName($propertyName);
        $task->setPropertyValue($propertyValue !== null ? serialize($propertyValue) : null);
        $task->setUpdateEmptyOnly($updateEmptyOnly);
        $task->setCreateHidden($createHidden);

        $this->replicationTaskRepository->add($task);
        $this->persistenceManager->persistAll();
    }

    /**
     * @return ReplicationTask[]
     */
    public function getPendingTasks(): array
    {
        return $this->replicationTaskRepository->findPending();
    }

    public function hasPendingTasks(): bool
    {
        return $this->replicationTaskRepository->hasPendingTasks();
    }

    public function markProcessed(ReplicationTask $task): void
    {
        $task->markProcessed();
        $this->replicationTaskRepository->update($task);
        $this->persistenceManager->persistAll();
    }
}
