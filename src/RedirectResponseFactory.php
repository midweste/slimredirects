<?php


namespace Midweste\SlimRedirects;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

class RedirectResponseFactory extends ResponseFactory
{

    public function createRedirectResponse(RedirectUri $uri, ResponseInterface $response = null): ResponseInterface
    {
        if (is_null($response)) {
            $response = $this->createResponse($uri->getStatusCode(), $uri->getReasonPhrase());
        }

        $headers = $response->getHeaders();
        foreach ($headers as $header) {
            $response = $response->withoutHeader($header);
        }
        $response = $response
            ->withStatus($uri->getStatusCode(), $uri->getReasonPhrase())
            ->withHeader('Location', (string) $uri);
        return $response;
    }
}
