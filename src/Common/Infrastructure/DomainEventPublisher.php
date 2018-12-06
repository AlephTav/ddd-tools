<?php

namespace AlephTools\DDD\Common\Infrastructure;

use ReflectionClass;

class DomainEventPublisher
{
    private $dispatcher;

    private $subscribers = [];

    private $events = [];

    private $inTransaction = true;

    private $isPublishing = false;

    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->subscribe(DefaultDomainEventSubscriber::class);
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getSubscribers(): array
    {
        return $this->subscribers;
    }

    public function cleanEvents(): void
    {
        $this->events = [];
    }

    public function cleanSubscribers(): void
    {
        $this->subscribers = [];
    }

    public function cleanAll(): void
    {
        $this->cleanEvents();
        $this->cleanSubscribers();
    }

    public function inTransactionMode(): bool
    {
        return $this->inTransaction;
    }

    public function turnOnTransactionMode(): void
    {
        $this->inTransaction = true;
    }

    public function turnOffTransactionMode(): void
    {
        if ($this->inTransaction && !$this->isPublishing) {
            $this->isPublishing = true;
            $this->dispatchAll();
            $this->inTransaction = false;
            $this->isPublishing = false;
        }
    }

    public function subscribeAll(array $subscribers): void
    {
        foreach ($subscribers as $subscriber) {
            $this->subscribe($subscriber);
        }
    }

    public function subscribe(string $subscriber): void
    {
        if (!isset($this->subscribers[$subscriber])) {
            $this->subscribers[$subscriber] = (new ReflectionClass($subscriber))
                ->newInstanceWithoutConstructor()
                ->subscribedToEventType();
        }
    }

    public function publishAll(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }

    public function publish(DomainEvent $event): void
    {
        if ($this->inTransaction) {
            $this->events[] = $event;
        } else {
            $this->dispatch($event);
        }
    }

    private function dispatchAll(): void
    {
        try {

            while ($this->events) {
                $currentEvents = $this->events;
                $this->events = [];
                foreach ($currentEvents as $event) {
                    $this->dispatch($event);
                }
            }

        } finally {
            $this->events = [];
        }
    }

    private function dispatch(DomainEvent $event): void
    {
        foreach ($this->findMatchedSubscribers($event) as $subscriber) {
            $this->dispatcher->dispatch($subscriber, $event);
        }
    }

    private function findMatchedSubscribers(DomainEvent $event): array
    {
        $subscribers = [];
        foreach ($this->subscribers as $subscriber => $eventType) {
            if (is_a($event, $eventType)) {
                $subscribers[] = $subscriber;
            }
        }
        return $subscribers;
    }
}