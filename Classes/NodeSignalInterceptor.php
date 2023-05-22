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
        if (self::hasReplicationConfiguration($node) && (self::nodeCreateReplicationEnabled($node) || self::nodeCreateHiddenEnabled($node)) && self::nodeHasAllowedPath($node)) {
            // The nodedAdded signal is called twice. When it is called the first time,
            // the node is not created completely (the child nodes are not created yet), so we skip that call.
            if (count($node->getNodeType()->getAutoCreatedChildNodes()) > 0 && count($node->findChildNodes()) === 0) {
                return;
            }
            self::getNodeReplicator()->createNodeVariants($node, self::nodeCreateHiddenEnabled($node));
        }
    }

    public static function nodeRemoved(NodeInterface $node): void
    {
        if (self::hasReplicationConfiguration($node) && self::nodeRemoveReplicationEnabled($node) && self::nodeHasAllowedPath($node)) {
            self::getNodeReplicator()->removeNodeVariants($node);
        }
    }

    public static function nodePropertyChanged(NodeInterface $node, string $propertyName, $oldValue, $newValue): void
    {
        if (!self::hasReplicationConfiguration($node) || !self::nodeHasAllowedPath($node)) {
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

    protected static function nodeHasAllowedPath(NodeInterface $node): bool
    {
        if (!$node->getNodeType()->hasConfiguration('options.replication.onlyPathsStartingWith')) {
            return true;  // if not configured, return default: allowed = true
        }

        $allowedStartingPaths = $node->getNodeType()->getConfiguration('options.replication.onlyPathsStartingWith');
        if (!is_array($allowedStartingPaths)) {  // fallback if only a string, instead of list was provided in the yaml
            $allowedStartingPaths = [$allowedStartingPaths];
        }
        $nodePath = strval($node->findNodePath());

        foreach ($allowedStartingPaths as $allowedPath) {
            if (substr($nodePath, 0, strlen($allowedPath)) === $allowedPath) {  // str_starts_with for pre php 8
                return true;
            }
        }
        return false;
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
