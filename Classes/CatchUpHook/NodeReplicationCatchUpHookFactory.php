<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\CatchUpHook;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryDependencies;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;
use PunktDe\NodeReplicator\Domain\Service\ReplicationQueue;

/**
 * @implements CatchUpHookFactoryInterface<ContentGraphReadModelInterface>
 */
final class NodeReplicationCatchUpHookFactory implements CatchUpHookFactoryInterface
{
    #[Flow\InjectConfiguration(path: 'queue', package: 'PunktDe.NodeReplicator')]
    protected array $queueSettings = [];

    public function __construct(
        private readonly ReplicationQueue $replicationQueue,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function build(CatchUpHookFactoryDependencies $dependencies): NodeReplicationCatchUpHook
    {
        $projectionState = $dependencies->projectionState;
        if (!$projectionState instanceof ContentGraphReadModelInterface) {
            throw new \InvalidArgumentException(
                'NodeReplicationCatchUpHook requires ContentGraphReadModelInterface',
                1773828719
            );
        }

        return new NodeReplicationCatchUpHook(
            $projectionState,
            $dependencies->nodeTypeManager,
            $this->replicationQueue,
            $this->logger,
            $this->queueSettings,
        );
    }
}
