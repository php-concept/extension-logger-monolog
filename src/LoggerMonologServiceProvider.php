<?php declare(strict_types=1);

namespace Concept\Extensions\LoggerMonolog;

use Concept\Extensions\DataMasker\Contracts\DataMaskerInterface;
use Concept\Extensions\Event\Events\ExtensionAwakened;
use Concept\Extensions\Event\Support\EventDispatcherResolver;
use Concept\Extensions\LoggerMonolog\Contracts\LoggerInterface;
use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Container\ServiceProvider\BootableServiceProviderInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Container\ContainerInterface;
use Throwable;

final class LoggerMonologServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface
{
    private const string EXTENSION_NAME = 'logger-monolog';

    public function __construct(
        private readonly string $path,
        private readonly string $level,
        private readonly int $maxFiles,
        private readonly string $channel,
    ) {}

    public function provides(string $id): bool
    {
        return $id === LoggerInterface::class || $id === LogHandlerRegistry::class;
    }

    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(LoggerInterface::class, function() use ($container): Logger {
            EventDispatcherResolver::optional($container)?->dispatch(new ExtensionAwakened(
                extensionName: self::EXTENSION_NAME,
                anchorId: LoggerInterface::class,
            ));

            $monolog = new Monolog($this->channel);
            $this->setup($monolog, $container);

            /** @var DataMaskerInterface|null $masker */
            $masker = $container->has(DataMaskerInterface::class)
                ? $container->get(DataMaskerInterface::class)
                : null;

            return new Logger($monolog, $masker);
        })->setShared(true);
    }

    public function boot(): void
    {
        $this->getContainer()
            ->add(LogHandlerRegistry::class, fn(): LogHandlerRegistry => new LogHandlerRegistry())
            ->setShared(true);
    }

    private function setup(Monolog $monolog, ContainerInterface $container): void
    {
        try {
            /** @phpstan-ignore-next-line */
            $logLevel = Level::fromName($this->level);
        } catch (Throwable) {
            $logLevel = Level::Debug;
        }

        $monolog->pushHandler(new RotatingFileHandler($this->path, $this->maxFiles, $logLevel));

        if ($container->has(LogHandlerRegistry::class)) {
            /** @var LogHandlerRegistry $registry */
            $registry = $container->get(LogHandlerRegistry::class);

            foreach ($registry->resolve($container) as $handler) {
                $monolog->pushHandler($handler);
            }
        }

        $monolog->pushProcessor(new PsrLogMessageProcessor());
    }
}
