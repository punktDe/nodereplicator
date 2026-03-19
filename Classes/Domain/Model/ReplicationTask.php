<?php
declare(strict_types=1);

namespace PunktDe\NodeReplicator\Domain\Model;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;

#[Flow\Entity]
class ReplicationTask
{
    public const EVENT_TYPE_CREATE = 'create';
    public const EVENT_TYPE_UPDATE = 'update';
    public const EVENT_TYPE_REMOVE = 'remove';

    /**
     * @var string
     */
    protected string $nodeAggregateId;

    /**
     * @var string
     */
    protected string $workspaceName;

    /**
     * @var string JSON-encoded OriginDimensionSpacePoint
     */
    protected string $originDimensionSpacePoint;

    /**
     * @var string
     */
    protected string $eventType;

    /**
     * @var string|null
     */
    protected ?string $propertyName = null;

    /**
     * @var string|null Serialized property value for update events
     */
    protected ?string $propertyValue = null;

    /**
     * @var bool
     */
    protected bool $updateEmptyOnly = false;

    /**
     * @var bool For create events: create as hidden
     */
    protected bool $createHidden = false;

    /**
     * @var \DateTimeImmutable|null
     */
    protected ?\DateTimeImmutable $processedAt = null;

    /**
     * @var \DateTimeImmutable
     */
    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getNodeAggregateId(): string
    {
        return $this->nodeAggregateId;
    }

    public function setNodeAggregateId(string $nodeAggregateId): void
    {
        $this->nodeAggregateId = $nodeAggregateId;
    }

    public function getWorkspaceName(): string
    {
        return $this->workspaceName;
    }

    public function setWorkspaceName(string $workspaceName): void
    {
        $this->workspaceName = $workspaceName;
    }

    public function getOriginDimensionSpacePoint(): string
    {
        return $this->originDimensionSpacePoint;
    }

    public function setOriginDimensionSpacePoint(string $originDimensionSpacePoint): void
    {
        $this->originDimensionSpacePoint = $originDimensionSpacePoint;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): void
    {
        $this->eventType = $eventType;
    }

    public function getPropertyName(): ?string
    {
        return $this->propertyName;
    }

    public function setPropertyName(?string $propertyName): void
    {
        $this->propertyName = $propertyName;
    }

    public function getPropertyValue(): ?string
    {
        return $this->propertyValue;
    }

    public function setPropertyValue(?string $propertyValue): void
    {
        $this->propertyValue = $propertyValue;
    }

    public function isUpdateEmptyOnly(): bool
    {
        return $this->updateEmptyOnly;
    }

    public function setUpdateEmptyOnly(bool $updateEmptyOnly): void
    {
        $this->updateEmptyOnly = $updateEmptyOnly;
    }

    public function isCreateHidden(): bool
    {
        return $this->createHidden;
    }

    public function setCreateHidden(bool $createHidden): void
    {
        $this->createHidden = $createHidden;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): void
    {
        $this->processedAt = $processedAt;
    }

    public function markProcessed(): void
    {
        $this->processedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isProcessed(): bool
    {
        return $this->processedAt !== null;
    }
}
