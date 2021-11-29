<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Replicator;

/*
 *  (c) 2017-2019 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Neos\Flow\Persistence\PersistenceManagerInterface;

/**
 * @Flow\Scope("singleton")
 */
class NodeReplicator
{
    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Replicates a node to all target dimensions where the parent node already exists
     *
     * @param NodeInterface $node
     */
    public function replicateNode(NodeInterface $node, bool $createHidden=false): void
    {
        /** @var NodeInterface $parentVariant */
        foreach ($this->getParentVariants($node) as $parentVariant) {

            if ($parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier()) !== null) {
                $this->logReplicationAction($node, 'Node was not replicated, as it already exists in target dimension', __METHOD__, LogLevel::DEBUG);
                continue;
            }
            $nodeVariant = $parentVariant->getContext()->adoptNode($node);

            // Create replicated node as "hidden node"
                $nodeVariant->setHidden($createHidden);

            $this->logReplicationAction($nodeVariant, 'Node was replicated to target context.', __METHOD__);
        }
    }

    /**
     * Similarize the node to all target dimensions. Copying over all properties and their values.
     *
     * @param NodeInterface $node
     * @param array|null $excludedProperties
     */
    public function similarizeNodeVariants(NodeInterface $node, ?array $excludedProperties): void
    {
        /** @var NodeInterface $parentVariant */
        foreach ($this->getParentVariants($node) as $parentVariant) {
            $nodeVariant = $parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier());

            if (!$nodeVariant instanceof NodeInterface) {
                $this->logger->info(sprintf('[NodeIdentifier: %s, TargetDimension: %s] Cannot similarize node, as the node was not found in the target dimension.', $node->getIdentifier(), json_encode($parentVariant->getDimensions())), LogEnvironment::fromMethodName(__METHOD__));
                continue;
            }

            // if properties are excluded from the replication, we store their values before similarizing
            // and set them back to their original values after
            $excludedPropertyValues = $this->getExcludedPropertyValues($nodeVariant, $excludedProperties);

            $nodeVariant->getNodeData()->similarize($node->getNodeData());

            $this->setExcludedPropertyValues($nodeVariant, $excludedPropertyValues);

            $this->logReplicationAction($nodeVariant, 'Content of target node was updated.', __METHOD__);
        }
    }

    /**
     * Remove all node variants
     *
     * @param NodeInterface $node
     */
    public function removeNodeVariants(NodeInterface $node): void
    {
        /** @var NodeInterface $parentVariant */
        foreach ($this->getParentVariants($node) as $parentVariant) {
            $nodeVariant = $parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier());
            if ($nodeVariant !== null) {
                $nodeVariant->remove();
                $this->logReplicationAction($nodeVariant, 'Node variant was deleted.', __METHOD__);
            }
        }
    }

    /**
     * @param NodeInterface $node
     * @throws NodeException
     */
    public function similarizePropertiesEmptyInOtherDimensions(NodeInterface $node): void
    {
        /** @var NodeInterface $parentVariant */
        foreach ($this->getParentVariants($node) as $parentVariant) {
            $nodeVariant = $parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier());

            if (!$nodeVariant) {
                $this->logger->warning(sprintf('Trying to set properties for empty node variant, skipping - original node identifier "%s"', $node->getIdentifier()), LogEnvironment::fromMethodName(__METHOD__));
                continue;
            }

            foreach ($node->getNodeData()->getProperties() as $propertyName => $propertyValue) {
                if (!$nodeVariant->hasProperty($propertyName) || empty($nodeVariant->getProperty($propertyName))) {
                    $nodeVariant->setProperty($propertyName, $propertyValue);
                }
            }

            $this->logReplicationAction($nodeVariant, 'Content of target node was updated. - only empty properties', __METHOD__);
        }
    }

    /**
     * @param NodeInterface $node
     * @return array
     */
    protected function getParentVariants(NodeInterface $node): array
    {
        // This is a fix for an edge case: copying a document with a content collection to another dimension (both using the replication)
        // with an autocreated child node in the content collection. As the content collection in the new dimension is not persisted at
        // this point, getParent() on the autocreated child node will return null. As this only happens when copying a document to another
        // dimension, there is no need to replicate anything in this moment.
        if ($node->getParent() === null) {
            return [];
        }
        
        return $node->getParent()->getOtherNodeVariants();
    }

    /**
     * @param NodeInterface $nodeVariant
     * @param string $message
     * @param string|null $loggingMethod
     */
    protected function logReplicationAction(NodeInterface $nodeVariant, string $message, ?string $loggingMethod = null, string $logLevel = LogLevel::INFO): void
    {
        $dimensionsAndPresets = [];
        foreach ($nodeVariant->getDimensions() as $dimension => $presets) {
            $dimensionsAndPresets[] = $dimension . ':' . implode(',', array_values($presets));
        }
        $dimensionString = implode('|', $dimensionsAndPresets);

        $this->logger->log($logLevel, sprintf('[NodeIdentifier: %s, TargetDimension: %s] %s', $nodeVariant->getIdentifier(), $dimensionString, $message), LogEnvironment::fromMethodName($loggingMethod ?? __METHOD__));
    }

    /**
     * @param NodeInterface $nodeVariant
     * @param array|null $excludedProperties
     * @return array
     * @throws NodeException
     */
    protected function getExcludedPropertyValues(NodeInterface $nodeVariant, ?array $excludedProperties): array
    {
        if (empty($excludedProperties)) {
            return [];
        }

        foreach ($excludedProperties as $property) {
            $excludedPropertyValues[$property] = $nodeVariant->getProperty($property);
        }

        return $excludedPropertyValues;
    }

    /**
     * @param NodeInterface $nodeVariant
     * @param array|null $excludedPropertyValues
     */
    protected function setExcludedPropertyValues(NodeInterface $nodeVariant, ?array $excludedPropertyValues): void
    {
        if (empty($excludedPropertyValues)) {
            return;
        }

        foreach ($excludedPropertyValues as $property => $value) {
            $nodeVariant->setProperty($property, $value);
        }
    }
}
