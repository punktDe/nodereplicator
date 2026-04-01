<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Domain\Service;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;

/**
 * Tracks when the node replicator is applying CR commands so catch-up hooks can skip echo enqueueing.
 */
#[Flow\Scope(value: 'singleton')]
final class ReplicationInternalContext
{
    private int $depth = 0;

    public function enter(): void
    {
        $this->depth++;
    }

    public function leave(): void
    {
        if ($this->depth > 0) {
            $this->depth--;
        }
    }

    public function isActive(): bool
    {
        return $this->depth > 0;
    }
}
