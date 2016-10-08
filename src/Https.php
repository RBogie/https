<?php

namespace Middlewares;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Interop\Http\Middleware\MiddlewareInterface;
use Interop\Http\Middleware\DelegateInterface;

class Https implements MiddlewareInterface
{
    const HEADER = 'Strict-Transport-Security';

    /**
     * @param int One year by default
     */
    private $maxAge = 31536000;

    /**
     * @param bool Whether include subdomains
     */
    private $includeSubdomains = false;

    /**
     * @param bool Whether check the headers "HTTP_X_FORWARDED_PROTO: https" or "HTTP_X_FORWARDED_PORT: 443"
     */
    private $checkHttpsForward = false;

    /**
     * Configure the max-age HSTS in seconds.
     *
     * @param int $maxAge
     *
     * @return self
     */
    public function maxAge($maxAge)
    {
        $this->maxAge = $maxAge;

        return $this;
    }

    /**
     * Configure the includeSubDomains HSTS directive.
     *
     * @param bool $includeSubdomains
     *
     * @return self
     */
    public function includeSubdomains($includeSubdomains = true)
    {
        $this->includeSubdomains = $includeSubdomains;

        return $this;
    }

    /**
     * Configure whether check the following headers before redirect:
     * HTTP_X_FORWARDED_PROTO: https
     * HTTP_X_FORWARDED_PORT: 443.
     *
     * @param bool $checkHttpsForward
     *
     * @return self
     */
    public function checkHttpsForward($checkHttpsForward = true)
    {
        $this->checkHttpsForward = $checkHttpsForward;

        return $this;
    }

    /**
     * Process a request and return a response.
     *
     * @param RequestInterface  $request
     * @param DelegateInterface $delegate
     *
     * @return ResponseInterface
     */
    public function process(RequestInterface $request, DelegateInterface $delegate)
    {
        $uri = $request->getUri();

        if (strtolower($uri->getScheme()) !== 'https') {
            $uri = $uri->withScheme('https')->withPort(443);

            if (!$this->checkHttpsForward ||
                (
                    $request->getHeaderLine('HTTP_X_FORWARDED_PROTO') !== 'https' &&
                    $request->getHeaderLine('HTTP_X_FORWARDED_PORT') !== '443'
                )) {
                return Utils\Factory::createResponse(301)
                    ->withHeader('Location', (string) $uri);
            }

            $request = $request->withUri($uri);
        }

        $response = $delegate->process($request);

        if (!empty($this->maxAge)) {
            $header = sprintf('max-age=%d%s', $this->maxAge, $this->includeSubdomains ? ';includeSubDomains' : '');
            $response = $response
                ->withHeader(self::HEADER, $header);
        }

        if ($response->hasHeader('Location')) {
            $location = Utils\Factory::createUri($response->getHeaderLine('Location'));

            if ($location->getHost() === '' || $location->getHost() === $uri->getHost()) {
                $location = $location->withScheme('https')->withPort(443);

                return $response->withHeader('Location', (string) $location);
            }
        }

        return $response;
    }
}
