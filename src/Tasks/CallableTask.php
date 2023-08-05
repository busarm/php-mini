<?php

namespace Busarm\PhpMini\Tasks;

use Closure;

/**
 * PHP Mini Framework
 *
 * @copyright busarm.com
 * @license https://github.com/Busarm/php-mini/blob/master/LICENSE (MIT License)
 */
final class CallableTask extends Task
{

    public function __construct(protected Closure $callable, protected array $data = [])
    {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    public function run(): mixed
    {
        if ($this->callable && is_callable($this->callable)) {
            return call_user_func($this->callable, $this->data) ?? null;
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getParams(): array
    {
        return [
            'callable' => $this->callable,
            'data' => $this->data,
        ];
    }
}