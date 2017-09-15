<?php

namespace PunktDe\NodeReplicator\Replicator;

/*
 *  (c) 2017 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class NodeReplicator
{
    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @param NodeInterface $node
     */
    public function replicateNode(NodeInterface $node)
    {
        /** @var NodeInterface $parentVariant */
        foreach ($this->getParentVariants($node) as $parentVariant) {

            if ($parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier()) !== null) {
                return;
            }

            $nodeVariant = $parentVariant->getContext()->adoptNode($node);
            $this->logReplicationAction($nodeVariant, 'Node was replicated to target context.');
        }
    }

    /**
     * @param NodeInterface $node
     */
    public function similarizeNodeVariants(NodeInterface $node)
    {
        /** @var NodeInterface $parentVariant */
        foreach ($this->getParentVariants($node) as $parentVariant) {
            $nodeVariant = $parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier());
            $nodeVariant->getNodeData()->similarize($node->getNodeData());

            $this->logReplicationAction($nodeVariant, 'Content of target node was updated.');
        }
    }

    /**
     * @param NodeInterface $node
     */
    public function removeNodeVariants(NodeInterface $node)
    {
        /** @var NodeInterface $parentVariant */
        foreach ($this->getParentVariants($node) as $parentVariant) {
            $nodeVariant = $parentVariant->getContext()->getNodeByIdentifier($node->getIdentifier());
            if ($nodeVariant !== null) {
                $nodeVariant->remove();
                $this->logReplicationAction($nodeVariant, 'Node variant was deleted.');
            }
        }
    }

    /**
     * @param NodeInterface $node
     * @return array
     */
    protected function getParentVariants(NodeInterface $node)
    {
        return $node->getParent()->getOtherNodeVariants();
    }

    /**
     * @param NodeInterface $nodeVariant
     * @param $message
     */
    protected function logReplicationAction(NodeInterface $nodeVariant, $message)
    {
        $dimensionsAndPresets = [];
        foreach ($nodeVariant->getDimensions() as $dimension => $presets) {
            $dimensionsAndPresets[] = $dimension . ':' . implode(',', array_values($presets));
        }
        $dimensionString = implode('|', $dimensionsAndPresets);

        $this->logger->log(sprintf('[NodeIdentifier: %s, TargetDimension: %s] %s', $nodeVariant->getIdentifier(), $dimensionString, $message));
    }
}
