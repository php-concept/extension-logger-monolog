<?php declare(strict_types=1);

namespace Concept\Extensions\LoggerMonolog;

use Monolog\Handler\HandlerInterface;
use Psr\Container\ContainerInterface;

final class LogHandlerRegistry
{
    /** @var list<class-string<HandlerInterface>> */
    private array $handlerClasses = [];

    /**
     * @param class-string<HandlerInterface> $handlerClass
     */
    public function add(string $handlerClass): void
    {
        $this->handlerClasses[] = $handlerClass;
    }

    /**
     * @return list<HandlerInterface>
     */
    public function resolve(ContainerInterface $container): array
    {
        $handlers = [];

        foreach ($this->handlerClasses as $handlerClass) {
            if (!$container->has($handlerClass)) {
                continue;
            }

            /** @var HandlerInterface $handler */
            $handler = $container->get($handlerClass);
            $handlers[] = $handler;
        }

        return $handlers;
    }
}
