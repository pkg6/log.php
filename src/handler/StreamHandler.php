<?php

namespace Pkg6\Log\handler;

use InvalidArgumentException;
use Pkg6\Log\Handler;
use RuntimeException;

class StreamHandler extends Handler
{
    /**
     * @var mixed|string
     */
    protected $stream;

    public function __construct($stream = 'php://stdout')
    {
        $this->stream = $stream;
        parent::__construct();
    }

    protected function write()
    {
        $stream = $this->createStream();
        flock($stream, LOCK_EX);

        if (fwrite($stream, $this->formatMessages("\n")) === false) {
            flock($stream, LOCK_UN);
            fclose($stream);
            throw new RuntimeException(sprintf(
                'Unable to export the log because of an error writing to the stream: %s',
                error_get_last()['message'] ?? '',
            ));
        }
        $this->stream = stream_get_meta_data($stream)['uri'];
        flock($stream, LOCK_UN);
        fclose($stream);
    }

    protected function createStream()
    {
        $stream = $this->stream;

        if (is_string($stream)) {
            $stream = @fopen($stream, 'ab');
            if ($stream === false) {
                throw new RuntimeException(sprintf(
                    'The "%s" stream cannot be opened.',
                    (string)$this->stream,
                ));
            }
        }

        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException(sprintf(
                'Invalid stream provided. It must be a string stream identifier or a stream resource, "%s" received.',
                gettype($stream),
            ));
        }

        return $stream;
    }
}