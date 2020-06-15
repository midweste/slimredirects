<?php

namespace MidwestE\SlimRedirects;

use Symfony\Component\HttpFoundation\Request;


class Controller
{

    private $start;
    protected $excludes = [];
    protected $hooks = [];
    protected $table = 'wp_lecm_rewrite';
    protected $forceHttps = false;
    protected $request = null;

    public function __construct()
    {
        $this->request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        //$this->request = $this->createRequest();

        //$this->start = microtime(true);
        $this->setTypeHandler('path', function (string $destination) {
            return $destination;
        });
        $this->setTypeHandler('domain', function (string $destination) {
            return $destination;
        });
    }

    private function createRequest(): object
    {
        $request = [
            'scheme' => '',
            'host' => '',
            'port' => 80,
            'user' => '',
            'pass' => '',
            'path' => '',
            'query' => '',
            'fragment' => '',
        ];

        $scheme = ($this->isHttps()) ? 'https' : 'http';
        $absUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        //$absUrl = 'http://username:password@tcbwoo.lndo.site:80/path?arg=value#anchor';
        $parsed = parse_url($absUrl);
        $request = array_replace($request, $parsed);
        return (object) $request;
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

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function getForceHttps(): bool
    {
        return $this->forceHttps;
    }

    public function setForceHttps(bool $https): self
    {
        $this->forceHttps = $https;
        return $this;
    }

    private function sqlMap(array $items = [], string $enclosed = '`'): string
    {
        if (empty($items)) {
            return '';
        }
        $mapped = $enclosed . implode($enclosed . ',' . $enclosed, array_map('esc_sql', $items)) . $enclosed;
        return $mapped;
    }

    protected function getRedirectsAll(?int $active = null, array $fields = [], array $types = []): array
    {
        global $wpdb;
        $fieldSql = (empty($fields)) ? '*' : $this->sqlMap($fields);
        $sql = sprintf('SELECT %s FROM `%s` WHERE 1 = 1', $fieldSql, $this->getTable());
        $sql .= (is_int($active)) ? sprintf(' AND `active` = %d', $active) : '';
        $sql .= (!empty($types)) ? ' AND `type` IN (' . $this->sqlMap($types, "'") . ')' : '';
        $sql .= ' ORDER BY type, source';
        $redirects = $wpdb->get_results($sql, ARRAY_A);
        $keyed = [];
        foreach ($redirects as $redirect) {
            $keyed[$redirect['source']] = SlimRedirect::factory($redirect);
        }
        return $keyed;
    }

    protected function getRedirectsSource(): array
    {
        return $this->getRedirectsAll(1, ['source'], $this->getTypeHandlerNames());
    }

    protected function getRedirects(): array
    {
        return $this->getRedirectsAll(1, [], $this->getTypeHandlerNames());
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

    public function setExcludes(array $excludes): self
    {
        $this->excludes = $excludes;
        return $this;
    }

    public function getExcludes(): array
    {
        return $this->excludes;
    }

    public function setTypeHandler(string $type, callable $callable): self
    {
        $this->handlers[$type] = $callable;
        return $this;
    }

    public function getTypeHandler(string $type): callable
    {
        if (!isset($this->handlers[$type]) || !is_callable(($this->handlers[$type]))) {
            throw new \Exception(sprintf('No redirect handler for type: %s', $type));
        }
        return $this->handlers[$type];
    }

    public function getTypeHandlerNames(): array
    {
        return array_keys($this->getTypeHandlers());
    }

    public function getTypeHandlers(): array
    {
        return $this->handlers;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function redirect(): void
    {
        $this->start = microtime(true);

        $https = $this->getForceHttps();
        if ($https === true) {
            $this->redirectToHttps();
        }

        $requestPath = urldecode(rtrim($this->getRequest()->path, '/'));
        if (in_array($requestPath, $this->getExcludes())) {
            return;
        }

        $redirects = $this->getRedirects();
        if (empty($redirects)) {
            return;
        }

        // direct match
        // TODO get redirects only without wildcard
        if (!empty($redirects[$requestPath])) {
            $redirect = $redirects[$requestPath];
            $finalPath = $this->getTypeHandler($redirect->getType())($redirect->getDestination()) . $this->getQuerystringWithFragment();
            $this->redirectToLocation($finalPath, $redirect->getHttpStatus());
        }

        $finalPath = '';
        foreach ($redirects as $redirect) {

            $rSource = $redirect->getSource();
            if (strpos($rSource, '*') === false) {
                continue;
            }

            $rDestination = $redirect->getDestination();
            // if (urldecode($requestPath) == rtrim($rSource, '/')) { // simple comparison redirect
            //     $finalPath = $this->getTypeHandler($redirect->getType())($rDestination) . $queryString;
            // } else
            // if (strpos($rSource, '*') !== false) { // wildcard redirect
            $rSourcePattern = rtrim(str_replace('*', '(.*)', $rSource), '/');
            $pattern = '/^' . str_replace('/', '\/', $rSourcePattern) . '/';
            $output = preg_replace($pattern, $this->parseDestination($rDestination), $requestPath);
            if ($output === $requestPath) {
                continue;
            }
            $finalPath = $this->getTypeHandler($redirect->getType())($output) . $this->getQuerystringWithFragment();
            // }

            // redirect. the second condition here prevents redirect loops as a result of wildcards.
            if ($finalPath !== '' && trim($finalPath, '/') !== trim($requestPath, '/')) {
                $this->redirectToLocation($finalPath, $redirect->getHttpStatus());
            }
        }
        // echo (microtime(true) - $this->start);
        // exit();
    }

    public function getHooks(): array
    {
        return (is_array($this->hooks)) ? $this->hooks : [];
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

    private function getHook(string $hook): ?callable
    {
        return (isset($this->hooks[$hook]) && is_callable($this->hooks[$hook])) ? $this->hooks[$hook] : null;
    }

    private function runHook(string $hook, array &$args = [])
    {
        $callable = $this->getHook($hook);
        return (!is_callable($callable)) ? call_user_func_array($callable, $args) : null;
    }

    public function redirectToLocation(string $location, int $status = 302)
    {
        // check if destination needs the domain prepended
        if (strpos($location, '/') === 0) {
            $location = $this->getHostWithProtocol() . $location;
        }
        // echo (microtime(true) - $this->start);
        // exit();

        $parameters = [&$location, &$status];
        $this->runHook('pre_redirect', $parameters);
        header(sprintf('Location: %s', $location), true, $status);
        $this->runHook('post_redirect', $parameters);
        exit();
    }

    public function redirectToHttps(): void
    {
        if ($this->isHttps()) {
            return;
        }
        $secureRedirect = sprintf('https://%s%s', $this->getHttpHost(), $this->getRequestUri());
        $this->redirectToLocation($secureRedirect, 301);
    }

    public function isHttps(): bool
    {
        $isSecure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $isSecure = true;
        } elseif (
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
            ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
        ) {
            $isSecure = true;
        }
        return $isSecure;
    }

    public function getScheme(): string
    {
        return $this->getRequest()->scheme;
    }

    public function getHttpHost(): string
    {
        return $this->getRequest()->host;
    }

    public function getQuerystringWithFragment(): string
    {
        $request = $this->getRequest();
        $query = (!empty($request->query)) ? '?' . $request->query : '';
        $fragment = (!empty($request->fragment)) ? '#' . $request->fragment : '';
        return $query . $fragment;
    }

    public function getRequestUri(): string
    {
        $url = $this->getRequest();
        $query = (!empty($url->query)) ? '?' . $url->query : '';
        $fragment = (!empty($url->fragment)) ? '#' . $url->fragment : '';
        return $url->path . $query . $fragment;
    }

    public function getHostWithProtocol(): string
    {
        return $this->getRequest()->scheme . '://' . $this->getRequest()->host;
    }

    public function getRequestAbsolute(): string
    {
        return $this->getHostWithProtocol() . $this->getRequestUri();
    }
}
