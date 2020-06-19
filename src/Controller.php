<?php

namespace Midweste\SlimRedirects;

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter as EmitterSapiEmitter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Controller
{
    protected $excludes = [];
    protected $hooks = [
        'pre_redirect_filter',
        'post_redirect_action'
    ];
    protected $hooksRegistered = [];
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
        // $this->setTypeHandler('domain', function (string $destination) {
        //     return $destination;
        // });
    }

    public static function factory(Request $request, Response $response, array $redirects, array $options = [])
    {
        static $self;
        $called = get_called_class();
        if ($self instanceof $called) {
            return $self;
        }

        $self = new $called($request, $response, $redirects, $options);
        return $self;
    }

    public function getExcludes(): array
    {
        return $this->excludes;
    }

    public function setExcludes(array $excludes): self
    {
        $this->excludes = $excludes;
        return $this;
    }

    public function getHooks(): array
    {
        return $this->hooksRegistered;
    }

    public function getHookList(): array
    {
        return $this->hooks;
    }

    private function getHook(string $hook): callable
    {
        if (!$this->hasHook($hook)) {
            throw new \Exception(sprintf('Could not retrieve hook: %s', $hook));
        }
        return $this->getHooks()[$hook];
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
        $this->hooksRegistered[$hook] = $callable;
        return $this;
    }

    public function hasHook(string $hook): bool
    {
        return array_key_exists($hook, $this->getHooks());
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
    public function setOption(string $option, $value): self
    {
        $options = $this->getOptions();
        if (!isset($options[$option])) {
            throw new \Exception('Option not available');
        }
        $options[$option] = $value;
        return $this->setOptions($options);
    }

    public function setForceHttps(bool $force): self
    {
        return $this->setOption('forcehttps', true);
    }

    public function getForceHttps(): bool
    {
        return $this->getOption('forcehttps', true);
    }


    public function emitResponse(Response $response): bool
    {
        $response = $this->runHook('pre_redirect_filter', $response);
        $emitter = new EmitterSapiEmitter();
        $result = $emitter->emit($response);
        $this->runHook('post_redirect_action', $response);
        return $result;
    }

    protected function getRedirectsFiltered(?bool $active = null, ?array $types = []): array
    {
        $redirects = [];
        $handlers = (!empty($types)) ? $types : $this->getTypeHandlerNames();
        foreach ($this->getRedirects() as $source => $redirect) {
            if (is_bool($active) && $redirect->getActive() <> $active) {
                continue;
            }
            if (!empty($handlers) && !in_array($redirect->getType(), $handlers)) {
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
            // TODO - force https should not require two redirects
        }

        if (empty($redirects) || in_array($requestPath, $this->getExcludes())) {
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
            $rSourcePatternRegex = '/^' . str_replace('/', '\/', $rSourcePattern) . '/';
            $output = preg_replace($rSourcePatternRegex, $this->parseDestination($rDestination), $requestPath);
            // non matching rule
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
        if (!$this->hasHook($hook)) {
            return $args;
        }
        $callable = $this->getHook($hook);
        return $callable($args);
    }
}
