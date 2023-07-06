<?php

namespace Busarm\PhpMini\Request\Attributes;

use Attribute;
use Busarm\PhpMini\App;
use Busarm\PhpMini\Interfaces\Attribute\ParameterAttributeInterface;
use Busarm\PhpMini\Interfaces\RequestInterface;
use Busarm\PhpMini\Interfaces\Resolver\AuthResolver;
use Busarm\PhpMini\Interfaces\RouteInterface;
use Busarm\PhpMini\Resolvers\Auth;
use Busarm\PhpMini\Traits\TypeResolver;
use ReflectionParameter;

/**
 * Request auth user resolver
 * 
 * PHP Mini Framework
 *
 * @copyright busarm.com
 * @license https://github.com/Busarm/php-mini/blob/master/LICENSE (MIT License)
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class AuthParam implements ParameterAttributeInterface
{

    use TypeResolver;

    public function __construct(private string $name, private bool $sanitize = false)
    {
    }

    /**
     * @inheritDoc
     */
    public function processParameter(ReflectionParameter $parameter, mixed $value = null, App $app, null|RequestInterface|RouteInterface $request = null): mixed
    {
        if (
            $request instanceof RequestInterface && $parameter
            && ($parameter->getType() == AuthResolver::class || $parameter->getType() == Auth::class)
        ) {
            return $request->auth();
        }
        return null;
    }
}