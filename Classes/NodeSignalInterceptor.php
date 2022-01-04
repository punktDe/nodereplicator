<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator;

/*
 *  (c) 2017-2021 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use PunktDe\NodeReplicator\Replicator\NodeReplicator;

class NodeSignalInterceptor
{
    /**
     * @param NodeInterface $node
     */
    public static function nodeAdded(NodeInterface $node): void
    {
        if (self::hasReplicationConfiguration($node) && (self::nodeCreateReplicationEnabled($node) || self::nodeCreateHiddenEnabled($node))) {
            self::getNodeReplicator()->createNodeVariants($node, self::nodeCreateHiddenEnabled($node));
        }
    }

    public function nodeRemoved(NodeInterface $node): void
    {
        if (self::hasReplicationConfiguration($node) && self::nodeRemoveReplicationEnabled($node)) {
            self::getNodeReplicator()->removeNodeVariants($node);
        }
    }

    public function nodePropertyChanged(NodeInterface $node, string $propertyName, $oldValue, $newValue): void
    {
        if (!self::hasReplicationConfiguration($node)) {
            return;
        }

        if ($node->getNodeType()->getConfiguration('properties.' . $propertyName . '.options.replication.updateEmptyOnly')) {
            self::getNodeReplicator()->updateContent($node, $propertyName, $newValue, true);
            return;
        }

        if ($node->getNodeType()->getConfiguration('properties.' . $propertyName . '.options.replication.update')) {
            self::getNodeReplicator()->updateContent($node, $propertyName, $newValue, false);
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

    /**
     * @return Replicator\NodeReplicator
     */
    protected static function getNodeReplicator(): NodeReplicator
    {
        return new Replicator\NodeReplicator();
    }

    /**
     * @param NodeInterface $node
     * @return array|null
     */
    protected static function getExcludedProperties(NodeInterface $node): ?array
    {
        return $node->getNodeType()->getConfiguration('options.replication.excludeProperties');
    }
}
