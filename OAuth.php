<?php

namespace Canva;

use Canva\Exception\ApiException;
use Canva\Exception\AuthenticationException;
use Canva\Model\Token;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Canva's OAuth 2.0 with PKCE: the user is sent to Canva with a code
 * challenge, comes back with a code, the code and the verifier buy a token
 * (kept in the storage), the token is refreshed when it expires.
 */
final class OAuth
{
    public const AUTHORIZE_URL = 'https://www.canva.com/api/oauth/authorize';
    public const TOKEN_URL = 'https://api.canva.com/rest/v1/oauth/token';
    public const REVOKE_URL = 'https://api.canva.com/rest/v1/oauth/revoke';

    /** @param list<string> $scopes */
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly TokenStorageInterface $tokens,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
        private readonly array $scopes = ['design:meta:read', 'design:content:read', 'asset:read', 'profile:read'],
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== (string) $this->clientId && '' !== (string) $this->clientSecret;
    }

    /** A PKCE code verifier: 43-128 URL-safe characters, kept in the session until the way back. */
    public static function verifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Where to send the user. $state comes back untouched: check it. */
    public function authorizationUrl(string $redirectUri, string $state, string $verifier): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'code_challenge' => self::challenge($verifier),
            'code_challenge_method' => 's256',
            'scope' => implode(' ', $this->scopes),
            'response_type' => 'code',
            'client_id' => (string) $this->clientId,
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /** The code brought back, exchanged for a token - and stored. */
    public function exchange(string $code, string $verifier, string $redirectUri): Token
    {
        $token = $this->tokenRequest(['grant_type' => 'authorization_code', 'code_verifier' => $verifier, 'code' => $code, 'redirect_uri' => $redirectUri]);
        $this->tokens->save($token);

        return $token;
    }

    /** A new token from the refresh token - and stored; the account must reconnect when Canva refuses. */
    public function refresh(Token $token): Token
    {
        if (!$token->refreshToken) {
            throw new AuthenticationException('No refresh token: connect the Canva account again.');
        }
        try {
            $fresh = $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $token->refreshToken]);
        } catch (ApiException $e) {
            if (400 === $e->status || 401 === $e->status) {
                $this->tokens->clear();
                throw new AuthenticationException('Canva refused the refresh token: connect the account again.', 0, $e);
            }
            throw $e;
        }
        $this->tokens->save($fresh);

        return $fresh;
    }

    /** The stored token, refreshed when it is about to expire; none: not connected. */
    public function token(): Token
    {
        $token = $this->tokens->load() ?? throw new AuthenticationException('The Canva account is not connected.');

        return $token->isExpired() ? $this->refresh($token) : $token;
    }

    public function disconnect(): void
    {
        $token = $this->tokens->load();
        if ($token) {
            try {
                $this->http->request('POST', self::REVOKE_URL, ['auth_basic' => [(string) $this->clientId, (string) $this->clientSecret], 'body' => ['token' => $token->refreshToken ?? $token->accessToken]])->getStatusCode();
            } catch (ExceptionInterface) {
                // Revoked or not, the account is forgotten here.
            }
        }
        $this->tokens->clear();
    }

    /** @param array<string, string> $body */
    private function tokenRequest(array $body): Token
    {
        try {
            $response = $this->http->request('POST', self::TOKEN_URL, [
                'auth_basic' => [(string) $this->clientId, (string) $this->clientSecret],
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => $body,
            ]);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true) ?? [];
        } catch (ExceptionInterface $e) {
            throw new ApiException('Canva\'s token endpoint could not be reached: '.$e->getMessage(), 0, null, $e);
        }
        if ($status >= 400 || !isset($data['access_token'])) {
            throw new ApiException(sprintf('Canva refused the token request (%d): %s', $status, $data['error_description'] ?? $data['error'] ?? $data['message'] ?? 'no detail'), $status, $data['error'] ?? $data['code'] ?? null);
        }

        return Token::fromResponse($data);
    }
}
