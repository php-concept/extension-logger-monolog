<?php declare(strict_types=1);

namespace Concept\Extensions\LoggerMonolog;

use Concept\Extensions\DataMasker\Contracts\DataMaskerInterface;
use Concept\Extensions\LoggerMonolog\Contracts\LoggerInterface;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use Psr\Log\AbstractLogger;
use Stringable;
use Throwable;

final class Logger extends AbstractLogger implements LoggerInterface
{
    public function __construct(
        private readonly Monolog $monolog,
        ?DataMaskerInterface $masker,
    ) {
        if ($masker === null) {
            return;
        }

        $this->monolog->pushProcessor(static function(LogRecord $record) use ($masker): LogRecord {
            return $record->with(
                context: $masker->mask($record->context),
            );
        });
    }

    /**
     * @param Level $level
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->monolog->log($level, (string) $message, $context);
    }

    public function exception(Throwable $exception, string $uri = ''): void
    {
        $code = $exception->getCode();
        if (method_exists($exception, 'getStatusCode')) {
            $code = $exception->getStatusCode();
        }

        $context = [
            'code' => $code,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($uri !== '') {
            $context['uri'] = $uri;
        }

        $this->error($exception->getMessage(), $context);
    }
}
