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

namespace Comely\Yaml\Parser;

use Comely\Yaml\Exception\ParseLineException;
use Comely\Yaml\Parser;

/**
 * Class Line
 * @package Comely\Yaml\Parser
 */
class Line
{
    /** @var string */
    public string $raw;
    /** @var int */
    public int $num;
    /** @var int */
    public int $len;
    /** @var int */
    public int $indent;
    /** @var string|null */
    public ?string $key = null;
    /** @var string|null */
    public ?string $value = null;

    /**
     * Line constructor.
     * @param Parser $parser
     * @param int $num
     * @param string $line
     * @throws ParseLineException
     */
    public function __construct(Parser $parser, int $num, string $line)
    {
        $this->raw = $line;
        $this->num = $num;

        if ($line && $line[0] === "\t") {
            throw new ParseLineException($this, 'Line cannot be intended by a tab character');
        }

        $mbEncoding = $parser->mbEncoding();
        $this->len = $mbEncoding ? mb_strlen($this->raw, $mbEncoding) : strlen($this->raw);
        $trimmedLen = $mbEncoding ? mb_strlen(ltrim($this->raw), $mbEncoding) : strlen(ltrim($this->raw));
        $this->indent = $this->len - $trimmedLen;

        if (!$this->raw || preg_match('/^\s*$/', $this->raw)) {
            return; // Blank line
        } elseif (preg_match('/^\s*#/', $this->raw)) {
            return; // Full line comment
        }

        // Clear any inline comment
        $line = trim(preg_split("/(#)(?=(?:[^\"']|[\"'][^\"']*[\"'])*$)/", $line, 2)[0]);

        // Check if line has a key
        if (preg_match('/^\s*[\w\-.]+:/', $line)) {
            // Key exists, split into key/value pair
            $line = preg_split("/:/", $line, 2);
            $this->key = trim($line[0]);
            $this->value = trim(strval($line[1] ?? ""));
        } else {
            // Key doesn't exist, set entire line as value
            $this->value = trim($line);
        }

        if (!$line) {
            $this->value = null;
        }
    }
}
