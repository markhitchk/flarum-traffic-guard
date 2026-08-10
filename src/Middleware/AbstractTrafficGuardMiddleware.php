<?php

namespace MarkHitchk\TrafficGuard\Middleware;

use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use MarkHitchk\TrafficGuard\Service\LogService;
use MarkHitchk\TrafficGuard\Service\TemplateRenderer;
use MarkHitchk\TrafficGuard\Service\TrafficGuardService;
use MarkHitchk\TrafficGuard\Support\ClientIpResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

abstract class AbstractTrafficGuardMiddleware implements MiddlewareInterface
{
    private $settings;
    private $resolver;
    private $guard;
    private $renderer;
    private $logs;

    public function __construct(
        SettingsRepositoryInterface $settings,
        ClientIpResolver $resolver,
        TrafficGuardService $guard,
        TemplateRenderer $renderer,
        LogService $logs
    ) {
        $this->settings = $settings;
        $this->resolver = $resolver;
        $this->guard = $guard;
        $this->renderer = $renderer;
        $this->logs = $logs;
    }

    abstract protected function scope();

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->enabled('enabled') || ! $this->enabled('scope_'.$this->scope())) {
            return $handler->handle($request);
        }

        $ip = $this->resolver->resolve($request);
        if (! $ip) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $userAgent = $request->getHeaderLine('User-Agent');

        try {
            $decision = $this->guard->inspect($ip, $path, $userAgent, false);
        } catch (Throwable $e) {
            // Internal guard errors should never take down the forum.
            return $handler->handle($request);
        }

        try {
            $this->logs->record($ip, $path, $userAgent, $decision);
        } catch (Throwable $e) {
            // Logging is best-effort and must not affect availability.
        }

        if (! $decision->blocked) {
            return $handler->handle($request);
        }

        $status = $this->renderer->statusCode($decision);
        $headers = [
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->scope() === 'api' && $this->get('api_response_mode', 'json') === 'json') {
            return new JsonResponse([
                'errors' => [[
                    'status' => (string) $status,
                    'code' => 'traffic_guard_blocked',
                    'title' => 'Access restricted',
                    'detail' => $decision->reason,
                    'meta' => [
                        'category' => $decision->category,
                        'ruleId' => $decision->ruleId,
                    ],
                ]],
            ], $status, $headers);
        }

        return new HtmlResponse(
            $this->renderer->render($decision, $ip, $request),
            $status,
            $headers
        );
    }

    private function enabled($name)
    {
        return $this->get($name, '0') === '1';
    }

    private function get($name, $default = null)
    {
        return $this->settings->get('markhitchk-traffic-guard.'.$name, $default);
    }
}
