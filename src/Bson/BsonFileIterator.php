<?php

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace GeckoPackages\Bson;

/**
 * Iterator (reader) for BSON files.
 *
 * Reads BSON files @see https://en.wikipedia.org/wiki/BSON as produced by
 * `mongodump` @see https://docs.mongodb.com/manual/reference/program/mongodump/
 * into JSON encoded strings.
 *
 * @api
 *
 * @author SpacePossum
 */
final class BsonFileIterator implements \Iterator
{
    const CONSTRUCT_ARRAY = 2;
    const CONSTRUCT_JSON = 1;
    const CONSTRUCT_STD = 3;

    /**
     * @var string|false
     */
    private $content = false;

    /**
     * @var resource
     */
    private $fileHandle;

    /**
     * @var int >= 0
     */
    private $key = 0;

    /**
     * @var int >= 0
     */
    private $maxUnpackSize;

    /**
     * @var callable
     */
    private $decoder;

    /**
     * @param string|\SplFileInfo $file               file to read
     * @param int                 $constructType      see class "CONSTRUCT_*" constants
     * @param int                 $maxUnpackSize      in bytes, > 0, never bigger than the file size, default 5MiB
     * @param int                 $jsonDecodeMaxDepth used for "CONSTRUCT_ARRAY" and "CONSTRUCT_STD", @see https://secure.php.net/manual/en/function.json-decode.php
     * @param int                 $jsonDecodeOptions  used for "CONSTRUCT_ARRAY" and "CONSTRUCT_STD", @see https://secure.php.net/manual/en/function.json-decode.php
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        $file,
        int $constructType = 1,
        int $maxUnpackSize = 5242880, // 5*1024*1024 bytes
        int $jsonDecodeMaxDepth = 512,
        int $jsonDecodeOptions = 0
    ) {
        $this->resolveFileHandle($file);
        $this->resolveDecoder($constructType, $jsonDecodeMaxDepth, $jsonDecodeOptions);
        $this->resolveMaxUnpackSize($maxUnpackSize, $file instanceof \SplFileInfo ? $file->getPathname() : $file);
    }

    public function __destruct()
    {
        @fclose($this->fileHandle); // best effort
    }

    /**
     * Return type based on config passes during construction.
     *
     * @return string|array|\stdClass|false
     */
    public function current()
    {
        return $this->content;
    }

    /**
     * Integer set to 0 on start, increments on reading an item from file.
     *
     * Note: the key is always set as the position of an item in a file.
     * It is _not_ derived from the data within the file (therefor also not
     * from the items it contains).
     *
     * @return int >= 0
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException
     */
    public function next()
    {
        $this->content = fread($this->fileHandle, 4);
        if (feof($this->fileHandle)) {
            $this->content = false;

            return;
        }

        fseek($this->fileHandle, -4, SEEK_CUR);
        $this->content = unpack('V', $this->content);
        $length = array_shift($this->content);

        if ($length > $this->maxUnpackSize) {
            throw new \UnexpectedValueException(sprintf(
                'Invalid data at item #%d, size %d exceeds max. unpack size %d.',
                1 + $this->key, $length, $this->maxUnpackSize
            ));
        }

        $decoder = $this->decoder;
        $this->content = $decoder($this->key, \MongoDB\BSON\toJSON(fread($this->fileHandle, $length)));

        ++$this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        fseek($this->fileHandle, 0);
        $this->next(); // implicit sets `$this->content`
        $this->key = 0;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return false !== $this->content;
    }

    private function resolveFileHandle($file)
    {
        if ($file instanceof \SplFileInfo) {
            $file = $file->getPathname();
        } elseif (!is_string($file)) {
            throw new \InvalidArgumentException(sprintf(
                '%s is not a file.',
                is_object($file) ? get_class($file) : gettype($file).(is_resource($file) ? '' : '#"'.$file.'"')
            ));
        }

        if (!is_file($file)) {
            throw new \InvalidArgumentException(sprintf('%s is not a file.', is_dir($file) ? 'directory#"'.$file.'"' : '"'.$file.'"'));
        }

        if (!is_readable($file)) {
            throw new \InvalidArgumentException(sprintf('file "%s" is not readable.', $file));
        }

        $fileHandle = @fopen($file, 'rb');
        if (false === $fileHandle) {
            $error = error_get_last();
            throw new \RuntimeException(sprintf(
                'Failed to open file "%s" for reading.%s',
                $file, null === $error ? '' : ' '.$error['message']
            ));
        }

        $this->fileHandle = $fileHandle;
    }

    private function resolveDecoder(int $constructType, int $jsonDecodeMaxDepth, int $jsonDecodeOptions)
    {
        switch ($constructType) {
            case self::CONSTRUCT_JSON:
                $this->decoder = function ($key, $content) {
                    return $content;
                };

                return;
            case self::CONSTRUCT_ARRAY:
                $assoc = true;

                break;
            case self::CONSTRUCT_STD:
                $assoc = false;

                break;
            default:
                throw new \InvalidArgumentException(sprintf(
                    'Construct type must be any of integers "%s" got "%d".',
                    implode(', ', [self::CONSTRUCT_JSON, self::CONSTRUCT_ARRAY, self::CONSTRUCT_STD]), $constructType
                ));
        }

        if ($jsonDecodeMaxDepth < 1) {
            throw new \InvalidArgumentException(sprintf(
                'Expected integer > 0 for JSON decode max depth, got "%s".',
                is_object($jsonDecodeMaxDepth) ? get_class($jsonDecodeMaxDepth) : gettype($jsonDecodeMaxDepth).(is_resource($jsonDecodeMaxDepth) ? '' : '#'.$jsonDecodeMaxDepth)
            ));
        }

        $this->decoder = function ($key, $content) use ($assoc, $jsonDecodeMaxDepth, $jsonDecodeOptions) {
            $content = @json_decode($content, $assoc, $jsonDecodeMaxDepth, $jsonDecodeOptions);
            if (null === $content) {
                throw new \UnexpectedValueException(sprintf('Invalid JSON "%s" at item #%d.', json_last_error_msg(), 1 + $key));
            }

            return $content;
        };
    }

    /**
     * @param mixed  $maxUnpackSize
     * @param string $file
     *
     * @throws \InvalidArgumentException
     */
    private function resolveMaxUnpackSize(int $maxUnpackSize, string $file)
    {
        if ($maxUnpackSize <= 0) {
            throw new \InvalidArgumentException(sprintf(
                'Expected integer > 0 for max. unpack size, got "%s".',
                is_object($maxUnpackSize) ? get_class($maxUnpackSize) : gettype($maxUnpackSize).(is_resource($maxUnpackSize) ? '' : '#'.$maxUnpackSize)
            ));
        }

        $this->maxUnpackSize = min($maxUnpackSize, filesize($file));
    }
}
