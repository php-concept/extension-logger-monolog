<?php declare(strict_types=1);

namespace Concept\Extensions\LoggerMonolog;

use Closure;
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

    /**
     * @param Closure(): ?DataMaskerInterface|null $dataMaskerFactory
     */
    public function __construct(
        private readonly string $logFilePath,
        private readonly string $level,
        private readonly int $maxFiles,
        private readonly string $channel,
        private readonly ?Closure $dataMaskerFactory = null,
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

            return new Logger($monolog, $this->resolveDataMasker());
        })->setShared(true);
    }

    private function resolveDataMasker(): ?DataMaskerInterface
    {
        if ($this->dataMaskerFactory === null) {
            return null;
        }

        $dataMaskerFactory = $this->dataMaskerFactory;
        $masker = $dataMaskerFactory();

        return $masker instanceof DataMaskerInterface ? $masker : null;
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

        $monolog->pushHandler(new RotatingFileHandler($this->logFilePath, $this->maxFiles, $logLevel));

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
