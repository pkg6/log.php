<?php

namespace Pkg6\Cache\Tests\handler;

use PHPUnit\Framework\TestCase;
use Pkg6\Log\handler\StreamHandler;
use Pkg6\Log\Message;
use Psr\Log\LogLevel;
use RuntimeException;

class StreamHandlerTest  extends TestCase
{
    public function testExportWithStringStreamIdentifier(): void
    {
        $handler = $this->createStreamHandler('php://output');
        $this->exportStreamHandler($handler);
        $this->expectOutputString("[info] message-1\n[debug] message-2\n[error] message-3\n");
    }

    public function testExportWithStreamResource(): void
    {
        $handler = $this->createStreamHandler(fopen('php://output', 'w'));
        $this->exportStreamHandler($handler);
        $this->expectOutputString("[info] message-1\n[debug] message-2\n[error] message-3\n");
    }

    public function testExportWithReopenedStream(): void
    {
        $handler = $this->createStreamHandler(fopen('php://output', 'w'));
        $expected = "[info] message-1\n[debug] message-2\n[error] message-3\n";

        $this->exportStreamHandler($handler);
        $this->exportStreamHandler($handler);

        $this->expectOutputString($expected . $expected);
    }

    public function testExportThrowExceptionForStreamCannotBeOpened(): void
    {
        $handler = $this->createStreamHandler('invalid://uri');
        $this->expectException(RuntimeException::class);
        $this->exportStreamHandler($handler);
    }

    public function errorWritingProvider(): array
    {
        return [
            'input-string' => ['php://input'],
            'input-resource' => [fopen('php://input', 'w')],
            'temp-not-writable' => [fopen('php://temp', 'r')],
            'memory-not-writable' => [fopen('php://memory', 'r')],
        ];
    }

    /**
     * @dataProvider errorWritingProvider
     *
     * @param resource|string $stream
     */
    public function testExportThrowExceptionForErrorWritingToStream($stream): void
    {
        $handler = $this->createStreamHandler($stream);
        $this->expectException(RuntimeException::class);
        $this->exportStreamHandler($handler);
    }

    /**
     * @param resource|string $stream
     *
     * @return StreamHandler
     */
    private function createStreamHandler($stream): StreamHandler
    {
        $handler = new StreamHandler($stream);
        $handler->setFormat(static fn (Message $message) => "[{$message->level()}] {$message->message()}");
        return $handler;
    }

    /**
     * @param StreamHandler $handler
     */
    private function exportStreamHandler(StreamHandler $handler): void
    {
        $handler->collect(
            [
                new Message(LogLevel::INFO, 'message-1', ['foo' => 'bar']),
                new Message(LogLevel::DEBUG, 'message-2', ['foo' => true]),
                new Message(LogLevel::ERROR, 'message-3', ['foo' => 1]),
            ],
            true
        );
    }
}