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

    private static bool $signalHandlingEnabled = true;

    /**
     * @param NodeInterface $node
     */
    public static function nodeAdded(NodeInterface $node): void
    {
        if (self::$signalHandlingEnabled && self::hasReplicationConfiguration($node) && (self::nodeCreateReplicationEnabled($node) || self::nodeCreateHiddenEnabled($node))) {
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
        if (self::$signalHandlingEnabled && self::hasReplicationConfiguration($node) && self::nodeRemoveReplicationEnabled($node)) {
            self::getNodeReplicator()->removeNodeVariants($node);
        }
    }

    public static function nodePropertyChanged(NodeInterface $node, string $propertyName, $oldValue, $newValue): void
    {
        if (!self::$signalHandlingEnabled) {
            return;
        }

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

    public static function withoutTriggeringSignals(\Closure $callback): void
    {
        $signalsEnabled = self::$signalHandlingEnabled;
        self::$signalHandlingEnabled = false;
        try {
            $callback();
        } finally {
            self::$signalHandlingEnabled = $signalsEnabled;
        }
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
