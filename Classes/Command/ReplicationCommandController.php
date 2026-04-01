<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Command;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Cli\CommandController;
use PunktDe\NodeReplicator\Domain\Model\ReplicationTask;
use PunktDe\NodeReplicator\Domain\Service\ReplicationQueue;
use PunktDe\NodeReplicator\Replicator\NodeReplicator;

class ReplicationCommandController extends CommandController
{
    public function __construct(
        private readonly ReplicationQueue $replicationQueue,
        private readonly NodeReplicator $nodeReplicator,
    ) {
        parent::__construct();
    }

    public function processQueueCommand(): void
    {
        $tasks = $this->replicationQueue->getPendingTasks();
        $this->outputLine('Processing %d replication task(s)...', [count($tasks)]);

        foreach ($tasks as $task) {
            if (!$task instanceof ReplicationTask) {
                continue;
            }
            try {
                $this->nodeReplicator->processTask($task);
                $this->replicationQueue->markProcessed($task);
                $this->outputLine('  Processed task for node %s (event: %s)', [$task->getNodeAggregateId(), $task->getEventType()]);
            } catch (\Throwable $e) {
                $this->outputLine('<error>Failed to process task for node %s: %s</error>', [$task->getNodeAggregateId(), $e->getMessage()]);
            }
        }

        $this->outputLine('Done.');
    }


    public function removeProcessedCommand(): void
    {
        $removed = $this->replicationQueue->removeProcessedTasks();
        $this->outputLine('Removed %d processed replication task(s).', [$removed]);
    }
}
