<?php

namespace App\Console\Commands;

use App\Services\StockService;
use Illuminate\Console\Command;

class VerifyStock extends Command
{
    protected $signature = 'stock:verify';

    protected $description = 'Reconcile every part\'s current stock against its ledger (read-only)';

    public function handle(StockService $stock): int
    {
        $issues = $stock->verify();
        if (! $issues) {
            $this->info('OK — all stock balances match their ledgers and no ledger mixes inventory types.');

            return self::SUCCESS;
        }
        $this->error(count($issues).' discrepancy(ies) found:');
        $this->table(['Part ID', 'SKU', 'Name', 'Inventory', 'Stored balance', 'Ledger balance'], array_map(fn ($i) => array_values($i), $issues));

        return self::FAILURE;
    }
}
