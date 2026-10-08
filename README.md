# glitchr/canva

[Canva's Connect API](https://www.canva.dev/docs/connect/) in PHP, the way
the Omni family does its integrations: a small library with no framework in
it, and a Symfony bundle beside it.

What it covers, for one user's account connected through OAuth 2.0 (PKCE):

- `OAuth`: the authorization URL to send the user to, the code exchanged for
  a token, the token refreshed; the token kept by a `TokenStorageInterface`
  the application provides (a setting, a row).
- `Client::me()`, `Client::designs($query, $continuation)`,
  `Client::design($id)`: the account, its designs (title, thumbnail, edit
  and view URLs, pages), one design.
- `Client::export($designId, 'pdf'|'png'|'jpg')`, `Client::exportJob($jobId)`,
  `Client::exportAndWait()`: a design rendered to files, the URLs to fetch
  them from (short-lived).
- `Client::embedUrl($designId)`: the public embed address of a design
  published to the web.

Errors are `Canva\Exception\CanvaException` (an `AuthenticationException`
when the token is missing or refused, an `ApiException` with the API's code
otherwise).

## Install

```bash
composer require glitchr/canva
```

An integration is declared at https://www.canva.com/developers/ (Connect
APIs): its client id and secret, the redirect URL of your site, the scopes
(`design:meta:read design:content:read asset:read profile:read`).

## Symfony

```php
// config/bundles.php
Canva\Bridge\Symfony\CanvaBundle::class => ['all' => true],
```

```yaml
# config/packages/canva.yaml
canva:
    client_id: '%env(CANVA_CLIENT_ID)%'
    client_secret: '%env(CANVA_CLIENT_SECRET)%'
    redirect_route: app_canva_callback        # the route your controller answers at
    scopes: ['design:meta:read', 'design:content:read', 'asset:read', 'profile:read']
    token_storage: App\Tools\CanvaTokens      # your TokenStorageInterface; default: a JSON file under var/
```

Then, in a controller: `OAuth::authorizationUrl($redirectUri, $state, $verifier)`
to start (keep `$state` and `$verifier` in the session), `OAuth::exchange($code, $verifier, $redirectUri)`
on the way back - it stores the token -, and `Client` anywhere, which refreshes
the token itself when it expires.

## Try it

```bash
cd docker && cp .env.dist .env   # CANVA_CLIENT_ID, CANVA_CLIENT_SECRET, CANVA_REFRESH_TOKEN
docker compose run --rm canva me
docker compose run --rm canva designs
docker compose run --rm canva export DAF... pdf
docker compose run --rm canva test
```

## License

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
