<?php

namespace Midweste\SlimRedirects;

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter as EmitterSapiEmitter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class RedirectController
{
    protected $excludes = [];
    protected $hooks = [
        'pre_redirect_filter' => RequestInterface::class,
        'post_redirect_action' => RequestInterface::class
    ];
    protected $hookStack = [];
    protected $redirects = [];
    protected $request = null;
    protected $response = null;

    public function __construct(Request $request, Response $response, array $redirects, array $options = [])
    {
        $this->setResponse($response);
        $this->setRequest($request);
        $this->setRedirects($redirects);
        $this->setOptions($options);

        // built in handlers
        $this->setTypeHandler('path', function (RedirectUri $uri, RedirectRule $rule, RequestInterface $request) {
            return $this->pathTypeHandler($uri, $rule, $request);
        });
        // $this->setTypeHandler('domain', function (string $destination) {
        //     return $destination;
        // });
    }

    public static function factory(Request $request, Response $response, array $redirects, array $options = []): self
    {
        // static $self;
        $called = get_called_class();
        // if ($self instanceof $called) {
        //     return $self;
        // }

        $self = new $called($request, $response, $redirects, $options);
        return $self;
    }

    public static function createRequestFromGlobals(): Request
    {
        $factory = new ServerRequestFactory();
        $serverRequest = $factory->createFromGlobals();
        return $serverRequest;
    }

    public static function createResponse(): Response
    {
        $factory = new ResponseFactory();
        $response = $factory->createResponse();
        return $response;
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

    public function getHookStack(): array
    {
        return $this->hookStack;
    }

    protected function setHookStack(array $hooks): self
    {
        $this->hookStack = $hooks;
        return $this;
    }

    public function getHooksAvailable(): array
    {
        return $this->hooks;
    }

    protected function getHook(string $hook): callable
    {
        if (!$this->isHookOnStack($hook)) {
            throw new \Exception(sprintf('Could not retrieve hook: %s', $hook));
        }
        return $this->getHookStack()[$hook];
    }

    public function setHook(string $hook, callable $callable)
    {
        if (!$this->isHookAvailable($hook)) {
            throw new \Exception(sprintf('Could not set hook: %s', $hook));
        }
        $this->hookStack[$hook] = $callable;
        return $this;
    }

    public function isHookAvailable(string $hook): bool
    {
        return array_key_exists($hook, $this->hooks);
    }

    public function isHookOnStack(string $hook): bool
    {
        $hooks = $this->getHookStack();
        return array_key_exists($hook, $hooks);
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
            $array[$r->getSourceUri()->getPathNormalized()] = $r;
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

    protected function pathTypeHandler(RedirectUri $uri, RedirectRule $rule, RequestInterface $request): RedirectUri
    {
        return $uri;
    }

    /**
     * Main redirect methods
     */
    public function getOptions(): RedirectOptions
    {
        return $this->options;
    }

    public function getOption(string $option)
    {
        return $this->getOptions()->getOption($option);
    }

    protected function setOptions(array $options = []): self
    {
        $this->options = RedirectOptions::factory($options);
        return $this;
    }

    public function setOption(string $option, $value): self
    {
        $this->getOptions()->setOption($option, $value);
        return $this;
    }

    public function setForceHttps(bool $force): self
    {
        return $this->setOption('forcehttps', $force);
    }

    public function getForceHttps(): bool
    {
        return $this->getOption('forcehttps');
    }

    protected function getRedirectsFiltered(bool $active = null, array $types = []): array
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

    protected function runHook(string $hook, $args = null)
    {
        if (!$this->isHookOnStack($hook)) {
            return $args;
        }
        $callable = $this->getHook($hook);
        return $callable($args);
    }

    protected function isExcluded(string $path): bool
    {
        // TODO pattern matching, begins with, wildcards
        return in_array($path, $this->getExcludes());
    }

    protected function mergeRuleIntoUri(RedirectRule $rule, RedirectUri $redirectUri): RedirectUri
    {
        $ruleUri = $rule->getDestinationUri();
        $redirectUri = $redirectUri
            ->withPath($ruleUri->getPath())
            ->withStatusCode($rule->getHttpStatus());

        // change host if needed
        $ruleHost = $ruleUri->getHost();
        if (!empty($ruleHost) && $ruleHost <> $redirectUri->getHost()) {
            $redirectUri = $redirectUri->withHost($ruleHost);
            if ($redirectUri->getPath() == '/') {
                $redirectUri = $redirectUri->withPath('');
            }
        }

        // default is combined querystring with rule overridding request
        if (!empty($redirectUri->getQuery()) || !empty($ruleUri->getQuery())) {
            parse_str($ruleUri->getQuery(), $destinationQs);
            parse_str($redirectUri->getQuery(), $sourceQs);
            $combinedQs = \array_replace_recursive($sourceQs, $destinationQs);
            ksort($combinedQs);
            $redirectUri = $redirectUri->withQuery(\http_build_query($combinedQs));
        }

        // rule fragment overrides request fragment
        if (!empty($ruleUri->getFragment())) {
            $redirectUri = $redirectUri->withFragment($ruleUri->getFragment());
        }

        // rule user info overrides request user info
        if (!empty($redirectUri->getUserInfo())) {
            $redirectUri = $redirectUri->withUserInfo($redirectUri->getUserInfo());
        }

        return $redirectUri;
    }

    public function redirectProcess(): ?Response
    {
        $redirects = $this->getRedirectsFiltered(true);
        $uri = new RedirectUri($this->getRequest()->getUri(), $this->getResponse()->getStatusCode());
        $path = $uri->getPathNormalized();
        $request = $this->getRequest();
        $noRedirectsOrExcluded = empty($redirects) || $this->isExcluded($path);
        $nullOrResponse = null;

        if ($this->getOption('enabled') === false) {
            return $nullOrResponse;
        }

        // strip standard port on standard schemes
        // something strange is happening on when used through load balancer
        if ($uri->getScheme() == 'http' || $uri->getScheme() == 'https') {
            $uri = $uri->withPort(null);
        }

        // force https
        if ($this->getOption('forcehttps') && $uri->getScheme() === 'http') {
            $uri = $uri
                ->withScheme('https')
                ->withPort(null)
                ->withStatusCode(302);
            if ($noRedirectsOrExcluded) {
                return $uri->toRedirectResponse();
            }
            $nullOrResponse = $uri->toRedirectResponse();
        }

        // bail on empty or excluded
        if ($noRedirectsOrExcluded) {
            // allow for http to https even if no redirects exists or path excluded
            return $nullOrResponse;
        }

        // direct match
        if (!empty($redirects[$path])) {
            $redirect = $redirects[$path];
            $uri = $this->mergeRuleIntoUri($redirect, $uri);

            $typeHandlerCallback = $this->getTypeHandler($redirect->getType());
            $uri = $typeHandlerCallback($uri, $redirect, $request);

            return $uri->toRedirectResponse();
        }

        $newPath = '';
        $requestPath = $request->getUri()->getPath();
        foreach ($redirects as $sourcePath => $redirect) {

            $sourcePath = urldecode($sourcePath);
            if (strpos($sourcePath, '*') === false) {
                continue;
            }

            // skip if request doesnt match wildcard up to the first *
            $static = substr($sourcePath, 0, strpos($sourcePath, '*'));
            if (strpos($requestPath, $static) === false) {
                continue;
            }

            // TODO refactor into matching method
            $destination = $redirect->getDestination();
            $sourcePattern = rtrim(str_replace('*', '(.*)', $sourcePath), '/');
            $sourcePatternRegex = '/^' . str_replace('/', '\/', $sourcePattern) . '/';
            $regexPath = preg_replace($sourcePatternRegex, $this->parseDestination($destination), $path);

            // non matching rule
            if ($regexPath === $path) {
                continue;
            }

            $regexRule = clone $redirect;
            $regexRule = $regexRule->setDestination($regexPath);
            $uri = $this->mergeRuleIntoUri($regexRule, $uri);

            $typeHandlerCallback = $this->getTypeHandler($redirect->getType());
            $uri = $typeHandlerCallback($uri, $regexRule, $request);

            // the second condition here prevents redirect loops as a result of wildcards.
            $newPath = $uri->getPath();
            if ($newPath !== '' && trim($newPath, '/') !== trim($path, '/')) {
                return $uri->toRedirectResponse();
            }
        }
        return $nullOrResponse;
    }

    public function emitResponse(Response $response): bool
    {
        $response = $this->runHook('pre_redirect_filter', $response);
        $emitter = new EmitterSapiEmitter();
        $result = $emitter->emit($response);
        $this->runHook('post_redirect_action', $response);
        return $result;
    }

    public function emitResponseAndExit(Response $response): void
    {
        $result = $this->emitResponse($response);
        if ($result === false) {
            throw new \Exception('Could not send response.');
        }
        exit();
    }
}
