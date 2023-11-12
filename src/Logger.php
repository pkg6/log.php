<?php

namespace Pkg6\Log;

use Pkg6\Log\message\CategoryFilter;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    /**
     *
     */
    protected const LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * @var Message[]
     */
    protected $messages = [];
    /**
     * @var  Handler[]
     */
    protected $handlers = [];
    /**
     * @var int
     */
    protected $traceLevel = 0;
    /**
     * @var array
     */
    protected $excludedTracePaths = [];
    /**
     * @var int
     */
    protected $flushInterval = 1000;

    /**
     * @param array $handlers
     */
    public function __construct($handlers = [])
    {
        if (!empty($handlers)) {
            foreach ($handlers as $handler) {
                $this->pushHandler($handler);
            }
        }
        register_shutdown_function(function () {
            // make regular flush before other shutdown functions, which allows session data collection and so on
            $this->flush();
            // make sure log entries written by shutdown functions are also flushed
            // ensure "flush()" is called last when there are multiple shutdown functions
            register_shutdown_function([$this, 'flush'], true);
        });
    }

    /**
     * @param Handler $handler
     * @return void
     */
    public function pushHandler($handler)
    {
        if (!($handler instanceof Handler)) {
            throw new InvalidArgumentException('You must provide an instance of ' . Handler::class);
        }
        $this->handlers[] = $handler;
    }


    /**
     * @param $level
     * @param $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if (($message instanceof Throwable) && !isset($context['exception'])) {
            // exceptions are string-convertible, thus should be passed as it is to the logger
            // if exception instance is given to produce a stack trace, it MUST be in a key named "exception".
            $context['exception'] = $message;
        }
        $context['time'] ??= microtime(true);
        $context['trace'] ??= $this->collectTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        $context['memory'] ??= memory_get_usage();
        $this->messages[] = new Message($level, $message, $context);
        if ($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval) {
            $this->flush();
        }
    }

    /**
     * @param int $traceLevel
     * @return $this
     */
    public function setTraceLevel($traceLevel)
    {
        $this->traceLevel = $traceLevel;
        return $this;
    }

    /**
     * @param array $excludedTracePaths
     * @return $this
     */
    public function setExcludedTracePaths($excludedTracePaths)
    {
        foreach ($excludedTracePaths as $excludedTracePath) {
            if (!is_string($excludedTracePath)) {
                throw new InvalidArgumentException(sprintf(
                    'The trace path must be a string, %s received.',
                    gettype($excludedTracePath)
                ));
            }
        }
        $this->excludedTracePaths = $excludedTracePaths;
        return $this;
    }

    /**
     * @param int $flushInterval
     * @return $this
     */
    public function setFlushInterval($flushInterval)
    {
        $this->flushInterval = $flushInterval;
        return $this;
    }

    /**
     * @param $final
     * @return void
     */
    public function flush($final = false)
    {
        $messages = $this->messages;
        $this->messages = [];
        $this->dispatch($messages, $final);
    }

    /**
     * @param Message[] $messages
     * @param bool $final
     * @return void
     */
    protected function dispatch($messages, $final)
    {
        $errors = [];
        foreach ($this->handlers as $handler) {
            if ($handler->isEnabled()) {
                try {
                    $handler->collect($messages, $final);
                } catch (Throwable $e) {
                    $handler->disable();
                    $errors[] = new Message(
                        LogLevel::WARNING,
                        'Unable to send log via ' . get_class($handler) . ': ' . get_class($e) . ': ' . $e->getMessage(),
                        ['time' => microtime(true), 'exception' => $e],
                    );
                }
            }
        }
        if (!empty($errors)) {
            $this->dispatch($errors, true);
        }
    }

    /**
     * @param array $backtrace
     * @return array
     */
    protected function collectTrace(array $backtrace): array
    {
        $traces = [];
        if ($this->traceLevel > 0) {
            $count = 0;
            foreach ($backtrace as $trace) {
                if (isset($trace['file'], $trace['line'])) {
                    $excludedMatch = array_filter($this->excludedTracePaths, static function ($path) use ($trace) {
                        return strpos($trace['file'], $path) !== false;
                    });
                    if (empty($excludedMatch)) {
                        unset($trace['object'], $trace['args']);
                        $traces[] = $trace;
                        if (++$count >= $this->traceLevel) {
                            break;
                        }
                    }
                }
            }
        }
        return $traces;
    }

    /**
     * @param $level
     * @return mixed
     */
    public static function validateLevel($level)
    {
        if (!is_string($level)) {
            throw new \Psr\Log\InvalidArgumentException(sprintf(
                'The log message level must be a string, %s provided.',
                gettype($level)
            ));
        }

        if (!in_array($level, self::LEVELS, true)) {
            throw new \Psr\Log\InvalidArgumentException(sprintf(
                'Invalid log message level "%s" provided. The following values are supported: "%s".',
                $level,
                implode('", "', self::LEVELS)
            ));
        }
        return $level;
    }


}