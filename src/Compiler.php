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

use Comely\Yaml\Exception\CompilerException;

/**
 * Class Compiler
 * @package Comely\Yaml
 */
class Compiler
{
    /** @var array */
    private $data;
    /** @var int */
    private $indent;
    /** @var string */
    private $eol;

    /**
     * Compiler constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->indent = 2;
        $this->eol = PHP_EOL;
    }

    /**
     * @param string $eol
     * @return Compiler
     * @throws CompilerException
     */
    public function eol(string $eol): self
    {
        if (!in_array($eol, ["", "\n", "\r\n"])) {
            throw new CompilerException('Invalid EOL character');
        }

        $this->eol = $eol;
        return $this;
    }

    /**
     * @param int $indent
     * @return Compiler
     * @throws CompilerException
     */
    public function indents(int $indent = 2): self
    {
        if ($indent < 2 || $indent > 8) {
            throw new CompilerException(sprintf('"%d" is an invalid indent value', $indent));
        }

        return $this;
    }

    /**
     * @return string
     * @throws CompilerException
     */
    public function generate(): string
    {
        $headers[] = "# This YAML file has been compiled using Comely YAML component";
        $headers[] = "# https://github.com/comely-io/yaml";

        $compiled = $this->compile($this->data);
        $compiled = implode($this->eol, $headers) . str_repeat($this->eol, 2) . $compiled;

        return $compiled;
    }

    /**
     * @param array $input
     * @param string|null $parent
     * @param int $tier
     * @return string
     * @throws CompilerException
     */
    private function compile(array $input, ?string $parent = null, int $tier = 0): string
    {
        $compiled = "";
        $indent = $this->indent * $tier;

        // Last value type
        // 1: Scalar, 0: Non-scalar
        $lastValueType = 1;

        // Iterate input
        foreach ($input as $key => $value) {
            // All tier-1 keys have to be string
            if ($tier === 1 && !is_string($key)) {
                throw new CompilerException('All tier 1 keys must be string');
            }

            if (is_scalar($value) || is_null($value)) {
                // Value is scalar or NULL
                if ($lastValueType !== 1) {
                    // A blank line is last value type was not scalar
                    $compiled .= $this->eol;
                }

                // Current value type
                $lastValueType = 1; // This value is scalar or null

                // Necessary indents
                $compiled .= $this->indent($indent);

                // Set mapping key or sequence
                if (is_string($key)) {
                    $compiled .= sprintf('%s: ', $key);
                } else {
                    $compiled .= "- ";
                }

                // Value
                switch (gettype($value)) {
                    case "boolean":
                        $compiled .= $value === true ? "true" : "false";
                        break;
                    case "NULL":
                        $compiled .= "~";
                        break;
                    case "integer":
                    case "double":
                        $compiled .= $value;
                        break;
                    default:
                        // Definitely a string
                        if (strpos($value, $this->eol)) {
                            // String has line-breaks
                            $compiled .= "|" . $this->eol;
                            $lines = explode($this->eol, $value);
                            $subIndent = $this->indent(($indent + $this->indent));

                            foreach ($lines as $line) {
                                $compiled .= $subIndent;
                                $compiled .= $line . $this->eol;
                            }
                        } elseif (strlen($value) > 75) {
                            // Long string
                            $compiled .= ">" . $this->eol;
                            $lines = explode($this->eol, wordwrap($value, 75, $this->eol));
                            $subIndent = $this->indent(($indent + $this->indent));

                            foreach ($lines as $line) {
                                $compiled .= $subIndent;
                                $compiled .= $line . $this->eol;
                            }
                        } else {
                            // Simple string
                            $compiled .= $value;
                        }
                }

                $compiled .= $this->eol;
            } else {

                // Current value type
                $lastValueType = 0; // This value is Non-scalar

                if (is_object($value)) {
                    // Directly convert to an Array, JSON is cleanest possible way
                    $value = json_decode(json_encode($value), true);
                }

                // Whether value was Array, or is now Array after conversion from object
                if (is_array($value)) {
                    $compiled .= $this->indent($indent);
                    $compiled .= sprintf('%s:%s', $key, $this->eol);
                    $compiled .= $this->compile($value, strval($key), $tier + 1);
                }
            }
        }

        if (!$compiled || ctype_space($compiled)) {
            throw new CompilerException(sprintf('Failed to compile YAML for key "%s"', $parent));
        }

        $compiled .= $this->eol;

        return $compiled;
    }

    /**
     * @param int $count
     * @return string
     */
    private function indent(int $count): string
    {
        return str_repeat(" ", $count);
    }
}