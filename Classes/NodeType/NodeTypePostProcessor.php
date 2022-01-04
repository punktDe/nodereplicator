<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\NodeType;

/*
 *  (c) 2021 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\NodeTypePostprocessor\NodeTypePostprocessorInterface;

class NodeTypePostProcessor implements NodeTypePostprocessorInterface
{

    public function process(NodeType $nodeType, array &$configuration, array $options)
    {
        if (!isset($configuration['options']['replication']['properties'])) {
            return;
        }

        $propertyReplicationConfiguration = $configuration['options']['replication']['properties'];

        $defaultPropertyReplicationConfiguration = [
            'update' => (bool)($propertyReplicationConfiguration['update'] ?? false),
            'updateEmptyOnly' => (bool)($propertyReplicationConfiguration['updateEmptyOnly'] ?? false),
        ];

        foreach ($configuration['properties'] as $propertyKey => $propertyConfiguration) {
            $configuration['properties'][$propertyKey]['options']['replication'] = array_merge($defaultPropertyReplicationConfiguration, $configuration['properties'][$propertyKey]['options']['replication'] ?? []);
        }
    }
}
