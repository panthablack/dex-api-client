<?php

namespace App\Http\Controllers;

use App\Enums\DataMigrationStatus;
use App\Enums\ResourceType;
use App\Models\DataMigration;
use App\Models\DataMigrationBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class VerificationController extends Controller
{
    public function getBatchVerificationInfo(
        DataMigration $migration,
        ResourceType $type
    ): Collection {
        return $migration->batches->where('resource_type', '=', $type->value)->map(function (DataMigrationBatch $batch) {
            return ['verification_status' => $batch->getVerificationStatus()];
        });
    }

    public function getResourceVerificationInfo(DataMigration $migration): array
    {
        return [
            ResourceType::CLIENT->value => $this->getBatchVerificationInfo(
                $migration,
                ResourceType::CLIENT
            ),
            ResourceType::CASE->value => $this->getBatchVerificationInfo(
                $migration,
                ResourceType::CASE
            ),
            ResourceType::SESSION->value => $this->getBatchVerificationInfo(
                $migration,
                ResourceType::SESSION
            ),
        ];
    }

    public function getStatus(DataMigration $migration)
    {
        $resourceVerificationInfo = $this->getResourceVerificationInfo($migration);
        return [
            'resource_verification' => $resourceVerificationInfo
        ];
    }
}
