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

/**
 * Class Yaml
 * @package Comely\Yaml
 */
class Yaml
{
    /** string Version (Major.Minor.Release-Suffix) */
    public const VERSION = "2.0.0";
    /** int Version (Major * 10000 + Minor * 100 + Release) */
    public const VERSION_ID = 20000;

    /**
     * @param string $yamlFile
     * @return Parser
     * @throws Exception\ParserException
     */
    public static function Parse(string $yamlFile): Parser
    {
        return new Parser($yamlFile);
    }

    /**
     * @param array $data
     * @return Compiler
     */
    public static function Compile(array $data): Compiler
    {
        return new Compiler($data);
    }
}
