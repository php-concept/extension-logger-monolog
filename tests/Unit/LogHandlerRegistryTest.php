<?php declare(strict_types=1);

namespace Tests\Unit;

use Concept\Extensions\LoggerMonolog\LogHandlerRegistry;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class LogHandlerRegistryTest extends TestCase
{
    public function testResolveReturnsOnlyRegisteredHandlersFromContainer(): void
    {
        $handler = new TestHandler();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            [TestHandler::class, true],
            ['MissingHandler', false],
        ]);
        $container->method('get')->with(TestHandler::class)->willReturn($handler);

        $registry = new LogHandlerRegistry();
        $registry->add(TestHandler::class);
        $registry->add('MissingHandler');

        $this->assertSame([$handler], $registry->resolve($container));
    }
}
