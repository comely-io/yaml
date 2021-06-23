<?php
/*
 * This file is a part of "comely-io/yaml" package.
 * https://github.com/comely-io/yaml
 *
 * Copyright (c) Furqan A. Siddiqui <hello@furqansiddiqui.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit following link:
 * https://github.com/comely-io/yaml/blob/master/LICENSE
 */

declare(strict_types=1);

namespace Comely\Yaml;

use Comely\Yaml\Exception\ParseLineException;
use Comely\Yaml\Exception\ParserException;
use Comely\Yaml\Parser\Buffer;
use Comely\Yaml\Parser\Line;

/**
 * Class Parser
 * @package Comely\Yaml
 */
class Parser
{
    /** @var string */
    private string $path;
    /** @var string */
    private string $eol = PHP_EOL;
    /** @var bool */
    private bool $evalBooleans = true;
    /** @var bool */
    private bool $evalNulls = true;
    /** @var null|string */
    private ?string $mbEncoding = null;

    /**
     * Parser constructor.
     * @param string $yamlFile
     * @throws ParserException
     */
    public function __construct(string $yamlFile)
    {
        $realPath = realpath($yamlFile);
        if (!$realPath) {
            throw new ParserException(sprintf('YAML file "%s" does not exist', basename($yamlFile)));
        }

        if (!preg_match('/[\w\-]+\.(yaml|yml)$/', $realPath)) {
            throw new ParserException('Given path is not a YAML file');
        }

        if (!is_readable($realPath)) {
            throw new ParserException(
                sprintf('YAML file "%s" is not readable', basename($realPath))
            );
        }

        $this->path = $realPath;
    }

    /**
     * @param string|null $eolChar
     * @param bool|null $evaluateBooleans
     * @param bool|null $evaluateNulls
     * @param string|null $mbEncoding
     * @return $this
     */
    public function options(?string $eolChar = null, ?bool $evaluateBooleans = null, ?bool $evaluateNulls = null, ?string $mbEncoding = null): self
    {
        if (is_string($eolChar)) {
            if (!in_array($eolChar, ["\n", "\r\n"])) {
                throw new \InvalidArgumentException('Invalid EOL character');
            }

            $this->eol = $eolChar;
        }

        if (is_bool($evaluateBooleans)) {
            $this->evalBooleans = $evaluateBooleans;
        }

        if (is_bool($evaluateNulls)) {
            $this->evalNulls = $evaluateNulls;
        }

        if (is_string($mbEncoding)) {
            if (!in_array($mbEncoding, mb_list_encodings())) {
                throw new \OutOfBoundsException('Not a valid multi-byte encoding');
            }

            $this->mbEncoding = $mbEncoding;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function filePath(): string
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function eolChar(): string
    {
        return $this->eol;
    }

    /**
     * @return string|null
     */
    public function mbEncoding(): ?string
    {
        return $this->mbEncoding;
    }

    /**
     * @return bool
     */
    public function evaluateNulls(): bool
    {
        return $this->evalNulls;
    }

    /**
     * @return bool
     */
    public function evaluateBooleans(): bool
    {
        return $this->evalBooleans;
    }

    /**
     * @return array
     * @throws ParserException
     */
    public function generate(): array
    {
        $buffer = new Buffer($this);
        $lines = file_get_contents($this->path);
        if ($lines === false) {
            throw new ParserException(sprintf('Failed to read YAML file "%s"', basename($this->path)));
        } elseif (!$lines) {
            throw new ParserException(sprintf('YAML file "%s" is blank', basename($this->path)));
        }

        try {
            $lines = explode($this->eol, $lines);
            $num = 1;
            foreach ($lines as $line) {
                $line = new Line($this, $num, $line);
                $buffer->append($line);
                $num++;
            }

            return $buffer->parse();
        } catch (ParseLineException $e) {
            $line = $e->line();
            throw new ParserException(
                sprintf('%s in file "%s" on line %d', $e->getMessage(), basename($this->path), $line->num)
            );
        }
    }
}
