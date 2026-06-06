<?php

namespace LaraCollab\TeamworkImport\Services\Persisters;

use LaraCollab\TeamworkImport\Services\Transformers\CompanyTransformer;

class CompanyPersister extends BasePersister
{
    protected string $entityKey = 'companies';

    protected string $modelBindingKey = 'client_company';

    protected string $teamworkType = 'company';

    protected string $transformerClass = CompanyTransformer::class;

    public function run(?array $entityFilter = null, ?int $projectId = null): array
    {
        $companies = $this->apiClient->getCompanies();
        $totalRecords = $companies->count();
        $imported = 0;

        foreach ($companies as $companyData) {
            $name = $companyData['name'] ?? '';

            if (empty($name)) {
                continue;
            }

            $attributes = $this->transform($companyData);
            unset($attributes['teamwork_id']);

            $company = $this->createModel($attributes);
            $this->recordMapping((int) $companyData['id'], $company);
            $imported++;
        }

        return ['imported' => $imported, 'fetched' => $totalRecords];
    }
}
