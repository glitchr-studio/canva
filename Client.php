<?php

namespace Canva;

use Canva\Exception\ApiException;
use Canva\Exception\AuthenticationException;
use Canva\Model\Account;
use Canva\Model\Design;
use Canva\Model\Export;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Connect API, for the connected account: its designs, one design, an
 * export to PDF or pictures. The token comes from OAuth, refreshed as needed.
 */
final class Client
{
    public const BASE_URL = 'https://api.canva.com/rest/v1';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly OAuth $oauth,
        private readonly string $baseUrl = self::BASE_URL,
    ) {
    }

    public function isConnected(): bool
    {
        try {
            $this->oauth->token();

            return true;
        } catch (AuthenticationException) {
            return false;
        }
    }

    public function me(): Account
    {
        $me = $this->get('/users/me');
        $name = null;
        try {
            $name = $this->get('/users/me/profile')['profile']['display_name'] ?? null;
        } catch (ApiException $e) {
            if (403 !== $e->status) {
                throw $e;
            }
            // No profile:read scope: the ids alone.
        }

        return new Account((string) ($me['team_user']['user_id'] ?? ''), $me['team_user']['team_id'] ?? null, $name);
    }

    /**
     * The account's designs, newest first; `continuation` from a previous page fetches the next.
     *
     * @return array{items: list<Design>, continuation: ?string}
     */
    public function designs(?string $query = null, ?string $continuation = null, string $ownership = 'owned'): array
    {
        $data = $this->get('/designs', array_filter(['query' => $query, 'continuation' => $continuation, 'ownership' => $ownership, 'sort_by' => 'modified_descending']));

        return ['items' => array_map(fn (array $item) => Design::fromArray($item), $data['items'] ?? []), 'continuation' => $data['continuation'] ?? null];
    }

    public function design(string $id): Design
    {
        return Design::fromArray($this->get('/designs/'.rawurlencode($id))['design'] ?? []);
    }

    /** An export started: PDF (one file), PNG or JPG (one per page). */
    public function export(string $designId, string $type = 'pdf', array $options = []): Export
    {
        $format = ['type' => $type] + $options;
        if ('pdf' === $type && !isset($format['size'])) {
            $format['size'] = 'a4';
        }

        return Export::fromArray($this->post('/exports', ['design_id' => $designId, 'format' => $format])['job'] ?? []);
    }

    public function exportJob(string $jobId): Export
    {
        return Export::fromArray($this->get('/exports/'.rawurlencode($jobId))['job'] ?? []);
    }

    /** The export started and polled until done, or the time given runs out. */
    public function exportAndWait(string $designId, string $type = 'pdf', int $timeoutSeconds = 60, array $options = []): Export
    {
        $job = $this->export($designId, $type, $options);
        $until = microtime(true) + $timeoutSeconds;
        while (!$job->isDone() && microtime(true) < $until) {
            usleep(1500000);
            $job = $this->exportJob($job->id);
        }

        return $job;
    }

    /** The public embed address of a design published to the web (Share › Embed). */
    public static function embedUrl(string $designId): string
    {
        return sprintf('https://www.canva.com/design/%s/view?embed', rawurlencode($designId));
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query = []): array
    {
        return $this->call('GET', $path, ['query' => $query]);
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $json): array
    {
        return $this->call('POST', $path, ['json' => $json]);
    }

    /** @return array<string, mixed> */
    private function call(string $method, string $path, array $options): array
    {
        $token = $this->oauth->token();
        try {
            $response = $this->http->request($method, $this->baseUrl.$path, $options + ['auth_bearer' => $token->accessToken]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            throw new ApiException('Canva could not be reached: '.$e->getMessage(), 0, null, $e);
        }
        $data = '' === $content ? [] : (json_decode($content, true) ?? []);
        if (401 === $status) {
            throw new AuthenticationException('Canva refused the token: connect the account again.');
        }
        if ($status >= 400) {
            throw new ApiException(sprintf('Canva answered %d on %s %s: %s', $status, $method, $path, $data['message'] ?? $data['error'] ?? 'no detail'), $status, $data['code'] ?? null);
        }

        return $data;
    }
}
