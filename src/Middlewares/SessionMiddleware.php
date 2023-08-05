<?php

namespace Busarm\PhpMini\Middlewares;

use Busarm\PhpMini\Config;
use Busarm\PhpMini\Interfaces\MiddlewareInterface;
use Busarm\PhpMini\Interfaces\RequestHandlerInterface;
use Busarm\PhpMini\Interfaces\RequestInterface;
use Busarm\PhpMini\Interfaces\ResponseInterface;
use Busarm\PhpMini\Interfaces\RouteInterface;
use Busarm\PhpMini\Interfaces\SessionStoreInterface;
use SessionHandlerInterface;

/**
 * PHP Mini Framework
 *
 * @copyright busarm.com
 * @license https://github.com/Busarm/php-mini/blob/master/LICENSE (MIT License)
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private Config $config, private SessionStoreInterface|SessionHandlerInterface|null $session = null)
    {
    }

    /**
     * Middleware handler
     *
     * @param RequestInterface|RouteInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(RequestInterface|RouteInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request instanceof RequestInterface && $this->config->sessionEnabled) {

            if ($this->session) {
                if ($this->session instanceof SessionStoreInterface) {
                    $request->setSession($this->session);
                } else {
                    $request->session()->setHandler($this->session);
                }
            }

            if (!$request->session()->isStarted()) {
                $request->session()->start();
            }
        }
        return $handler->handle($request);
    }
}