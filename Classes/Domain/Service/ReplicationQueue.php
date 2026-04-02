<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Domain\Service;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Flowpack\JobQueue\Common\Job\JobManager;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;
use PunktDe\NodeReplicator\Domain\Model\ReplicationTask;
use PunktDe\NodeReplicator\Job\ProcessReplicationJob;

#[Flow\Scope(value: 'singleton')]
class ReplicationQueue
{
    private const DEFAULT_QUEUE_NAME = 'punktde-nodereplicator-replication';

    #[Flow\InjectConfiguration(path: 'queue', package: 'PunktDe.NodeReplicator')]
    protected array $queueSettings = [];

    public function __construct(
        private readonly JobManager $jobManager,
    ) {
    }

    public function enqueue(
        string $nodeAggregateId,
        string $workspaceName,
        string $originDimensionSpacePoint,
        string $eventType,
        string $contentRepositoryId,
        ?string $propertyName = null,
        mixed $propertyValue = null,
        bool $updateEmptyOnly = false,
        bool $createHidden = false,
    ): void {
        if (($this->queueSettings['liveWorkspaceOnly'] ?? false) && !WorkspaceName::fromString($workspaceName)->isLive()) {
            return;
        }

        $encodedPropertyValue = null;
        if ($propertyValue !== null) {
            $encodedPropertyValue = json_encode($propertyValue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $task = new ReplicationTask(
            $contentRepositoryId,
            $nodeAggregateId,
            $workspaceName,
            $originDimensionSpacePoint,
            $eventType,
            $propertyName,
            $encodedPropertyValue,
            $updateEmptyOnly,
            $createHidden,
        );

        $queueName = $this->queueSettings['flowpackQueueName'] ?? self::DEFAULT_QUEUE_NAME;
        $this->jobManager->queue($queueName, new ProcessReplicationJob($task));
    }
}
