<?php

namespace Pkg6\Log;

use Pkg6\Log\message\CategoryFilter;
use Pkg6\Log\message\Formatter;
use Psr\Log\InvalidArgumentException;
use RuntimeException;

abstract class Handler
{
    /**
     * @var CategoryFilter
     */
    protected $categories;
    /**
     * @var Formatter
     */
    protected $formatter;
    /**
     * @var Message[]
     */
    protected $messages = [];
    /**
     * @var array
     */
    protected $levels = [];
    /**
     * @var array
     */
    protected $commonContext = [];
    /**
     * @var int
     */
    protected $exportInterval = 1000;
    /**
     * @var bool
     */
    protected $enabled = true;

    public function __construct()
    {
        $this->categories = new CategoryFilter();
        $this->formatter  = new Formatter();
    }

    abstract protected function write();

    /**
     * @param array $messages
     * @param bool $final
     * @return void
     */
    public function collect(array $messages, bool $final)
    {
        $this->filterMessages($messages);
        $count = count($this->messages);

        if ($count > 0 && ($final || ($this->exportInterval > 0 && $count >= $this->exportInterval))) {
            $oldExportInterval    = $this->exportInterval;
            $this->exportInterval = 0;
            $this->write();
            $this->exportInterval = $oldExportInterval;
            $this->messages       = [];
        }
    }

    /**
     * @param array $categories
     * @return $this
     */
    public function setCategories(array $categories)
    {
        $this->categories->include($categories);
        return $this;
    }

    /**
     * @param array $except
     * @return $this
     */
    public function setExcept(array $except)
    {
        $this->categories->exclude($except);
        return $this;
    }

    /**
     * @param array $levels
     * @return $this
     */
    public function setLevels(array $levels)
    {
        foreach ($levels as $key => $level) {
            $levels[$key] = Logger::validateLevel($level);
        }

        $this->levels = $levels;
        return $this;
    }

    /**
     * @param array $commonContext
     * @return $this
     */
    public function setCommonContext(array $commonContext)
    {
        $this->commonContext = $commonContext;
        return $this;
    }

    /**
     * @param callable $format
     * @return $this
     */
    public function setFormat(callable $format)
    {
        $this->formatter->setFormat($format);
        return $this;
    }

    /**
     * @param callable $prefix
     * @return $this
     */
    public function setPrefix(callable $prefix)
    {
        $this->formatter->setPrefix($prefix);
        return $this;
    }

    /**
     * @param int $exportInterval
     * @return $this
     */
    public function setExportInterval($exportInterval)
    {
        $this->exportInterval = $exportInterval;
        return $this;
    }

    /**
     * @param string $format
     * @return $this
     */
    public function setTimestampFormat(string $format)
    {
        $this->formatter->setTimestampFormat($format);
        return $this;
    }

    /**
     * @param callable $value
     * @return $this
     */
    public function setEnabled(callable $value)
    {
        $this->enabled = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function enable()
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disable()
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        if (is_bool($this->enabled)) {
            return $this->enabled;
        }

        if (!is_bool($enabled = ($this->enabled)())) {
            throw new RuntimeException(sprintf(
                'The PHP callable "enabled" must returns a boolean, %s received.',
                gettype($enabled)
            ));
        }

        return $enabled;
    }

    /**
     * @return Message[]
     */
    protected function getMessages()
    {
        return $this->messages;
    }

    /**
     * @return array
     */
    protected function getFormattedMessages()
    {
        $formatted = [];
        foreach ($this->messages as $key => $message) {
            $formatted[$key] = $this->formatter->format($message, $this->commonContext);
        }
        return $formatted;
    }

    /**
     * @param string $separator
     * @return string
     */
    protected function formatMessages($separator = '')
    {
        $formatted = '';
        foreach ($this->messages as $message) {
            $formatted .= $this->formatter->format($message, $this->commonContext) . $separator;
        }
        return $formatted;
    }

    /**
     * @return array
     */
    protected function getCommonContext()
    {
        return $this->commonContext;
    }

    /**
     * @param Message[] $messages
     * @return void
     */
    protected function filterMessages(array $messages): void
    {
        foreach ($messages as $i => $message) {
            if (!($message instanceof Message)) {
                throw new InvalidArgumentException('You must provide an instance of' . Message::class);
            }

            if ((!empty($this->levels) && !in_array(($message->level()), $this->levels, true))) {
                unset($messages[$i]);
                continue;
            }

            if ($this->categories->isExcluded($message->context('category', ''))) {
                unset($messages[$i]);
                continue;
            }

            $this->messages[] = $message;
        }
    }
}