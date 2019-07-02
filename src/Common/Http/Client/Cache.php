<?php
/**
 * SocialConnect project
 * @author: Patsura Dmitry https://github.com/ovr <talk@dmtry.me>
 */

namespace SocialConnect\Common\Http\Client;

use Psr\Cache\CacheItemPoolInterface;
use SocialConnect\Common\Http\Response;

class Cache extends Client
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    /**
     * @param Client $client
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(Client $client, CacheItemPoolInterface $cache)
    {
        $this->client = $client;
        $this->cache = $cache;
    }

    /**
     * @param string $url
     * @param array $parameters
     * @return string|null
     */
    protected function makeCacheKey($url, array $parameters = array())
    {
        $cacheKey = $url;

        if ($parameters) {
            foreach ($parameters as $key => $value) {
                $cacheKey .= $key . '-' . $value;
            }
        }

        return 'sc:' . md5($cacheKey);
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $url, array $options = [], array $headers = [], string $method = Client::GET): Response
    {
        if ($method != Client::GET) {
            return $this->client->request($url, $options, $headers, $method);
        }

        $key = $this->makeCacheKey($url, $options);
        if ($key && $this->cache->contains($key)) {
            return $this->cache->fetch($key);
        }

        $response = $this->client->request($url, $options, $headers, $method);
        $noCache = $response->hasHeader('Pragma') && $response->getHeader('Pragma') == 'no-cache';

        if (!$noCache && $response->hasHeader('Expires')) {
            $expires = new \DateTime($response->getHeader('Expires'));
            $lifeTime = $expires->getTimestamp() - time() - 60;

            if ($key) {
                $this->cache->save($key, $response, $lifeTime);
            }
        }

        return $response;
    }
}