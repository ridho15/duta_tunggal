<?php

namespace App\Console\Commands;

use App\Services\PurchaseReturnAutomationService;
use Illuminate\Console\Command;

class AutomatePurchaseReturn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purchase:automate-return {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically create purchase returns based on quality control results';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Purchase Return Automation...');

        $isDryRun = $this->option('dry-run');
        
        $automationService = app(PurchaseReturnAutomationService::class);
        
        $result = $automationService->automatePurchaseReturns($isDryRun);
        
        if ($isDryRun) {
            $this->line('DRY RUN MODE - No changes were made');
        }
        
        $this->info("Processed {$result['processed']} quality control items");
        $this->info("Created {$result['created']} purchase returns");
        
        if (!empty($result['errors'])) {
            $this->error('Errors encountered:');
            foreach ($result['errors'] as $error) {
                $this->error("- {$error}");
            }
        }
        
        $this->info('Purchase Return Automation completed!');
        
        return Command::SUCCESS;
    }
}
