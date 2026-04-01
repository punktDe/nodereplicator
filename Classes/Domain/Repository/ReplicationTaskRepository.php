<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Domain\Repository;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Repository;

#[Flow\Scope(value: "singleton")]
class ReplicationTaskRepository extends Repository
{
    public function findPending(): array
    {
        $query = $this->createQuery();
        $query->matching($query->equals('processedAt', null));
        $query->setOrderings(['createdAt' => \Neos\Flow\Persistence\QueryInterface::ORDER_ASCENDING]);
        return $query->execute()->toArray();
    }

    public function hasPendingTasks(): bool
    {
        $query = $this->createQuery();
        $query->matching($query->equals('processedAt', null));
        return $query->count() > 0;
    }

    /**
     * @return list<\PunktDe\NodeReplicator\Domain\Model\ReplicationTask>
     */
    public function findProcessed(): array
    {
        $query = $this->createQuery();
        $query->matching($query->logicalNot($query->equals('processedAt', null)));
        return $query->execute()->toArray();
    }
}
