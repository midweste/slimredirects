<?php

use Midweste\SlimRedirects\Controller;
use Midweste\SlimRedirects\RedirectRule;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Http\Uri;

class SlimRedirectsTest extends TestCase
{

    private $scheme = 'http';
    private $port = '80';
    private $host = 'localhost';
    private $path = '/';
    private $query = 'one=value&another=value';

    private function loadRedirects()
    {
        $redirects = json_decode(file_get_contents(__DIR__ . '/slimredirects.json'))->redirects;
        return $redirects;
    }

    private function loadOptions()
    {
        return json_decode(file_get_contents(__DIR__ . '/slimoptions.json'), true);
    }

    private function mockRequest(UriInterface $uri, array $params = []): ServerRequestInterface
    {
        $factory = new ServerRequestFactory();
        //$serverRequest = $factory->createFromGlobals();
        $serverRequest = $factory->createServerRequest('GET', $uri, $params);
        return $serverRequest;
    }

    private function mockResponse(): ResponseInterface
    {
        $factory = new ResponseFactory();
        $response = $factory->createResponse();
        return $response;
    }

    private function slimRedirect($uri = null, ?array $redirects = [], ?array $options = []): object
    {
        $testUri = (!empty($uri)) ? $uri : $this->scheme . '://' . $this->host . ':' . $this->port . $this->path . '?' . $this->query;
        $uri = (new UriFactory())->createUri($testUri);

        $server['SERVER_PROTOCOL'] = $uri->getScheme();
        $server['HTTP_HOST'] = $uri->getHost();
        $server['REQUEST_URI'] = $uri->getPath();
        $server['QUERY_STRING'] = $uri->getQuery();
        $server['REMOTE_ADDR'] = '127.0.0.1';
        $server['REQUEST_METHOD'] = 'GET';
        $server['HTTP_USER_AGENT'] = 'phpunit';
        foreach ($server as $key => $value) {
            $_SERVER[$key] = $value;
        }

        $options = (!empty($options)) ? $options : $this->loadOptions();
        $request = $this->mockRequest($uri, $server);
        $controller = new Controller($request, $this->mockResponse(), $redirects, $options);
        $response = $controller->redirectProcess();
        $responseStatus = ($response) ? $response->getStatusCode() : null;
        $location = (!is_null($response) && $response->hasHeader('location')) ? (new UriFactory())->createUri($response->getHeaderLine('location')) : null;
        $locationUri = (!is_null($location)) ? (string) $location : null;

        $result = new \stdClass();
        $result->request = $request;
        $result->controller = $controller;
        $result->responseStatus = $responseStatus;
        $result->response = $response;
        $result->locationUri = $location;
        $result->location = $locationUri;
        $result->redirects = $redirects;
        $result->options = $options;

        return $result;
    }


    /**
     * Tests
     */

    public function testCreation()
    {
        $rule = [
            "id" => "1",
            "source" => "/",
            "type" => "path",
            "destination" => "/root",
            "httpStatus" => 302,
            "active" => 1
        ];
        $options = $this->loadOptions();
        $result = $this->slimRedirect('http://localhost/', [$rule], $options);
        $this->assertInstanceOf(Controller::class, $result->controller);
    }

    public function testForceHttps()
    {
        $rule = [
            "id" => "1",
            "source" => "/",
            "type" => "path",
            "destination" => "/root",
            "httpStatus" => 302,
            "active" => 1
        ];
        $options = $this->loadOptions();
        $options['forceHttps'] = true;
        $result = $this->slimRedirect('http://localhost/', [$rule], $options);
        $this->assertEquals($result->locationUri->getScheme(), 'https');
        $this->assertEquals($result->responseStatus, $rule['httpStatus']);
    }

    public function testRedirectNonMatch()
    {
        $rule = [
            "id" => "1",
            "source" => "/",
            "type" => "path",
            "destination" => "/root",
            "httpStatus" => 302,
            "active" => 1
        ];
        $result = $this->slimRedirect('https://localhost/nomatch?query=string', [$rule]);
        $this->assertEquals($result->responseStatus, null);
        $this->assertEquals($result->location, null);
    }

    public function testRedirectRootRedirect()
    {
        $rule = [
            "id" => "1",
            "source" => "/",
            "type" => "path",
            "destination" => "/root",
            "httpStatus" => 302,
            "active" => 1
        ];
        $result = $this->slimRedirect('https://localhost/?query=string', [$rule]);
        $this->assertEquals($result->responseStatus, $rule['httpStatus']);
        $this->assertEquals($result->location, 'https://localhost/root?query=string');
    }

    public function testRedirectSimpleWildcardPath()
    {
        $rule = [
            "id" => "1",
            "source" => "/wild/*/card",
            "type" => "path",
            "destination" => "/wildcard/*",
            "httpStatus" => 302,
            "active" => 1
        ];
        $result = $this->slimRedirect('https://localhost/wild/test/card?query=string', [$rule]);
        $this->assertEquals($result->locationUri->getScheme(), 'https');
        $this->assertEquals($result->responseStatus, $rule['httpStatus']);
    }
}
