<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UnfreezeExpiredAccounts extends Command
{
    protected $signature = 'app:unfreeze-accounts';
    protected $description = 'Unfreeze accounts whose frozen_until timestamp has passed';

    public function handle(): int
    {
        $count = User::where('account_status', 'frozen')
            ->where('frozen_until', '<', now())
            ->update([
                'account_status' => 'active',
                'frozen_until' => null,
            ]);

        if ($count > 0) {
            Log::info('accounts_unfrozen', ['count' => $count]);
        }

        return self::SUCCESS;
    }
}
