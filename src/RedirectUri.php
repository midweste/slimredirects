<?php


namespace Midweste\SlimRedirects;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Slim\HttpCache\Cache;
use Slim\Psr7\Uri;

class RedirectUri extends Uri
{
    protected $uri;
    protected $statusCode;
    protected $reasonPhrase = '';

    public function __construct(UriInterface $uri, int $statusCode = 302)
    {
        $this->withStatusCode($statusCode);
        $userInfo = explode(':', $this->getUserInfo());
        parent::__construct(
            $uri->getScheme(),
            $uri->getHost(),
            $uri->getPort(),
            $uri->getPath(),
            $uri->getQuery(),
            $uri->getFragment(),
            (!empty($userInfo[0])) ? $userInfo[0] : '',
            (!empty($userInfo[1])) ? $userInfo[1] : ''
        );
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withReasonPhrase(string $reasonPhrase)
    {
        $this->reasonPhrase = $reasonPhrase;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatusCode(int $statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function toRedirectResponse(ResponseInterface $response = null): ResponseInterface
    {
        $factory = new RedirectResponseFactory();
        $response = $factory->createRedirectResponse($this, $response);
        if ($response->getStatusCode() == 302) {
            $cacheProvider = new \Slim\HttpCache\CacheProvider();
            $response = $cacheProvider->denyCache($response);
            $response = $response->withHeader('Pragma', 'no-cache');
            $response = $cacheProvider->withExpires($response, strtotime('Yesterday'));
        }
        return $response;
    }
}
