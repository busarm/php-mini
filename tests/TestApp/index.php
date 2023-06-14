<?php

/**
 * @var \Psr\Http\Message\ServerRequestInterface|null $request Capture Server request
 * @var \Busarm\PhpMini\Interfaces\ServiceDiscoveryInterface|null $discovery Capture Service discovery
 */

use Busarm\PhpMini\App;
use Busarm\PhpMini\Config;
use Busarm\PhpMini\Configs\HttpConfig;
use Busarm\PhpMini\Enums\Env;
use Busarm\PhpMini\Interfaces\RequestInterface;
use Busarm\PhpMini\Request;
use Busarm\PhpMini\Response;
use Busarm\PhpMini\Service\LocalServiceDiscovery;
use Busarm\PhpMini\Test\TestApp\Controllers\AuthTestController;
use Busarm\PhpMini\Test\TestApp\Controllers\HomeTestController;

require __DIR__ . '/../../vendor/autoload.php';

$config = (new Config())
    ->setAppPath(__DIR__)
    ->setEncryptionKey("ds3d5Posdf@nZods!mfo")
    ->setCookieEncrypt(false)
    ->setHttp((new HttpConfig)
        ->setCheckCors(true)
        ->setAllowAnyCorsDomain(true)
        ->setAllowedCorsHeaders(['*'])
        ->setAllowedCorsMethods(['GET']));

$app = new App($config, Env::LOCAL);
$app->setServiceDiscovery($discovery ?? new LocalServiceDiscovery([]));

$app->get('ping')->to(HomeTestController::class, 'ping');
$app->get('pingHtml')->call(function (App $app) {
    return 'success-' . $app->env;
});
$app->get('auth/test')->to(AuthTestController::class, 'test');
$app->get('test')->call(function (RequestInterface $req, App $app) {
    return [
        'name' => 'v1',
        'discovery' => $app->serviceDiscovery?->getServiceClientsMap(),
        'headers' => $req->header()->all(),
        'server' => $req->server()->all(),
        'cookies' => $req->cookie()->all(),
        'session' => $req->session()?->all(),
        'currentUrl' => $req->currentUrl(),
        'baseUrl' => $req->baseUrl(),
        'ip' => $req->ip(),
        'correlationId' => $req->correlationId(),
    ];
});

$app->get('download')->call(function (Response $response) {
    return $response->downloadFile(__DIR__ . '../../../README.md', 'README.md', true);
});

return $app->run(Request::capture($request ?? null, $config))->send($config->http->sendAndContinue);
