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

namespace Comely\Yaml\Exception;

use Comely\Yaml\Parser\Line;
use Throwable;

/**
 * Class ParseLineException
 * @package Comely\Yaml\Exception
 */
class ParseLineException extends ParserException
{
    /** @var Line */
    private $line;

    /**
     * ParseLineException constructor.
     * @param Line $line
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(Line $line, string $message = "", int $code = 0, Throwable $previous = null)
    {
        $this->line = $line;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return Line
     */
    public function line(): Line
    {
        return $this->line;
    }
}