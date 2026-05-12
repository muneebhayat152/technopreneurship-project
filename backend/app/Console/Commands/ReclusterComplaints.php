<?php

namespace App\Console\Commands;

use App\Services\ClusteringService;
use Illuminate\Console\Command;

class ReclusterComplaints extends Command
{
    protected $signature = 'complaints:cluster {company_id? : Optional company ID}';

    protected $description = 'Rebuild TF-IDF / k-means style clusters, time series, and alerts for complaint data';

    public function handle(ClusteringService $clustering): int
    {
        $cid = $this->argument('company_id');
        if ($cid) {
            $clustering->reclusterCompany((int) $cid);
            $this->info('Reclustered company '.$cid);

            return self::SUCCESS;
        }

        $clustering->reclusterAllCompanies();
        $this->info('Reclustered all companies with complaints.');

        return self::SUCCESS;
    }
}
