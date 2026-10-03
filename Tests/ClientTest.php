<?php

namespace Canva\Tests;

use Canva\Client;
use Canva\Exception\AuthenticationException;
use Canva\FileTokenStorage;
use Canva\Model\Token;
use Canva\OAuth;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ClientTest extends TestCase
{
    private string $tokenFile;

    protected function setUp(): void
    {
        $this->tokenFile = sys_get_temp_dir().'/canva-test-'.bin2hex(random_bytes(4)).'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->tokenFile);
    }

    public function testTheAuthorizationUrlCarriesThePkceChallengeAndTheScopes(): void
    {
        $oauth = new OAuth(new MockHttpClient(), new FileTokenStorage($this->tokenFile), 'id', 'secret', ['design:meta:read']);
        $verifier = OAuth::verifier();
        $url = $oauth->authorizationUrl('https://site.test/cb', 'st4te', $verifier);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame(OAuth::challenge($verifier), $query['code_challenge']);
        self::assertSame('s256', $query['code_challenge_method']);
        self::assertSame('design:meta:read', $query['scope']);
        self::assertSame('st4te', $query['state']);
        self::assertSame('https://site.test/cb', $query['redirect_uri']);
    }

    public function testTheCodeIsExchangedAndTheTokenStored(): void
    {
        $http = new MockHttpClient([new MockResponse(json_encode(['access_token' => 'acc', 'refresh_token' => 'ref', 'expires_in' => 3600, 'scope' => 'design:meta:read']))]);
        $storage = new FileTokenStorage($this->tokenFile);
        $oauth = new OAuth($http, $storage, 'id', 'secret');
        $token = $oauth->exchange('c0de', 'verifier', 'https://site.test/cb');
        self::assertSame('acc', $token->accessToken);
        self::assertSame('acc', $storage->load()?->accessToken);
        self::assertFalse($token->isExpired());
    }

    public function testAnExpiredTokenIsRefreshedBeforeACall(): void
    {
        $storage = new FileTokenStorage($this->tokenFile);
        $storage->save(new Token('old', 'ref', new \DateTimeImmutable('-1 hour')));
        $http = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'new', 'refresh_token' => 'ref2', 'expires_in' => 3600])),
            new MockResponse(json_encode(['items' => [['id' => 'DAF1', 'title' => 'Flashcards', 'thumbnail' => ['url' => 'https://t/1.png', 'width' => 100, 'height' => 80], 'urls' => ['edit_url' => 'https://e', 'view_url' => 'https://v'], 'page_count' => 4, 'created_at' => 1700000000, 'updated_at' => 1700003600]], 'continuation' => null])),
        ]);
        $client = new Client($http, new OAuth($http, $storage, 'id', 'secret'));
        $designs = $client->designs();
        self::assertSame('new', $storage->load()?->accessToken);
        self::assertCount(1, $designs['items']);
        self::assertSame('Flashcards', $designs['items'][0]->title);
        self::assertSame(4, $designs['items'][0]->pageCount);
        self::assertSame('2023-11-14', $designs['items'][0]->createdAt?->format('Y-m-d'));
    }

    public function testNoTokenMeansNotConnected(): void
    {
        $client = new Client(new MockHttpClient(), new OAuth(new MockHttpClient(), new FileTokenStorage($this->tokenFile), 'id', 'secret'));
        self::assertFalse($client->isConnected());
        $this->expectException(AuthenticationException::class);
        $client->me();
    }

    public function testAnExportIsPolledUntilDone(): void
    {
        $storage = new FileTokenStorage($this->tokenFile);
        $storage->save(new Token('acc', 'ref', new \DateTimeImmutable('+1 hour')));
        $http = new MockHttpClient([
            new MockResponse(json_encode(['job' => ['id' => 'job1', 'status' => 'in_progress']])),
            new MockResponse(json_encode(['job' => ['id' => 'job1', 'status' => 'success', 'urls' => ['https://export/1.pdf']]])),
        ]);
        $client = new Client($http, new OAuth($http, $storage, 'id', 'secret'));
        $export = $client->exportAndWait('DAF1', 'pdf', 10);
        self::assertTrue($export->isSuccess());
        self::assertSame(['https://export/1.pdf'], $export->urls);
        self::assertSame('https://www.canva.com/design/DAF1/view?embed', Client::embedUrl('DAF1'));
    }
}
