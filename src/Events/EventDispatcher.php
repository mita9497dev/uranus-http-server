<?php 
namespace Mita\UranusHttpServer\Events;

class EventDispatcher implements EventDispatcherInterface
{
    private array $listeners = [];
    private array $wildcardListeners = [];

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        if ($eventName === '*') {
            $this->wildcardListeners[$priority][] = $listener;
            ksort($this->wildcardListeners);
        } else {
            $this->listeners[$eventName][$priority][] = $listener;
            ksort($this->listeners[$eventName]);
        }
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        if ($eventName === '*') {
            $this->removeListenerFromArray($this->wildcardListeners, $listener);
        } elseif (isset($this->listeners[$eventName])) {
            $this->removeListenerFromArray($this->listeners[$eventName], $listener);
        }
    }

    private function removeListenerFromArray(array &$listenerArray, callable $listener): void
    {
        foreach ($listenerArray as $priority => &$listeners) {
            $key = array_search($listener, $listeners, true);
            if ($key !== false) {
                unset($listeners[$key]);
                if (empty($listeners)) {
                    unset($listenerArray[$priority]);
                }
                break;
            }
        }
    }

    public function dispatch(string $eventName, ?Event $event = null): void
    {
        $event = $event ?? new Event();
        $event->setName($eventName);

        $this->dispatchToListeners($this->listeners[$eventName] ?? [], $event);
        
        if (!$event->isPropagationStopped()) {
            $this->dispatchToListeners($this->wildcardListeners, $event);
        }
    }

    private function dispatchToListeners(array $listeners, Event $event): void
    {
        foreach ($listeners as $priorityListeners) {
            foreach ($priorityListeners as $listener) {
                $listener($event, $event->getName(), $this);
                if ($event->isPropagationStopped()) {
                    return;
                }
            }
        }
    }

    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]) || !empty($this->wildcardListeners);
    }

    public function getListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName] ?? [];
        $wildcardListeners = $this->wildcardListeners;

        $allListeners = array_merge_recursive($listeners, $wildcardListeners);
        ksort($allListeners);

        return array_merge(...$allListeners);
    }
}
