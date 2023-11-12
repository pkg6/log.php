<?php

namespace Pkg6\Cache\Tests\message;

use PHPUnit\Framework\TestCase;
use Pkg6\Log\Message;
use Pkg6\Log\message\Formatter;
use Psr\Log\LogLevel;
use RuntimeException;

class FormatterTest extends TestCase
{
    private $formatter;

    public function setUp(): void
    {
        $this->formatter = new Formatter();
    }

    public function testFormatWithContextAndSetFormat(): void
    {
        $this->formatter->setFormat(static function (Message $message) {
            $context = json_encode($message->context());
            return "({$message->level()}) {$message->message()}, context: {$context}";
        });
        $message  = new Message(LogLevel::INFO, 'message', ['foo' => 'bar', 'params' => ['baz' => true]]);
        $expected = '(info) message, context: {"foo":"bar","params":{"baz":true}}';
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testFormatWithTraceInContext(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(
            LogLevel::INFO,
            'message',
            ['category' => 'app', 'time' => 1508160390, 'trace' => [['file' => '/path/to/file', 'line' => 99]]],
        );

        $expected = "2017-10-16 13:26:30 [info] message

Message context:

trace:
    in /path/to/file:99
category: 'app'
time: 1508160390
";

        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function invalidCallableReturnStringProvider(): array
    {
        return [
            'string'   => [fn() => true],
            'int'      => [fn() => 1],
            'float'    => [fn() => 1.1],
            'array'    => [fn() => []],
            'null'     => [fn() => null],
            'callable' => [fn() => static fn() => 'a'],
            'object'   => [fn() => new \stdClass()],
        ];
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     *
     * @param callable $value
     */
    public function testFormatThrowExceptionForFormatCallableReturnNotString(callable $value): void
    {
        $this->formatter->setFormat($value);
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     *
     * @param callable $value
     */
    public function testFormatMessageThrowExceptionForPrefixCallableReturnNotString(callable $value): void
    {
        $this->formatter->setPrefix($value);
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }
}