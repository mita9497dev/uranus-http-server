<?php 
namespace Mita\UranusHttpServer\Events;

interface EventDispatcherInterface
{
    public function addListener(string $eventName, callable $listener): void;
    public function removeListener(string $eventName, callable $listener): void;
    public function dispatch(string $eventName, Event $event = null): void;
}