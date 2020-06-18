<?php

namespace Midweste\SlimRedirects;

use Psr\Http\Message\UriInterface as Uri;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\HttpHandlerRunner\Emitter\SapiEmitter;

/**
 * https://github.com/php-fig/http-message/blob/master/docs/PSR7-Interfaces.md
 */
class Controller
{
    protected $excludes = [];
    protected $hooks = [];
    protected $options = [
        'enabled' => true,
        'forcehttps' => false,
        'wildcard' => true,
    ];
    protected $redirects = [];
    protected $request = null;
    protected $response = null;

    public function __construct(Request $request, Response $response, array $redirects, array $options = [])
    {
        //$this->request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        //$this->request = $this->createRequest();
        $this->setResponse($response);
        $this->setRequest($request);
        $this->setRedirects($redirects);
        $this->setOptions($options);

        // built in handlers
        $this->setTypeHandler('path', function (string $destination) {
            return $destination;
        });
        $this->setTypeHandler('domain', function (string $destination) {
            return $destination;
        });
    }

    public static function instance()
    {
        static $self;
        $called = get_called_class();
        if ($self instanceof $called) {
            return $self;
        }

        $self = new $called;
        return $self;
    }

    public function getExcludes(): array
    {
        return $this->excludes;
    }

    protected function setExcludes(array $excludes): self
    {
        $this->excludes = $excludes;
        return $this;
    }

    public function getHooks(): array
    {
        return $this->hooks;
    }

    private function getHook(string $hook): callable
    {
        if (!$this->hasHook($hook)) {
            throw new \Exception(sprintf('Could not retrieve hook: %s', $hook));
        }
        return $this->hooks[$hook];
    }

    public function setHooks(array $hooks): self
    {
        foreach ($hooks as $hook => $callable) {
            $this->setHook($hook, $callable);
        }
        return $this;
    }

    public function setHook(string $hook, callable $callable)
    {
        $this->hooks[$hook] = $callable;
        return $this;
    }

    public function hasHook(string $hook): bool
    {
        return isset($this->hooks[$hook]);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $option)
    {
        if (!isset($this->getOptions()[$option])) {
            throw new \Exception(sprintf('Could not retrive option: %s', $option));
        }
        return $this->options[$option];
    }

    protected function setOptions(array $options = []): self
    {
        $this->options = \array_replace_recursive($this->options, $options);
        return $this;
    }

    public function getRedirects(): array
    {
        return $this->redirects;
    }

    protected function setRedirects(array $redirects): self
    {
        $array = [];
        foreach ($redirects as $redirect) {
            $r = ($redirect instanceof RedirectRule) ? $redirect : RedirectRule::factory($redirect);
            $array[$r->getSource()] = $r;
        }
        $this->redirects = $array;
        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    protected function setRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    protected function setResponse(Response $response): self
    {
        $this->response = $response;
        return $this;
    }

    public function getTypeHandlers(): array
    {
        return $this->handlers;
    }

    protected function getTypeHandlerNames(): array
    {
        return array_keys($this->getTypeHandlers());
    }

    public function hasTypeHandler(string $type): bool
    {
        return isset($this->handlers[$type]) && is_callable($this->handlers[$type]);
    }

    public function getTypeHandler(string $type): callable
    {
        if (!$this->hasTypeHandler($type)) {
            throw new \Exception(sprintf('No redirect handler for type: %s', $type));
        }
        return $this->handlers[$type];
    }

    public function setTypeHandler(string $type, callable $callable): self
    {
        $this->handlers[$type] = $callable;
        return $this;
    }

    /**
     * Main redirect methods
     */


    public function isRequestHttps(Uri $uri): bool
    {
        return ($uri->getScheme() == 'https') ? true : false;
    }

    public function getQuerystringWithFragment(): string
    {
        $uri = $this->getRequest()->getUri();
        $query = $uri->getQuery();
        $fragment = $uri->getFragment();

        $query = (!empty($query)) ? '?' . $query : '';
        $fragment = (!empty($fragment)) ? '#' . $fragment : '';
        return $query . $fragment;
    }

    protected function getRedirectsFiltered(?bool $active = null, ?array $types = []): array
    {
        $redirects = [];
        $handlers = (!empty($types)) ? $types : $this->getTypeHandlerNames();
        foreach ($this->getRedirects() as $source => $redirect) {
            if (is_bool($active) && $redirect->getActive() <> $active) {
                continue;
            }
            if (!empty($types) && !in_array($redirect->getType(), $handlers)) {
                continue;
            }
            $redirects[$source] = $redirect;
        }
        return $redirects;
    }

    protected function parseDestination(string $destination): string
    {
        if (strpos($destination, '*') === false) {
            return $destination;
        }

        $wildcards = 1;
        $replaced = '';
        for ($i = 0; $i < strlen($destination); $i++) {
            if ($destination[$i] === '*') {
                $replaced .= '$' . $wildcards;
                $wildcards++;
            } else {
                $replaced .= $destination[$i];
            }
        }
        return $replaced;
    }

    public function redirectProcess(): ?Response
    {
        $redirects = $this->getRedirectsFiltered(true);
        $redirectUri = new RedirectUri($this->getRequest()->getUri(), $this->getResponse()->getStatusCode());
        $requestPath = urldecode($redirectUri->getUri()->getPath());

        if ($this->getOption('enabled') === false) {
            return null;
        }

        if ($this->getOption('forcehttps') && $redirectUri->getScheme() !== 'https') {
            $redirectUri = $redirectUri->withScheme('https')->withStatusCode(302);
            return $redirectUri->createResponse($redirectUri);
        }

        if (
            empty($redirects)
            || in_array($requestPath, $this->getExcludes())
        ) {
            return null;
        }

        // direct match
        // TODO get redirects only without wildcard
        if (!empty($redirects[$requestPath])) {
            $redirect = $redirects[$requestPath];
            $typeHandlerCallback = $this->getTypeHandler($redirect->getType());
            $redirectUri = $redirectUri
                ->withPath($typeHandlerCallback($redirect->getDestination()))
                ->withStatusCode($redirect->getHttpStatus());
            return $redirectUri->createResponse($redirectUri);
        }

        $finalPath = '';
        foreach ($redirects as $redirect) {

            $rSource = $redirect->getSource();
            if (strpos($rSource, '*') === false) {
                continue;
            }

            $rDestination = $redirect->getDestination();
            $rSourcePattern = rtrim(str_replace('*', '(.*)', $rSource), '/');
            $pattern = '/^' . str_replace('/', '\/', $rSourcePattern) . '/';
            $output = preg_replace($pattern, $this->parseDestination($rDestination), $requestPath);
            if ($output === $requestPath) {
                continue;
            }
            $typeHandlerCallback = $this->getTypeHandler($redirect->getType());
            $finalPath = $typeHandlerCallback($output);

            // redirect. the second condition here prevents redirect loops as a result of wildcards.
            if ($finalPath !== '' && trim($finalPath, '/') !== trim($requestPath, '/')) {
                $redirectUri = $redirectUri
                    ->withPath($finalPath)
                    ->withStatusCode($redirect->getHttpStatus());
                return $redirectUri->createResponse($redirectUri);
            }
        }
        return null;
    }

    private function runHook(string $hook, $args = null)
    {
        $callable = $this->getHook($hook);
        return (!is_callable($callable)) ? call_user_func_array($callable, $args) : null;
    }

    public function emitResponse(Response $response)
    {
        $response = $this->runHook('pre_redirect', $response);
        $emitter = new SapiEmitter();
        $emitter->emit($response);
        $this->runHook('post_redirect', $response);
        exit();
    }
}
