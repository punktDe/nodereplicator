<?php

namespace PunktDe\NodeReplicator;

/*
 *  (c) 2017 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */


use Neos\ContentRepository\Domain\Model\NodeInterface;

class NodeSignalInterceptor
{
    /**
     * @param NodeInterface $node
     */
    public static function nodeAdded(NodeInterface $node)
    {
        if (self::hasReplicationConfiguration($node) && self::nodeReplicationEnabled($node)) {
            self::getNodeReplicator()->replicateNode($node);
        }
    }

    /**
     * @param NodeInterface $node
     */
    public static function nodeUpdated(NodeInterface $node)
    {
        if (self::hasReplicationConfiguration($node) && self::nodeContentUpdateEnabled($node)) {
            if (self::nodeContentUpdateOnlyEmpty($node)) {
                self::getNodeReplicator()->similarizeProperties($node);
            } else {
                self::getNodeReplicator()->similarizeNodeVariants($node);
            }

        }
    }

    /**
     * @param NodeInterface $node
     */
    public function nodeRemoved(NodeInterface $node)
    {
        if (self::hasReplicationConfiguration($node) && self::nodeReplicationEnabled($node)) {
            self::getNodeReplicator()->removeNodeVariants($node);
        }
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function hasReplicationConfiguration(NodeInterface $node)
    {
        return $node->getNodeType()->hasConfiguration('options.replication');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeReplicationEnabled(NodeInterface $node)
    {
        return $node->getNodeType()->hasConfiguration('options.replication.structure') && $node->getNodeType()->getConfiguration('options.replication.structure');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeContentUpdateEnabled(NodeInterface $node)
    {
        return $node->getNodeType()->hasConfiguration('options.replication.content') && $node->getNodeType()->getConfiguration('options.replication.content');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeContentUpdateOnlyEmpty(NodeInterface $node)
    {
        return $node->getNodeType()->hasConfiguration('options.replication.updateEmptyPropertiesOnly')
            && $node->getNodeType()->getConfiguration('options.replication.updateEmptyPropertiesOnly');
    }

    /**
     * @return Replicator\NodeReplicator
     */
    protected static function getNodeReplicator()
    {
        return new Replicator\NodeReplicator();
    }
}
