<?php
declare(strict_types=1);

namespace Core;

use Response;

require_once __DIR__ . '/../utils/Response.php';

/**
 * Lightweight, Dependency-Free OOP Router
 * 
 * Supports standard REST methods, dynamic route parameters (e.g. /products/{id}),
 * and route-level middleware binding.
 */
class Router {
    /**
     * @var array<int, array{
     *   method: string,
     *   path: string,
     *   pattern: string,
     *   paramNames: array<string>,
     *   handler: callable|array,
     *   middlewares: array<callable>
     * }>
     */
    private array $routes = [];

    /**
     * Register a GET route.
     *
     * @param string $path
     * @param callable|array $handler
     * @param array<callable> $middlewares
     */
    public function get(string $path, callable|array $handler, array $middlewares = []): self {
        return $this->add('GET', $path, $handler, $middlewares);
    }

    /**
     * Register a POST route.
     *
     * @param string $path
     * @param callable|array $handler
     * @param array<callable> $middlewares
     */
    public function post(string $path, callable|array $handler, array $middlewares = []): self {
        return $this->add('POST', $path, $handler, $middlewares);
    }

    /**
     * Register a PUT route.
     *
     * @param string $path
     * @param callable|array $handler
     * @param array<callable> $middlewares
     */
    public function put(string $path, callable|array $handler, array $middlewares = []): self {
        return $this->add('PUT', $path, $handler, $middlewares);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $path
     * @param callable|array $handler
     * @param array<callable> $middlewares
     */
    public function delete(string $path, callable|array $handler, array $middlewares = []): self {
        return $this->add('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Add a route definition.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $path Route path pattern (e.g. /products/{id})
     * @param callable|array $handler Target callable or [ControllerClass, 'action']
     * @param array<callable> $middlewares Array of middleware callables
     */
    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): self {
        $normalizedPath = $this->normalizePath($path);
        
        // Convert {paramName} placeholders into regex named capture groups
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $normalizedPath);

        $regex = '#^' . $pattern . '$#i';

        $this->routes[] = [
            'method'      => strtoupper($method),
            'path'        => $normalizedPath,
            'pattern'     => $regex,
            'paramNames'  => $paramNames,
            'handler'     => $handler,
            'middlewares' => $middlewares
        ];

        return $this;
    }

    /**
     * Dispatch the current HTTP request to matching route.
     *
     * @param string|null $method Optional override for testing
     * @param string|null $uri Optional override for testing
     */
    public function dispatch(?string $method = null, ?string $uri = null): void {
        $requestMethod = strtoupper($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        
        // Handle CORS Preflight
        if ($requestMethod === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        $requestUri = $this->resolveRequestUri($uri);
        $normalizedUri = $this->normalizePath($requestUri);

        $matchedPathFound = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $normalizedUri, $matches)) {
                $matchedPathFound = true;

                if ($route['method'] !== $requestMethod) {
                    continue; // Path matches, but method differs
                }

                // Extract named parameter values
                $params = [];
                foreach ($route['paramNames'] as $paramName) {
                    if (isset($matches[$paramName])) {
                        $params[$paramName] = urldecode($matches[$paramName]);
                    }
                }

                // Execute Route Middlewares
                $userContext = null;
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        $result = $middleware($params, $userContext);
                        if (is_array($result)) {
                            $userContext = $result; // Update context if returned
                        }
                    }
                }

                // Execute Controller / Handler
                $this->invokeHandler($route['handler'], $params, $userContext);
                return;
            }
        }

        // Return appropriate HTTP error if no match
        if ($matchedPathFound) {
            Response::json([
                'success' => false,
                'message' => "Method {$requestMethod} not allowed for {$normalizedUri}"
            ], 405);
        } else {
            Response::json([
                'success' => false,
                'message' => "Route endpoint not found: {$normalizedUri}"
            ], 404);
        }
    }

    /**
     * Resolves incoming URI across various Apache configurations (Rewrite or query string).
     */
    private function resolveRequestUri(?string $uriOverride): string {
        if ($uriOverride !== null) {
            return $uriOverride;
        }

        if (isset($_GET['request']) && !empty($_GET['request'])) {
            return (string)$_GET['request'];
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip subdirectories if deployed inside /VINJACK-PHP/ or /VINJACK-PHP/api/
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return $path;
    }

    /**
     * Normalizes paths with consistent leading slashes and trimmed trailing slashes.
     */
    private function normalizePath(string $path): string {
        // Strip out optional /api prefix if provided in route definition or request
        $trimmed = trim($path, '/');
        if (str_starts_with($trimmed, 'api/')) {
            $trimmed = substr($trimmed, 4);
        } elseif ($trimmed === 'api') {
            $trimmed = '';
        }

        return '/' . $trimmed;
    }

    /**
     * Invokes the registered handler.
     *
     * @param callable|array $handler
     * @param array<string, string> $params
     * @param array<string, mixed>|null $userContext
     */
    private function invokeHandler(callable|array $handler, array $params, ?array $userContext): void {
        if (is_callable($handler)) {
            $handler($params, $userContext);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$classOrInstance, $methodName] = $handler;

            $instance = is_object($classOrInstance) 
                ? $classOrInstance 
                : new $classOrInstance();

            if (!method_exists($instance, $methodName)) {
                throw new \BadMethodCallException("Method {$methodName} does not exist on " . get_class($instance));
            }

            $instance->$methodName($params, $userContext);
            return;
        }

        throw new \InvalidArgumentException("Invalid route handler format.");
    }
}
