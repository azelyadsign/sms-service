<?php

namespace Azelya\SmsService\Http;

use Azelya\SmsService\Config;
use Azelya\SmsService\Dto\AuthResponse;
use Azelya\SmsService\Exception\AuthenticationException;
use Azelya\SmsService\Exception\ConflictException;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Exception\NetworkException;
use Azelya\SmsService\Exception\NotFoundException;
use Azelya\SmsService\Exception\PermissionDeniedException;
use Azelya\SmsService\Exception\RateLimitException;
use Azelya\SmsService\Exception\SmsGatewayException;
use Azelya\SmsService\Exception\ValidationException;
use Azelya\SmsService\TokenStore\TokenStore;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The only place in the SDK that talks HTTP: executes requests, resolves
 * authentication, decodes responses and maps every failure to a typed
 * exception.
 */
final class ApiClient
{
    private ?string $token = null;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly Config $config,
        private readonly TokenStore $tokenStore,
    ) {
    }

    /**
     * Execute a request against the gateway and return the decoded JSON body.
     *
     * @param  string  $uri  Path relative to the configured base URL, e.g. "/sms/send".
     * @param  array<string, mixed>  $options  Additional Guzzle request options (json, query, ...).
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $uri,
        array $options = [],
        AuthMode $auth = AuthMode::Bearer,
        bool $retried = false,
    ): array {
        $requestOptions = $options;
        $requestOptions['headers'] = array_merge($this->headers($auth), $options['headers'] ?? []);

        $response = $this->send($method, $uri, $requestOptions);

        $status = $response->getStatusCode();

        if (
            $status === 401
            && $auth === AuthMode::Bearer
            && ! $retried
            && $this->config->retryOnUnauthorized
            && $this->config->credentials() !== null
        ) {
            // The token was rejected before any controller ran, so a single
            // re-login + retry is side-effect free and recovers from revoked
            // or expired tokens.
            $this->forgetToken();
            $this->autoLogin();

            return $this->request($method, $uri, $options, $auth, retried: true);
        }

        $body = $this->decode($response);

        if ($status >= 400) {
            throw $this->exceptionFor($status, $body, $response);
        }

        return $body;
    }

    /**
     * Log in with explicit credentials and persist the issued token.
     */
    public function authenticate(string $email, string $password, bool $app = false): AuthResponse
    {
        $body = $this->request(
            'POST',
            $app ? '/app/login' : '/login',
            ['json' => ['email' => $email, 'password' => $password]],
            AuthMode::None,
        );

        $response = AuthResponse::fromArray($body);
        $this->setToken($response->accessToken);

        return $response;
    }

    /**
     * The access token in use, if any (config, memory or store).
     */
    public function getToken(): ?string
    {
        return $this->config->token ?? $this->token ?? $this->tokenStore->load();
    }

    /**
     * Replace the access token and persist it.
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
        $this->tokenStore->save($token);
    }

    /**
     * Drop the cached access token (used on logout and before re-login).
     */
    public function forgetToken(): void
    {
        $this->token = null;
        $this->tokenStore->forget();
    }

    private function send(string $method, string $uri, array $options): ResponseInterface
    {
        try {
            $response = $this->http->request($method, $this->uri($uri), $options);
        } catch (RequestException $e) {
            // Handles both transfer failures and 4xx/5xx responses when a
            // caller injected a Guzzle client with http_errors left enabled.
            if (! $e->hasResponse()) {
                throw new NetworkException('Could not reach the SMS gateway: '.$e->getMessage(), 0, $e);
            }

            $response = $e->getResponse();
        } catch (GuzzleException $e) {
            throw new NetworkException('Could not reach the SMS gateway: '.$e->getMessage(), 0, $e);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function headers(AuthMode $auth): array
    {
        $headers = ['Accept' => 'application/json'];

        return match ($auth) {
            AuthMode::Bearer => $headers + ['Authorization' => 'Bearer '.$this->bearerToken()],
            AuthMode::Device => $headers + ['X-Device-Token' => $this->deviceToken()],
            AuthMode::None => $headers,
        };
    }

    private function bearerToken(): string
    {
        $token = $this->getToken();

        return $token ?? $this->autoLogin()->accessToken;
    }

    private function deviceToken(): string
    {
        if ($this->config->deviceToken === null || trim($this->config->deviceToken) === '') {
            throw new AuthenticationException(
                'No device token configured. Set the "device_token" option to call gateway device endpoints.'
            );
        }

        return $this->config->deviceToken;
    }

    private function autoLogin(): AuthResponse
    {
        $credentials = $this->config->credentials();

        if ($credentials === null) {
            throw new AuthenticationException(
                'No access token available. Configure "email" and "password" (or "app_email" and "app_password") '
                .'credentials, or a pre-provisioned "token".'
            );
        }

        return $this->authenticate($credentials['email'], $credentials['password'], $credentials['app']);
    }

    private function uri(string $uri): string
    {
        return rtrim($this->config->baseUrl, '/').'/'.ltrim($uri, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();

        if (trim($contents) === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidResponseException('The SMS gateway returned an invalid JSON response.', 0, $e);
        }

        if (! is_array($decoded)) {
            throw new InvalidResponseException('The SMS gateway returned an unexpected response body.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function exceptionFor(int $status, array $body, ResponseInterface $response): SmsGatewayException
    {
        $message = is_string($body['message'] ?? null) ? $body['message'] : $response->getReasonPhrase();
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];

        return match ($status) {
            401 => new AuthenticationException($message, $status, context: ['errors' => $errors]),
            403 => new PermissionDeniedException($message, $status, context: ['errors' => $errors]),
            404 => new NotFoundException($message, $status, context: ['errors' => $errors]),
            409 => new ConflictException($message, $status, context: ['errors' => $errors]),
            422 => new ValidationException($message, $status, context: ['errors' => $errors]),
            429 => new RateLimitException($message, $status, context: [
                'errors' => $errors,
                'retry_after' => $this->retryAfter($response),
            ]),
            default => new SmsGatewayException($message, $status, context: ['errors' => $errors]),
        };
    }

    private function retryAfter(ResponseInterface $response): ?int
    {
        $header = $response->getHeaderLine('Retry-After');

        if ($header !== '' && is_numeric($header)) {
            return (int) $header;
        }

        $reset = $response->getHeaderLine('X-RateLimit-Reset');

        if ($reset !== '' && is_numeric($reset)) {
            return max(0, (int) $reset - time());
        }

        return null;
    }
}
