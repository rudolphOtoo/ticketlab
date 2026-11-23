<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Media\CloudinaryUploads;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the policy from config/csp.php when CSP_MODE is "report-only" or "enforce" (off by default).
 *
 * The browser-upload origin is taken from the same setting the uploader posts to (CloudinaryUploads::endpoint()),
 * so the policy can never allow one host while uploads go to another.
 */
final class ContentSecurityPolicy
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $mode = config('csp.mode');

        if (!in_array($mode, ['report-only', 'enforce'], true) || $response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        $header = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
        $response->headers->set($header, self::policy());

        return $response;
    }

    /**
     * Scheme, host and port only: a CSP source with a path matches that exact path, not the paths below it.
     */
    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        $port = ($parts['port'] ?? null) !== null ? ':' . $parts['port'] : '';

        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $port;
    }

    /**
     * The policy as a header value.
     */
    public static function policy(): string
    {
        /** @var array<string, list<string>> $directives */
        $directives = (array) config('csp.directives', []);
        $directives['connect-src'][] = self::origin(CloudinaryUploads::origin());

        // The Vite dev server (local development only) serves scripts and styles and keeps a websocket open.
        if (Vite::isRunningHot()) {
            $hot = rtrim((string) file_get_contents(Vite::hotFile()));
            $websocket = (string) preg_replace('#^http#', 'ws', $hot);

            // A CSP source cannot name an IPv6 address (Vite often listens on http://[::1]:5173), so allow the
            // scheme instead. This only happens while the dev server runs, never with built assets.
            if (str_contains($hot, '[')) {
                $hot = (string) parse_url($hot, PHP_URL_SCHEME) . ':';
                $websocket = 'ws:';
            }

            foreach (['script-src', 'style-src', 'connect-src', 'img-src', 'font-src'] as $directive) {
                $directives[$directive][] = $hot;
            }

            $directives['connect-src'][] = $websocket;
        }

        $parts = [];

        foreach ($directives as $directive => $sources) {
            $parts[] = $directive . ' ' . implode(' ', array_values(array_unique($sources)));
        }

        return implode('; ', $parts);
    }
}
