<?php

namespace Jc\GraphQL;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;

class Upload
{
    /**
     * @var resource|string
     */
    protected $contents;

    /**
     * @var string|null
     */
    protected $filename;

    /**
     * @var string|null
     */
    protected $contentType;

    /**
     * @param mixed $contents
     * @param string|null $filename
     * @param string|null $contentType
     */
    public function __construct($contents, $filename = null, $contentType = null)
    {
        if (!($contents instanceof \Psr\Http\Message\StreamInterface)) {
            $contents = Utils::streamFor($contents);
        }

        $this->contents = $contents;
        $this->filename = $filename;
        $this->contentType = $contentType;
    }

    /**
     * @return resource|string
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * @return string|null
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @return string|null
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $path
     * @param string|null $filename
     * @param string|null $contentType
     * @return static
     */
    public static function fromPath($path, $filename = null, $contentType = null)
    {
        if(!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('File [%s] is not readable.', $path));
        }

        return new static(fopen($path, 'r'), $filename ?: basename($path), $contentType);
    }

    /**
     * @param resource $stream
     * @param string|null $filename
     * @param string|null $contentType
     * @return static
     */
    public static function fromStream($stream, $filename = null, $contentType = null)
    {
        if(!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream must be a valid resource.');
        }

        return new static($stream, $filename, $contentType);
    }

    public static function fromUrl($url, $filename = null, $contentType = null, $stream = false, Client $client = null)
    {
        $client = $client ?: new Client();

        $response = $client->request('GET', $url, [
            'stream' => $stream,
            'http_errors' => true,
        ]);

        if($stream) {
            $contentsOrStream = $response->getBody(); // Get the stream directly without reading into memory
        } else {
            // Read entire body into memory (seekable via constructor normalization)
            $contentsOrStream = $response->getBody()->getContents();
        }

        // Detect filename from headers if not provided
        if ($filename === null) {
            $disposition = $response->getHeaderLine('Content-Disposition');

            if ($disposition && preg_match('/filename="?([^"]+)"?/', $disposition, $matches)) {
                $filename = $matches[1];
            } else {
                $path = parse_url($url, PHP_URL_PATH);
                $filename = $path ? basename($path) : null;
            }

            if (!$filename) {
                $filename = 'upload';
            }
        }

        // Detect content type from headers if not provided
        if ($contentType === null) {
            $contentType = $response->getHeaderLine('Content-Type') ?: null;

            if ($contentType !== null) {
                // Strip charset etc.
                if (strpos($contentType, ';') !== false) {
                    $contentType = explode(';', $contentType)[0];
                }

                // Ignore obviously wrong types
                if ($contentType === 'text/html' || $contentType === '') {
                    $contentType = null;
                }
            }
        }

        return new static($contentsOrStream, $filename, $contentType);
    }

    public static function fromFile($file)
    {
        // Laravel UploadedFile / Symfony UploadedFile
        if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new static(fopen($file->getRealPath(), 'r'), $file->getClientOriginalName(), $file->getClientMimeType());
        }

        // SplFileInfo
        if ($file instanceof \SplFileInfo) {
            return new static(fopen($file->getRealPath(), 'r'), $file->getFilename(), null);
        }

        // Already a stream or string, let constructor normalize
        return new static($file);
    }
}