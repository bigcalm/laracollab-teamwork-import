<?php

namespace LaraCollab\TeamworkImport\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ApiClient
{
    private string $baseUrl;

    private string $token;

    private string $authMode;

    private ?string $username;

    private ?string $password;

    private int $timeout;

    private int $connectTimeout;

    private int $pageSize;

    private int $maxPages;

    private ?\Closure $onPage = null;

    private ?int $totalPages = null;

    private int $retryAfterMs = 0;

    public function __construct()
    {
        $config = config('teamwork.api');

        $this->baseUrl = $config['base_url'] ?? $this->resolveBaseUrl();
        $this->token = $config['token'];
        $this->authMode = $config['auth_mode'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->timeout = $config['timeout'] ?? 30;
        $this->connectTimeout = $config['connect_timeout'] ?? 10;
        $this->pageSize = $config['page_size'] ?? 100;
        $this->maxPages = $config['max_pages'] ?? 2000;
    }

    public function setOnPageCallback(?\Closure $callback): void
    {
        $this->onPage = $callback;
        $this->totalPages = null;
    }

    private function resolveBaseUrl(): string
    {
        $siteName = config('teamwork.api.site_name');

        if ($siteName) {
            return "https://{$siteName}.teamwork.com/projects/api/v3";
        }

        throw new \RuntimeException('TEAMWORK_API_BASE_URL or TEAMWORK_API_SITE_NAME must be set.');
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->retry(5, function (int $attempt, \Exception $e) {
                $delay = $this->retryAfterMs ?: $attempt * 2000;
                $this->retryAfterMs = 0;

                return $delay;
            }, function ($response) {
                if ($response instanceof \Illuminate\Http\Client\Response) {
                    if ($response->status() === 429) {
                        $this->retryAfterMs = ((int) $response->header('Retry-After', 5)) * 1000;

                        return true;
                    }

                    return $response->serverError();
                }

                return true;
            }, false);

        if ($this->authMode === 'basic_credentials') {
            $client->withBasicAuth($this->username, $this->password);
        } else {
            $client->withBasicAuth($this->token, '');
        }

        return $client;
    }

    private function extractTotalPages(array $body): ?int
    {
        $totalRecords = $body['meta']['page']['totalRecords']
            ?? $body['meta']['page']['total']
            ?? $body['meta']['totalRecords']
            ?? null;

        if ($totalRecords !== null && $this->pageSize > 0) {
            return (int) ceil((int) $totalRecords / $this->pageSize);
        }

        $pageCount = $body['meta']['page']['pageCount']
            ?? $body['meta']['page']['totalPages']
            ?? null;

        if ($pageCount !== null) {
            return (int) $pageCount;
        }

        return null;
    }

    private function paginate(string $resource): Collection
    {
        $all = collect();
        $page = 1;

        do {
            $response = $this->client()->get($resource, [
                'page' => $page,
                'pageSize' => $this->pageSize,
            ]);

            if ($response->clientError() && $page > 1) {
                break;
            }

            $response->throw();

            $body = $response->json();
            $data = $this->extractRecords($body);
            $all = $all->concat($data);

            if ($this->onPage) {
                if ($this->totalPages === null && $page === 1) {
                    $this->totalPages = $this->extractTotalPages($body);
                }
                call_user_func($this->onPage, $page, $all->count(), $this->totalPages);
            }

            $hasMore = $this->hasMorePages($body, $data);
            $page++;

            if ($hasMore) {
                usleep(200_000);
            }
        } while ($hasMore && $page <= $this->maxPages);

        return $all;
    }

    private function extractRecords(array $payload): array
    {
        if (array_is_list($payload)) {
            return $this->unwrapRecords(array_values(array_filter($payload, 'is_array')));
        }

        foreach ($payload as $key => $value) {
            if (in_array($key, ['included', 'links', 'meta', 'STATUS'], true)) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                return $this->unwrapRecords(array_values(array_filter($value, 'is_array')));
            }

            if (is_array($value) && isset($value['data']) && is_array($value['data']) && array_is_list($value['data'])) {
                return $this->unwrapRecords(array_values(array_filter($value['data'], 'is_array')));
            }

            if (is_array($value) && !array_is_list($value)) {
                foreach ($value as $innerKey => $innerValue) {
                    if ($innerKey === 'meta') {
                        continue;
                    }
                    if (is_array($innerValue) && array_is_list($innerValue)) {
                        return $this->unwrapRecords(array_values(array_filter($innerValue, 'is_array')));
                    }
                }
            }
        }

        return [];
    }

    private function unwrapRecords(array $records): array
    {
        return array_map(function ($record) {
            if (! is_array($record) || array_is_list($record)) {
                return $record;
            }

            $keys = array_keys($record);
            if (count($keys) === 1 && is_array($record[$keys[0]]) && ! array_is_list($record[$keys[0]])) {
                return $record[$keys[0]];
            }

            return $record;
        }, $records);
    }

    private function hasMorePages(array $payload, array $pageRecords): bool
    {
        $metaPage = $payload['meta']['page'] ?? null;

        if (is_array($metaPage) && array_key_exists('hasMore', $metaPage)) {
            return (bool) $metaPage['hasMore'];
        }

        return count($pageRecords) > 0;
    }

    public function getUsers(): Collection
    {
        return $this->paginate('people.json');
    }

    public function getCompanies(): Collection
    {
        return $this->paginate('companies.json');
    }

    public function getProjects(): Collection
    {
        return $this->paginate('projects.json');
    }

    public function getTaskLists(?int $projectId = null): Collection
    {
        if ($projectId !== null) {
            return $this->paginate("projects/{$projectId}/tasklists.json");
        }

        return $this->paginate('tasklists.json');
    }

    public function getTasks(?int $projectId = null): Collection
    {
        if ($projectId !== null) {
            return $this->paginate("projects/{$projectId}/tasks.json");
        }

        return $this->paginate('tasks.json');
    }

    public function getTags(): Collection
    {
        return $this->paginate('tags.json');
    }

    public function getTimeEntries(): Collection
    {
        return $this->paginate('time.json');
    }

    public function getProjectTimeEntries(int $projectId): Collection
    {
        return $this->paginate("projects/{$projectId}/time.json");
    }

    public function getComments(): Collection
    {
        return $this->paginate('comments.json');
    }

    public function getFiles(): Collection
    {
        return $this->paginate('files.json');
    }

    public function getProjectFiles(int $projectId): Collection
    {
        return $this->paginate("projects/{$projectId}/files.json");
    }

    public function getProjectPeople(int $projectId): Collection
    {
        return $this->paginate("projects/{$projectId}/people.json");
    }
}
