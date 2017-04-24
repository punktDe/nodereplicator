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
        if (self::hasReplicationConfiguration($node)) {
            self::getNodeReplicator()->similarizeNodeVariants($node);
        }
    }

    /**
     * @param NodeInterface $node
     */
    public function nodeRemoved(NodeInterface $node)
    {
        if (self::hasReplicationConfiguration($node)) {
            self::getNodeReplicator()->removeNodeVariants($node);
        }
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function hasReplicationConfiguration(NodeInterface $node)
    {
        return $node->getNodeType()->hasConfiguration('replication');
    }

    /**
     * @param NodeInterface $node
     * @return bool
     */
    protected static function nodeReplicationEnabled(NodeInterface $node) {
        return $node->getNodeType()->hasConfiguration('replication.structure') && $node->getNodeType()->getConfiguration('replication.structure');
    }

    /**
     * @return Replicator\NodeReplicator
     */
    protected static function getNodeReplicator()
    {
        return new Replicator\NodeReplicator();
    }
}
