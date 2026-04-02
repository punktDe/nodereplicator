<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Job;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Flowpack\JobQueue\Common\Job\JobInterface;
use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use PunktDe\NodeReplicator\Domain\Model\ReplicationTask;
use PunktDe\NodeReplicator\Replicator\NodeReplicator;

final class ProcessReplicationJob implements JobInterface
{
    public function __construct(
        private readonly ReplicationTask $task,
    ) {
    }

    public function execute(QueueInterface $queue, Message $message): bool
    {
        $objectManager = Bootstrap::$staticObjectManager;
        if (!$objectManager instanceof ObjectManagerInterface) {
            throw new \RuntimeException('Flow ObjectManager is not available for job execution.', 1743590400);
        }

        $objectManager->get(NodeReplicator::class)->processTask($this->task);

        return true;
    }

    public function getLabel(): string
    {
        return sprintf(
            'PunktDe.NodeReplicator %s (node %s, workspace %s)',
            $this->task->getEventType(),
            $this->task->getNodeAggregateId(),
            $this->task->getWorkspaceName()
        );
    }
}
