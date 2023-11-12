<?php

namespace Pkg6\Log\message;

use DateTime;
use Pkg6\Log\Message;
use Pkg6\VarDumper\VarDumper;
use RuntimeException;

class Formatter
{
    /**
     * @var
     */
    protected $format;
    /**
     * @var
     */
    protected $prefix;
    /**
     * @var string
     */
    protected $timestampFormat = 'Y-m-d H:i:s.u';

    /**
     * @param callable $format
     * @return void
     */
    public function setFormat(callable $format)
    {
        $this->format = $format;
    }

    /**
     * @param callable $prefix
     * @return void
     */
    public function setPrefix(callable $prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @param string $timestampFormat
     * @return void
     */
    public function setTimestampFormat(string $timestampFormat)
    {
        $this->timestampFormat = $timestampFormat;
    }

    /**
     * @param Message $message
     * @param array $commonContext
     * @return string
     */
    public function format(Message $message, array $commonContext)
    {
        if ($this->format === null) {
            return $this->defaultFormat($message, $commonContext);
        }
        $formatted = ($this->format)($message, $commonContext);
        if (!is_string($formatted)) {
            throw new RuntimeException(sprintf(
                'The PHP callable "format" must return a string, %s received.',
                gettype($formatted)
            ));
        }

        return $this->getPrefix($message, $commonContext) . $formatted;
    }

    /**
     * @param Message $message
     * @param array $commonContext
     * @return string
     */
    protected function defaultFormat(Message $message, array $commonContext)
    {
        $time    = $this->getTime($message);
        $prefix  = $this->getPrefix($message, $commonContext);
        $context = $this->getContext($message, $commonContext);
        return "{$time} {$prefix}[{$message->level()}] {$message->message()}{$context}";
    }

    /**
     * @param $message
     * @return string
     */
    protected function getTime($message)
    {
        $timestamp = (string)$message->context('time', microtime(true));

        switch (true) {
            case strpos($timestamp, '.') !== false:
                $format = 'U.u';
                break;
            case strpos($timestamp, ',') !== false:
                $format = 'U,u';
                break;
            default:
                $format = 'U';
                break;
        }

        return DateTime::createFromFormat($format, $timestamp)->format($this->timestampFormat);
    }

    /**
     * @param Message $message
     * @param array $commonContext
     * @return string
     */
    protected function getPrefix(Message $message, array $commonContext)
    {
        if ($this->prefix === null) {
            return '';
        }

        $prefix = ($this->prefix)($message, $commonContext);
        if (!is_string($prefix)) {
            throw new RuntimeException(sprintf(
                'The PHP callable "prefix" must return a string, %s received.',
                gettype($prefix)
            ));
        }
        return $prefix;
    }

    /**
     * @param Message $message
     * @param array $commonContext
     * @return string
     */
    protected function getContext(Message $message, array $commonContext)
    {
        $trace   = $this->getTrace($message);
        $context = [];
        $common  = [];

        if ($trace !== '') {
            $context[] = $trace;
        }

        foreach ($message->context() as $name => $value) {
            if ($name !== 'trace') {
                $context[] = "{$name}: " . $this->convertToString($value);
            }
        }

        foreach ($commonContext as $name => $value) {
            $common[] = "{$name}: " . $this->convertToString($value);
        }

        return (empty($context) ? '' : "\n\nMessage context:\n\n" . implode("\n", $context))
            . (empty($common) ? '' : "\n\nCommon context:\n\n" . implode("\n", $common)) . "\n";
    }

    /**
     * @param Message $message
     * @return string
     */
    protected function getTrace(Message $message)
    {
        $traces = (array)$message->context('trace', []);

        foreach ($traces as $key => $trace) {
            if (isset($trace['file'], $trace['line'])) {
                $traces[$key] = "in {$trace['file']}:{$trace['line']}";
            }
        }
        return empty($traces) ? '' : "trace:\n    " . implode("\n    ", $traces);
    }

    /**
     * @param $value
     * @return mixed|string
     * @throws \ReflectionException
     */
    protected function convertToString($value)
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }
        return VarDumper::create($value)->asString();
    }
}