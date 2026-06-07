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

                $role = $this->getRole($userData);
                if ($role && ! $existing->hasRole($role)) {
                    $existing->syncRoles($role);
                }

                $this->syncClientCompany($userData, $existing, $skipped);
                $imported++;
                continue;
            }

            $attributes = $this->transform($userData);
            $attributes['email'] = $email;
            $attributes['password'] = Hash::make(\Str::random(32));

            $role = $this->getRole($userData);
            unset($attributes['teamwork_id']);

            $user = $this->createModel($attributes);

            if ($role) {
                $user->assignRole($role);
            }

            $this->recordMapping((int) $userData['id'], $user);
            $this->syncClientCompany($userData, $user, $skipped);
            $imported++;
        }

        return ['imported' => $imported, 'fetched' => $totalRecords, 'skipped' => $skipped];
    }

    private function syncClientCompany(array $userData, $user, array &$skipped): void
    {
        $teamworkCompanyId = $userData['companyId'] ?? null;

        if ($teamworkCompanyId === null) {
            return;
        }

        $localCompany = $this->idMappingService->find((int) $teamworkCompanyId, 'company');

        if ($localCompany === null) {
            $skipped[] = ['id' => $userData['id'] ?? null, 'reason' => 'client_user_unresolved_company'];
            return;
        }

        $user->clientCompanies()->syncWithoutDetaching([$localCompany->getKey()]);
    }

    private function getRole(array $userData): ?string
    {
        if ($userData['isClientUser'] ?? false) {
            return $this->role ?? config('teamwork.client_role');
        }

        return $this->role ?? config('teamwork.default_role');
    }
}
