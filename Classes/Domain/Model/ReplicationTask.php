<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Domain\Model;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

/**
 * Serializable task payload for node replication (queued via Flowpack JobQueue).
 */
final class ReplicationTask
{
    public const EVENT_TYPE_CREATE = 'create';
    public const EVENT_TYPE_UPDATE = 'update';
    public const EVENT_TYPE_REMOVE = 'remove';

    public function __construct(
        private readonly string $contentRepositoryId,
        private readonly string $nodeAggregateId,
        private readonly string $workspaceName,
        private readonly string $originDimensionSpacePoint,
        private readonly string $eventType,
        private readonly ?string $propertyName = null,
        private readonly ?string $propertyValue = null,
        private readonly bool $updateEmptyOnly = false,
        private readonly bool $createHidden = false,
    ) {
    }

    public function getNodeAggregateId(): string
    {
        return $this->nodeAggregateId;
    }

    public function getWorkspaceName(): string
    {
        return $this->workspaceName;
    }

    public function getContentRepositoryId(): string
    {
        return $this->contentRepositoryId;
    }

    public function getOriginDimensionSpacePoint(): string
    {
        return $this->originDimensionSpacePoint;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }

    public function getPropertyValue(): ?string
    {
        return $this->propertyValue;
    }

    public function isUpdateEmptyOnly(): bool
    {
        return $this->updateEmptyOnly;
    }

    public function isCreateHidden(): bool
    {
        return $this->createHidden;
    }
}
