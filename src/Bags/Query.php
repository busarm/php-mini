<?php

namespace Busarm\PhpMini\Bags;

use function Busarm\PhpMini\Helpers\http_parse_query;

/**
 * PHP Mini Framework
 *
 * @copyright busarm.com
 * @license https://github.com/Busarm/php-mini/blob/master/LICENSE (MIT License)
 * @link https://github.com/josantonius/php-session
 */
final class Query extends Bag
{
    /**
     * Set attributes from query string
     *
     * @param string $query
     * @return self
     */
    public function setQuery(string $query): self
    {
        if (!empty($list = http_parse_query($query))) {
            $this->attributes = $list;
        }
        return $this;
    }

    /**
     * Gets a string representation of the object
     *
     * @return string Returns the `string` representation of the object.
     */
    public function __toString()
    {
        return http_build_query($this->attributes);
    }
}
