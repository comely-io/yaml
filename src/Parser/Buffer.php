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

namespace Comely\Yaml\Parser;

use Comely\Yaml\Exception\ParseLineException;
use Comely\Yaml\Exception\ParserException;
use Comely\Yaml\Parser;
use Comely\Yaml\Yaml;

/**
 * Class Buffer
 * @package Comely\Yaml\Parser
 */
class Buffer
{
    /** @var Parser */
    public $parser;
    /** @var int */
    public $indent;
    /** @var null|string */
    public $key;
    /** @var null|string */
    public $type;
    /** @var array */
    public $lines;

    /**
     * Buffer constructor.
     * @param Parser $parser
     * @param int $indent
     * @param string|null $key
     * @param string|null $type
     */
    public function __construct(Parser $parser, int $indent = 0, ?string $key = null, ?string $type = null)
    {
        if ($type && !in_array($type, [">", "|"])) {
            throw new \InvalidArgumentException('Invalid buffer type');
        }

        $this->parser = $parser;
        $this->indent = $indent;
        $this->key = $key;
        $this->type = $type;
        $this->lines = [];
    }

    /**
     * @param Line $line
     * @return Buffer
     */
    public function append(Line $line): self
    {
        $this->lines[] = $line;
        return $this;
    }

    /**
     * @param int $indent
     * @param string|null $key
     * @param string|null $type
     * @return Buffer
     */
    private function createSubBuffer(int $indent = 0, ?string $key = null, ?string $type = null): self
    {
        return new self($this->parser, $indent, $key, $type);
    }

    /**
     * @return array|null
     * @throws ParseLineException
     * @throws ParserException
     */
    public function parse(): ?array
    {
        $parsed = [];
        /** @var null|Buffer $subBuffer */
        $subBuffer = null;

        /** @var Line $line */
        foreach ($this->lines as $line) {
            if (isset($subBuffer)) {
                if (!$line->key && !$line->value) {
                    $subBuffer->append($line);
                    continue;
                }

                if ($line->indent > $subBuffer->indent) {
                    $subBuffer->append($line);
                    continue;
                }

                $parsed[$subBuffer->key] = $subBuffer->parse();
                unset($subBuffer);
            }

            // No key, no value
            if (!$line->key && !$line->value) {
                continue; // Ignore empty line
            }

            // Has key but no value = assoc array
            if ($line->key && !$line->value) {
                $subBuffer = $this->createSubBuffer($line->indent, $line->key);
                continue;
            }

            // Has both key and a value
            if ($line->key && $line->value) {
                // Long string buffer
                if (in_array($line->value, [">", "|"])) {
                    $subBuffer = $this->createSubBuffer($line->indent, $line->key, $line->value);
                    continue;
                }

                // Set key/value pair
                $parsed[$line->key] = $this->getLineValue($line);
                continue;
            }

            // Has value but no key
            if (!$line->key && $line->value) {
                // Long strings buffer
                if (in_array($this->type, [">", "|"])) {
                    $parsed[] = $line->value;
                    continue;
                }

                // Sequences
                if ($line->value[0] === "-") {
                    $line->value = trim(substr($line->value, 1));
                    $value = $this->getLineValue($line);
                    if ($this->key === "imports") {
                        if (!is_string($value)) {
                            throw new ParseLineException($line, 'Variable "imports" must be sequence of Yaml files');
                        }

                        $importPath = dirname($this->parser->path) . DIRECTORY_SEPARATOR . trim($value, DIRECTORY_SEPARATOR);

                        try {
                            $parser = Yaml::Parse($importPath)
                                ->eol($this->parser->eol)
                                ->evalNulls($this->parser->evalNulls)
                                ->evalBooleans($this->parser->evalBooleans);
                            $value = $parser->generate(); // returns array
                        } catch (ParserException $e) {
                            throw new ParserException(
                                sprintf(
                                    '%s, imported in "%s" on line %d',
                                    $e->getMessage(),
                                    basename($this->parser->path),
                                    $line->num
                                )
                            );
                        }
                    }

                    $parsed[] = $value;
                    continue;
                }
            }
        }

        // Check for any sub buffer at end of lines
        if (isset($subBuffer)) {
            $parsed[$subBuffer->key] = $subBuffer->parse();
        }

        // Empty arrays will return null
        if (!count($parsed)) {
            $parsed = null;
        }

        // Long string buffers
        if (is_array($parsed) && in_array($this->type, [">", "|"])) {
            $glue = $this->type === ">" ? " " : $this->parser->eol;
            $parsed = implode($glue, $parsed);
        }

        // Result cannot be empty if no-parent
        if (!$parsed && !$this->key) {
            throw new ParserException(
                sprintf('Corrupt YAML file format or line endings in "%s"', basename($this->parser->path))
            );
        }

        // Merge imports
        $imports = $parsed["imports"] ?? null;
        if (is_array($imports)) {
            unset($parsed["imports"]);
            array_push($imports, $parsed);
            $parsed = call_user_func_array("array_replace_recursive", $imports);
        }

        return $parsed;
    }

    /**
     * @param Line $line
     * @return bool|float|int|string|null
     * @throws ParseLineException
     */
    private function getLineValue(Line $line)
    {
        if (!$line->value) {
            return null;
        }

        $isQuoted = false;
        $value = $line->value;

        // Is quoted string?
        if (in_array($value[0], ["'", '"'])) {
            if (substr($value, -1) !== $value[0]) {
                throw new ParseLineException($line, 'Unmatched string start and end quote');
            }

            $isQuoted = true;
            $value = substr($value, 1, -1);
        }

        // Is not quoted string, evaluate boolean or NULL values?
        if (!$isQuoted) {
            $lowercaseValue = strtolower($value);
            // Null Types
            if ($this->parser->evalNulls && in_array($lowercaseValue, ["~", "null"])) {
                return null;
            }

            // Evaluate Booleans?
            if ($this->parser->evalBooleans) {
                if (in_array($lowercaseValue, ["true", "false", "on", "off", "yes", "no"])) {
                    return in_array($lowercaseValue, ["true", "on", "yes"]) ? true : false;
                }
            }

            // Integers
            if (preg_match('/^\-?[0-9]+$/', $value)) {
                return intval($value);
            }

            // Floats
            if (preg_match('/^\-?[0-9]+\.[0-9]+$/', $value)) {
                return floatval($value);
            }
        }

        return $value;
    }
}