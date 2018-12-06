<?php

namespace AlephTools\DDD\Common\Infrastructure;

use AlephTools\DDD\Common\Model\Events\EntityCreated;
use AlephTools\DDD\Common\Model\Events\EntityDeleted;
use AlephTools\DDD\Common\Model\Events\EntityUpdated;

abstract class EventSourcedEntity extends Entity
{
    public function __construct(array $properties = [], bool $suppressEntityCreatedEvent = false)
    {
        parent::__construct($properties);
        if (!$suppressEntityCreatedEvent) {
            $this->publishEntityCreatedEvent($properties);
        }
    }

    /**
     * Sets properties.
     *
     * @param array $newProperties
     * @return void
     */
    protected function assignProperties(array $newProperties): void
    {
        if ($this->isEntityInstantiated) {
            $oldProperties = $this->getOldProperties($newProperties);
            [$oldNestedProperties, $newNestedProperties] = $this->computeNestedChanges($oldProperties, $newProperties);
            if ($newNestedProperties) {
                parent::assignProperties($newProperties);
                $this->publishEntityUpdatedEvent($oldNestedProperties, $newNestedProperties);
            }
        } else {
            parent::assignProperties($newProperties);
        }
    }

    private function getOldProperties(array $newProperties): array
    {
        $oldProperties = [];
        foreach ($newProperties as $property => $ignore) {
            $oldProperties[$property] = $this->__get($property);
        }
        return $oldProperties;
    }

    private function computeNestedChanges(array $Properties1, array $Properties2): array
    {
        $oldProperties = [];
        $newProperties = [];
        foreach ($Properties2 as $property => $value2) {
            $value1 = $Properties1[$property];
            if ($value2 instanceof DomainObject) {
                if (!$value2->equals($value1)) {
                    if ($value1 instanceof DomainObject) {
                        [$old, $new] = $this->computeNestedChanges($value1->toNestedArray(), $value2->toNestedArray());
                        $oldProperties[$property] = $old;
                        $newProperties[$property] = $new;
                    } else {
                        $oldProperties[$property] = $value1;
                        $newProperties[$property] = $value2;
                    }
                }
            } else if ($value2 !== $value1) {
                $oldProperties[$property] = $value1;
                $newProperties[$property] = $value2;
            }
        }
        return [$oldProperties, $newProperties];
    }

    protected function publishEntityCreatedEvent(array $properties): void
    {
        $this->publishEvent(new EntityCreated(static::class, $this->id, $properties));
    }

    protected function publishEntityUpdatedEvent(array $oldProperties, array $newProperties): void
    {
        $this->publishEvent(new EntityUpdated(static::class, $this->id, $oldProperties, $newProperties));
    }

    protected function publishEntityDeletedEvent(): void
    {
        $this->publishEvent(new EntityDeleted(static::class, $this->id));
    }
}