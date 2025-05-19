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

    public function createNodeVariants(NodeInterface $node, bool $createHidden = false): void
    {
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
     * Remove all node variants
     */
    public function removeNodeVariants(NodeInterface $node): void
    {
        foreach ($this->getParentVariants($node) as $parentVariant) {
            $nodeVariant = $parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier());
            if ($nodeVariant !== null && !$nodeVariant->isRemoved()) {
                $nodeVariant->remove();
                $this->logReplicationAction($nodeVariant, 'Node variant was removed.', __METHOD__);
            }
        }
    }

    public function updateContent(NodeInterface $node, string $propertyName, $newValue, bool $updateEmptyOnly)
    {
        foreach ($this->getParentVariants($node) as $parentVariant) {
            $variantNode = $parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier());

            if ($variantNode === null) {
                $this->logReplicationAction($node, 'Node content was not update, as the variant does not exist.', __METHOD__, LogLevel::DEBUG);
                continue;
            }

            if ($updateEmptyOnly && !empty($variantNode->getProperty($propertyName))) {
                continue;
            }

            $variantNode->setProperty($propertyName, $newValue);
            $this->logReplicationAction($node, sprintf('Property %s of the node was updated', $propertyName), __METHOD__);
        }
    }

    /**
     * @param NodeInterface $node
     * @return NodeInterface[]
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
     * @param string $logLevel
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
}
