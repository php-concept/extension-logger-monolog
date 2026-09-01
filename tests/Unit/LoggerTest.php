<?php declare(strict_types=1);

namespace Tests\Unit;

use Concept\Extensions\DataMasker\DataMasker;
use Concept\Extensions\DataMasker\RegexDataMaskerRule;
use Concept\Extensions\LoggerMonolog\Logger;
use Exception;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class LoggerTest extends TestCase
{
    public function testLogForwardsToMonolog(): void
    {
        $handler = new TestHandler();
        $logger = new Logger(new Monolog('test', [$handler]), null);

        $logger->log(LogLevel::INFO, 'hello', ['key' => 'value']);

        $this->assertTrue($handler->hasInfo('hello'));
    }

    public function testExceptionLogsContextWithTrace(): void
    {
        $handler = new TestHandler();
        $logger = new Logger(new Monolog('test', [$handler]), null);
        $exception = new Exception('boom', 418);

        $logger->exception($exception, '/test');

        $record = $handler->getRecords()[0];
        $this->assertSame('boom', $record['message']);
        $this->assertSame(418, $record['context']['code']);
        $this->assertSame('/test', $record['context']['uri']);
        $this->assertArrayHasKey('trace', $record['context']);
    }

    public function testMaskerRedactsLoggedContext(): void
    {
        $handler = new TestHandler();
        $masker = new DataMasker();
        $masker->addRule(new RegexDataMaskerRule(keyPatterns: ['/password/i']));
        $logger = new Logger(new Monolog('test', [$handler]), $masker);

        $logger->info('login', ['password' => 'secret']);

        $this->assertSame(DataMasker::MASK_CHARS, $handler->getRecords()[0]['context']['password']);
    }
}
