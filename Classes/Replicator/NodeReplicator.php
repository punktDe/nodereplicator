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
            if ($nodeVariant !== null) {
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
