<?php

namespace Pkg6\Log;

use Pkg6\VarDumper\VarDumper;

class Message
{
    /**
     * @var string Log message level.
     *
     * @see LogLevel See constants for valid level names.
     */
    protected $level;
    /**
     * @var string Log message.
     */
    protected $message;
    /**
     * @var array Log message context.
     */
    protected $context;

    public function __construct($level, $message, $context = [])
    {
        $this->level   = Logger::validateLevel($level);
        $this->message = $this->parse($message, $context);
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function level()
    {
        return $this->level;
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * @return array
     */
    public function context($name = null, $default = null)
    {
        if ($name === null) {
            return $this->context;
        }
        return $this->context[$name] ?? $default;
    }

    protected function parse($message, $context)
    {
        $message = (is_scalar($message) || (is_object($message) && method_exists($message, '__toString')))
            ? (string)$message
            : VarDumper::create($message)->export();

        return preg_replace_callback('/{([\w.]+)}/', static function (array $matches) use ($context) {
            $placeholderName = $matches[1];

            if (isset($context[$placeholderName])) {
                return (string)$context[$placeholderName];
            }

            return $matches[0];
        }, $message);
    }
}