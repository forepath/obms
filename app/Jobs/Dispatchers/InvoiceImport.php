<?php

declare(strict_types=1);

namespace App\Jobs\Dispatchers;

use App\Jobs\Structure\Job;
use App\Jobs\TenantJobs\InvoiceImport as InvoiceImportJob;
use App\Models\Tenant;

/**
 * Class InvoiceImport.
 *
 * This class is the dispatcher job for importing invoice metadata via. IMAP inboxes.
 *
 * @author Marcel Menk <marcel.menk@ipvx.io>
 */
class InvoiceImport extends Job
{
    public $tries = 1;

    public $timeout = 3600;

    public static $onQueue = 'dispatchers';

    /**
     * Execute job algorithm.
     */
    public function handle()
    {
        // Dispatch job for main instance
        $this->dispatch((new InvoiceImportJob([
            'tenant_id' => 0,
        ]))->onQueue('invoice_import'));

        // Dispatch job for tenant instances
        Tenant::query()->each(function (Tenant $tenant) {
            $this->dispatch((new InvoiceImportJob([
                'tenant_id' => $tenant->id,
            ]))->onQueue('invoice_import'));
        });
    }

    /**
     * Define tags which the job can be identified by.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'job',
            'job:dispatcher',
            'job:dispatcher:InvoiceImport',
        ];
    }
}
