<?php

namespace App\Jobs;

use App\Services\ClusteringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReclusterCompanyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $companyId)
    {
    }

    public function handle(ClusteringService $clustering): void
    {
        $clustering->reclusterCompany($this->companyId);
    }
}

