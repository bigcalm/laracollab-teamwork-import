<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use Illuminate\Support\Facades\Hash;
use LaraCollab\TeamworkImport\Services\Transformers\UserTransformer;

class UserPersister extends BasePersister
{
    protected string $entityKey = 'users';

    protected string $modelBindingKey = 'user';

    protected string $teamworkType = 'user';

    protected string $transformerClass = UserTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $users = $this->apiClient->getUsers();
        $totalRecords = $users->count();
        $imported = 0;
        $skipped = [];

        foreach ($users as $userData) {
            $email = strtolower(trim($userData['email'] ?? ''));

            if (empty($email)) {
                $skipped[] = ['id' => $userData['id'] ?? null, 'reason' => 'missing_email'];
                continue;
            }

            $modelClass = $this->getModelClass();
            $existing = $modelClass::where('email', $email)->first();

            if ($existing) {
                $this->recordMapping((int) $userData['id'], $existing);
                $imported++;
                continue;
            }

            $attributes = $this->transform($userData);
            $attributes['email'] = $email;
            $attributes['password'] = Hash::make(\Str::random(32));

            $role = $this->getRole();
            unset($attributes['teamwork_id']);

            $user = $this->createModel($attributes);

            if ($role) {
                $user->assignRole($role);
            }

            $this->recordMapping((int) $userData['id'], $user);
            $imported++;
        }

        return ['imported' => $imported, 'fetched' => $totalRecords, 'skipped' => $skipped];
    }

    private function getRole(): ?string
    {
        return $this->role ?? config('teamwork.default_role');
    }
}
