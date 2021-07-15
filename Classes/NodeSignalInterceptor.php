<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator;

/*
 *  (c) 2017-2019 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
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
    public static function nodeAdded(NodeInterface $node)
    {
        if (self::hasReplicationConfiguration($node) && self::nodeReplicationEnabled($node)) {
            self::getNodeReplicator()->replicateNode($node, self::nodeCreateHiddenEnabled($node));
        }
    }

    /**
     * @param NodeInterface $node
     * @throws NodeException
     */
    public static function nodeUpdated(NodeInterface $node): void
    {
        if (self::hasReplicationConfiguration($node) && self::nodeContentUpdateEnabled($node)) {
            if (self::nodeContentUpdateOnlyEmpty($node)) {
                self::getNodeReplicator()->similarizePropertiesEmptyInOtherDimensions($node);
            } else {
                self::getNodeReplicator()->similarizeNodeVariants($node, self::getExcludedProperties($node));
            }

        }
    }

    /**
     * @param NodeInterface $node
     */
    public function nodeRemoved(NodeInterface $node): void
    {
        if (self::hasReplicationConfiguration($node) && self::nodeReplicationEnabled($node)) {
            self::getNodeReplicator()->removeNodeVariants($node);
        }
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function hasReplicationConfiguration(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeReplicationEnabled(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication.structure') && $node->getNodeType()->getConfiguration('options.replication.structure');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeCreateHiddenEnabled(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication.createHidden') && $node->getNodeType()->getConfiguration('options.replication.createHidden');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeContentUpdateEnabled(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication.content') && $node->getNodeType()->getConfiguration('options.replication.content');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeContentUpdateOnlyEmpty(NodeInterface $node): bool
    {
        return $node->getNodeType()->hasConfiguration('options.replication.updateEmptyPropertiesOnly')
            && $node->getNodeType()->getConfiguration('options.replication.updateEmptyPropertiesOnly');
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
