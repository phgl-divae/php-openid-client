<?php

declare(strict_types=1);

namespace Facile\OpenIDClient\Middleware;

use function class_exists;
use Dflydev\FigCookies\Cookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Facile\OpenIDClient\Exception\LogicException;
use Facile\OpenIDClient\Session\AuthSession;
use Facile\OpenIDClient\Session\AuthSessionInterface;
use function is_array;
use function json_decode;
use function json_encode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @deprecated Use a custom Middleware to store AuthSession
 */
class SessionCookieMiddleware implements MiddlewareInterface
{
    public const SESSION_ATTRIBUTE = AuthSessionInterface::class;

    /** @var string */
    private $cookieName;

    /** @var null|int */
    private $cookieMaxAge;

    /** @var bool */
    private $secure;

    public function __construct(string $cookieName = 'openid', ?int $cookieMaxAge = null, bool $secure = true)
    {
        $this->cookieName = $cookieName;
        $this->cookieMaxAge = $cookieMaxAge;
        $this->secure = $secure;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! class_exists(Cookies::class)) {
            throw new LogicException('To use the SessionCookieMiddleware you should install dflydev/fig-cookies package');
        }

        $cookies = Cookies::fromRequest($request);
        $sessionCookie = $cookies->get($this->cookieName);

        $cookieValue = null !== $sessionCookie ? $sessionCookie->getValue() : null;
        $data = null !== $cookieValue ? json_decode($cookieValue, true) : [];

        if (! is_array($data)) {
            $data = [];
        }

        $authSession = AuthSession::fromArray($data);

        $response = $handler->handle($request->withAttribute(self::SESSION_ATTRIBUTE, $authSession));

        /** @var string $cookieValue */
        $cookieValue = json_encode($authSession);

        $sessionCookie = SetCookie::create($this->cookieName)
            ->withValue($cookieValue)
            ->withMaxAge($this->cookieMaxAge)
            ->withHttpOnly()
            ->withPath('/')
            ->withSameSite(SameSite::strict());

        if ($this->secure) {
            $sessionCookie = $sessionCookie->withSecure();
        }

        $response = FigResponseCookies::set($response, $sessionCookie);

        return $response;
    }
}
