<?php
/**
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
 * @property-read string $path
 * @property-read null|string $encoding
 * @property-read string $eol
 * @property-read bool $evalBooleans
 * @property-read bool $evalNulls
 */
class Parser
{
    /** @var string */
    private $path;
    /** @var null|string */
    private $encoding;
    /** @var string */
    private $eol;
    /** @var bool */
    private $evalBooleans;
    /** @var bool */
    private $evalNulls;

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

        if (!preg_match('/[\w\_\-]+\.(yaml|yml)$/', $realPath)) {
            throw new ParserException('Given path is not a YAML file');
        }

        if (!is_readable($realPath)) {
            throw new ParserException(
                sprintf('YAML file "%s" is not readable', basename($realPath))
            );
        }

        $this->path = $realPath;
        $this->eol = PHP_EOL;
        $this->evalBooleans = true;
        $this->evalNulls = true;
    }

    /**
     * @param string $prop
     * @return mixed
     */
    public function __get(string $prop)
    {
        switch ($prop) {
            case "path":
            case "encoding":
            case "eol":
            case "evalBooleans":
            case "evalNulls":
                return $this->$prop;
        }

        throw new \DomainException('Cannot get value of inaccessible property');
    }

    /**
     * @param string $eol
     * @return Parser
     * @throws ParserException
     */
    public function eol(string $eol): self
    {
        if (!in_array($eol, ["", "\n", "\r\n"])) {
            throw new ParserException('Invalid EOL character');
        }

        $this->eol = $eol;
        return $this;
    }

    /**
     * Converts unquoted values (true/false, on/off, yes/no) to booleans
     * @param bool $trigger
     * @return Parser
     */
    public function evalBooleans(bool $trigger): self
    {
        $this->evalBooleans = $trigger;
        return $this;
    }

    /**
     * Converts unquoted values like NULL/null or ~ (tilde) to NULLs
     * @param bool $trigger
     * @return Parser
     */
    public function evalNulls(bool $trigger): self
    {
        $this->evalNulls = $trigger;
        return $this;
    }

    /**
     * @param string $mbEncoding
     * @return Parser
     */
    public function encoding(string $mbEncoding): self
    {
        if (!in_array($mbEncoding, mb_list_encodings())) {
            throw new \OutOfBoundsException('Not a valid multi-byte encoding');
        }

        $this->encoding = $mbEncoding;
        return $this;
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