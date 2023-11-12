<?php

namespace Pkg6\Cache\Tests;

use PHPUnit\Framework\TestCase;
use Pkg6\Log\handler\StreamHandler;
use Pkg6\Log\Logger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use ReflectionClass;

class LoggerTest extends TestCase
{
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var StreamHandler
     */
    private $handler;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->handler = new StreamHandler("./test/t.log");
        $this->logger  = new Logger([$this->handler]);
    }

    public function testLog(): void
    {
        $memory = memory_get_usage();
        $this->logger->log(LogLevel::INFO, 'test1');
        $messages = $this->getInaccessibleMessages($this->logger);

        $this->assertCount(1, $messages);
        $this->assertSame(LogLevel::INFO, $messages[0]->level());
        $this->assertSame('test1', $messages[0]->message());
        $this->assertSame('application', $messages[0]->context('category'));
        $this->assertSame([], $messages[0]->context('trace'));
        $this->assertGreaterThanOrEqual($memory, $messages[0]->context('memory'));

        $this->logger->log(LogLevel::ERROR, 'test2', ['category' => 'category']);
        $messages = $this->getInaccessibleMessages($this->logger);

        $this->assertCount(2, $messages);
        $this->assertSame(LogLevel::ERROR, $messages[1]->level());
        $this->assertSame('test2', $messages[1]->message());
        $this->assertSame('category', $messages[1]->context('category'));
        $this->assertSame([], $messages[1]->context('trace'));
        $this->assertGreaterThanOrEqual($memory, $messages[1]->context('memory'));
    }

    public function testLogWithTraceLevel(): void
    {
        $memory = memory_get_usage();
        $this->logger->setTraceLevel(3);

        $this->logger->log(LogLevel::INFO, 'test3');
        $messages = $this->getInaccessibleMessages($this->logger);

        $this->assertCount(1, $messages);
        $this->assertSame(LogLevel::INFO, $messages[0]->level());
        $this->assertSame('test3', $messages[0]->message());
        $this->assertSame('application', $messages[0]->context('category'));
        $this->assertCount(3, $messages[0]->context('trace'));
        $this->assertGreaterThanOrEqual($memory, $messages[0]->context('memory'));
    }

    public function messageProvider(): array
    {
        return [
            'string'            => ['test', 'test'],
            'int'               => [1, '1'],
            'float'             => [1.1, '1.1'],
            'bool'              => [true, '1'],
            'callable'          => [fn() => 1, 'fn () => 1'],
            'object'            => [new \stdClass(), 'unserialize(\'O:8:"stdClass":0:{}\')'],
            'stringable-object' => [
                $stringableObject = new class () {
                    public function __toString(): string
                    {
                        return 'Stringable object';
                    }
                },
                $stringableObject->__toString(),
            ],
        ];
    }

    /**
     * @dataProvider messageProvider
     *
     * @param $message
     * @param string $expected
     */
    public function testPsrLogInterfaceMethods($message, string $expected): void
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];
        $this->logger->emergency($message);
        $this->logger->alert($message);
        $this->logger->critical($message);
        $this->logger->error($message);
        $this->logger->warning($message);
        $this->logger->notice($message);
        $this->logger->info($message);
        $this->logger->debug($message);
        $this->logger->log(LogLevel::INFO, $message);

        $messages = $this->getInaccessibleMessages($this->logger);

        for ($i = 0, $levelsCount = count($levels); $i < $levelsCount; $i++) {
            $this->assertSame($levels[$i], $messages[$i]->level());
        }

        $this->assertSame(LogLevel::INFO, $messages[8]->level());
    }

    public function testSetExcludedTracePaths(): void
    {
        $this->logger->setTraceLevel(20);
        $this->logger->info('info message');

        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertSame(__FILE__, $messages[0]->context('trace')[1]['file']);

        $this->logger->setExcludedTracePaths([__DIR__]);
        $this->logger->info('info message');
        $messages = $this->getInaccessibleMessages($this->logger);

        foreach ($messages[1]->context('trace') as $trace) {
            $this->assertNotSame(__FILE__, $trace['file']);
        }
    }

    public function invalidExcludedTracePathsProvider(): array
    {
        return [
            'int'      => [[1]],
            'float'    => [[1.1]],
            'array'    => [[[]]],
            'bool'     => [[true]],
            'null'     => [[null]],
            'callable' => [[fn() => null]],
            'object'   => [[new \stdClass()]],
        ];
    }

    /**
     * @dataProvider invalidExcludedTracePathsProvider
     *
     * @param mixed $list
     */
    public function testSetExcludedTracePathsThrowExceptionForNonStringList($list): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->logger->setExcludedTracePaths($list);
    }

    public function testLevel(): void
    {
        $this->assertSame('info', Logger::validateLevel(LogLevel::INFO));
        $this->assertSame('error', Logger::validateLevel(LogLevel::ERROR));
        $this->assertSame('warning', Logger::validateLevel(LogLevel::WARNING));
        $this->assertSame('debug', Logger::validateLevel(LogLevel::DEBUG));
        $this->assertSame('emergency', Logger::validateLevel(LogLevel::EMERGENCY));
        $this->assertSame('alert', Logger::validateLevel(LogLevel::ALERT));
        $this->assertSame('critical', Logger::validateLevel(LogLevel::CRITICAL));
    }

    public function invalidMessageLevelProvider(): array
    {
        return [
            'string'   => ['unknown'],
            'int'      => [1],
            'float'    => [1.1],
            'bool'     => [true],
            'null'     => [null],
            'array'    => [[]],
            'callable' => [fn() => null],
            'object'   => [new \stdClass()],
        ];
    }

    /**
     * @dataProvider invalidMessageLevelProvider
     *
     * @param mixed $level
     */
    public function testGetLevelNameThrowExceptionForInvalidMessageLevel($level): void
    {
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        Logger::validateLevel($level);
    }


    public function invalidListTargetProvider(): array
    {
        return [
            'string'   => [['a']],
            'int'      => [[1]],
            'float'    => [[1.1]],
            'bool'     => [[true]],
            'null'     => [[null]],
            'array'    => [[[]]],
            'callable' => [[fn() => null]],
            'object'   => [[new \stdClass()]],
        ];
    }

    /**
     * @dataProvider invalidListTargetProvider
     *
     * @param array $targetList
     */
    public function testConstructorThrowExceptionForNonInstanceTarget(array $targetList): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Logger($targetList);
    }

    public function parseMessageProvider(): array
    {
        return [
            [
                'no placeholder',
                ['foo' => 'some'],
                'no placeholder',
            ],
            [
                'has {foo} placeholder',
                ['foo' => 'some'],
                'has some placeholder',
            ],
            [
                'has {foo} placeholder',
                [],
                'has {foo} placeholder',
            ],
        ];
    }

    /**
     * @dataProvider parseMessageProvider
     *
     * @param string $message
     * @param array $context
     * @param string $expected
     */
    public function testParseMessage(string $message, array $context, string $expected): void
    {
        $this->logger->log(LogLevel::INFO, $message, $context);
        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertSame($expected, $messages[0]->message());
    }


    private function getInaccessibleMessages(Logger $logger, bool $revoke = true): array
    {
        $class    = new ReflectionClass($logger);
        $property = $class->getProperty('messages');
        $property->setAccessible(true);
        $messages = $property->getValue($logger);

        if ($revoke) {
            $property->setAccessible(false);
        }

        return $messages;
    }
}