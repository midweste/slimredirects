<?php


namespace Midweste\SlimRedirects;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Slim\Http\Uri;
use Slim\Psr7\Factory\ResponseFactory;

class RedirectUri extends Uri
{
    protected $uri;
    protected $statusCode;

    public function __construct(UriInterface $uri, ?int $statusCode = 302)
    {
        $this->setUri($uri);
        $this->withStatusCode($statusCode);
        parent::__construct($uri);
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    protected function setUri(UriInterface $uri)
    {
        $this->uri = $uri;
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

    public function createResponse(RedirectUri $uri): ResponseInterface
    {
        $factory = new ResponseFactory;
        $response = $factory->createResponse($uri->getStatusCode());
        $response = $response->withHeader('Location', (string) $uri->getUri());
        return $response;
    }
}
