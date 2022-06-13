<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator;

/*
 *  (c) 2017-2021 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use PunktDe\NodeReplicator\Replicator\NodeReplicator;

/**
 * @Flow\Scope("singleton")
 */
class NodeSignalInterceptor
{
    /**
     * @Flow\Inject
     * @var NodeReplicator
     */
    protected $nodeReplicator;

    /**
     * @param NodeInterface $node
     */
    public function nodeAdded(NodeInterface $node): void
    {
        if (self::hasReplicationConfiguration($node) && (self::nodeCreateReplicationEnabled($node) || self::nodeCreateHiddenEnabled($node))) {
            // The nodeAdded signal is called twice. When it is called the first time,
            // the node is not created completely (the child nodes are not created yet), so we skip that call.
            if (count($node->getNodeType()->getAutoCreatedChildNodes()) > 0 && count($node->findChildNodes()) === 0) {
                return;
            }
            $this->nodeReplicator->createNodeVariants($node, self::nodeCreateHiddenEnabled($node));
        }
    }

    public function nodeRemoved(NodeInterface $node): void
    {
        if (self::hasReplicationConfiguration($node) && self::nodeRemoveReplicationEnabled($node)) {
            $this->nodeReplicator->removeNodeVariants($node);
        }
    }

    public function nodePropertyChanged(NodeInterface $node, string $propertyName, $oldValue, $newValue): void
    {
        if (!self::hasReplicationConfiguration($node)) {
            return;
        }

        if ($node->getNodeType()->getConfiguration('properties.' . $propertyName . '.options.replication.updateEmptyOnly')) {
            $this->nodeReplicator->updateContent($node, $propertyName, $newValue, true);
            return;
        }

        if ($node->getNodeType()->getConfiguration('properties.' . $propertyName . '.options.replication.update')) {
            $this->nodeReplicator->updateContent($node, $propertyName, $newValue, false);
        }
    }

    protected static function hasReplicationConfiguration(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication');
    }

    protected static function nodeCreateReplicationEnabled(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication.structure.create') && $node->getNodeType()->getConfiguration('options.replication.structure.create');
    }

    protected static function nodeRemoveReplicationEnabled(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication.structure.remove') && $node->getNodeType()->getConfiguration('options.replication.structure.remove');
    }

    protected static function nodeCreateHiddenEnabled(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication.structure.createHidden') && $node->getNodeType()->getConfiguration('options.replication.structure.createHidden');
    }
}
