<?php


namespace Midweste\SlimRedirects;

use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Slim\Http\Uri;

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
        $response = new RedirectResponse($uri->getUri(), $uri->getStatusCode());
        return $response;
    }
}
