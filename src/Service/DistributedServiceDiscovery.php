<?php

namespace Busarm\PhpMini\Service;

use Busarm\PhpMini\Dto\ServiceRegistryDto;
use Busarm\PhpMini\Enums\HttpMethod;
use Busarm\PhpMini\Interfaces\DistributedServiceDiscoveryInterface;
use Busarm\PhpMini\Interfaces\ServiceClientInterface;
use Busarm\PhpMini\Interfaces\ServiceDiscoveryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Error Reporting
 * 
 * PHP Mini Framework
 *
 * @copyright busarm.com
 * @license https://github.com/Busarm/php-mini/blob/master/LICENSE (MIT License)
 */
class DistributedServiceDiscovery implements DistributedServiceDiscoveryInterface, ServiceDiscoveryInterface
{
    /**
     * @param array<string,ServiceRegistryDto> $services
     */
    protected array $registry = [];

    /**
     *
     * @param RemoteClient $client Current client to be registered on the service registry
     * @param string $endpoint Service registry endpoint
     * - Endpoint must support
     * -- GET /{name}       - Get service client by name. Format = `{"name":"<name>", "url":"<url>"}`
     * -- POST /            - Register service client
     * -- DELETE /{name}    - Unregister service client. Accepts `url` as query param.
     * @param integer $ttl Service registry cache ttl (seconds). Re-load after ttl. Default: 300secs
     * @param integer $timeout Service registry request timeout (seconds). Default: 10secs
     */
    public function __construct(private RemoteClient $client, private string $endpoint, private $ttl = 300, private $timeout = 10)
    {
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): ?ServiceRegistryDto
    {
        if ($name == $this->client->getName()) return null;

        /** @var ServiceRegistryDto|null */
        $service = $this->registry[$name] ?? null;

        if (!empty($service)) {
            if (($service->expiresAt > time())) {
                return $service;
            } else {
                $http = new Client([
                    'timeout'  => $this->timeout,
                ]);
                $response = $http->request(
                    HttpMethod::GET,
                    $this->endpoint . "/$name",
                    [
                        RequestOptions::VERIFY => false
                    ]
                );
                if ($response && $response->getStatusCode() == 200 && !empty($result = json_decode($response->getBody(), true))) {
                    if (isset($result['name']) && isset($result['url'])) {
                        return $this->registry[$result['name']] = new ServiceRegistryDto($result['name'], $result['url'], time() + $this->ttl);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $http = new Client([
            'timeout'  => $this->timeout,
        ]);
        $http->requestAsync(
            HttpMethod::POST,
            $this->endpoint,
            [
                RequestOptions::VERIFY => false,
                RequestOptions::BODY => [
                    'name' => $this->client->getName(),
                    'url' => $this->client->getLocation()
                ]
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function unregister(): void
    {
        $http = new Client([
            'timeout'  => $this->timeout,
        ]);
        $http->requestAsync(
            HttpMethod::DELETE,
            $this->endpoint . "/" . $this->client->getName(),
            [
                RequestOptions::VERIFY => false,
                RequestOptions::QUERY => [
                    'url' => $this->client->getLocation()
                ]
            ]
        );
    }

    /**
     * Get service client
     *
     * @param string $name Service Name
     * @return ?ServiceClientInterface
     */
    public function getServiceClient(string $name): ?ServiceClientInterface
    {
        if (!empty($service = $this->get($name))) {
            return new RemoteClient($service->name, $service->url);
        }
        return null;
    }

    /**
     * Get list of service client
     * @return ServiceClientInterface[]
     */
    public function getServiceClients(): array
    {
        return array_map(
            function (ServiceRegistryDto $service) {
                $service = $this->get($service->name);
                return new RemoteClient($service->name, $service->url);
            },
            $this->registry
        );
    }

    /**
     * Get `name=>location` map list of service clienta
     * 
     * @return array<string,string>
     */
    public function getServiceClientsMap(): array
    {
        return array_reduce($this->getServiceClients(), function ($carry, ServiceClientInterface $current) {
            $carry[$current->getName()] = $current->getLocation();
            return $carry;
        }, []);
    }
}