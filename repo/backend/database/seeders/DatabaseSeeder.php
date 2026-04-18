<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Accounts are only created when this is a safe environment (local / development /
     * testing) OR when the operator has opted in via SEED_DEFAULT_ACCOUNTS=true. The
     * well-known passwords ("password") are only used in testing — every other environment
     * falls back to random passwords or operator-supplied ones via env vars.
     */
    public function run(): void
    {
        $this->seedFeatureFlags();

        if (! $this->shouldSeedAccounts()) {
            $this->command?->warn(
                'DatabaseSeeder: skipping default-account seed. Set SEED_DEFAULT_ACCOUNTS=true '
                . 'to force seeding, or create operator accounts via `php artisan tinker` / a migration.'
            );
            return;
        }

        $this->seedAccount('admin', 'role:admin', 'ADMIN_BOOTSTRAP_PASSWORD');
        $this->seedAccount('user1', 'role:user', 'USER_BOOTSTRAP_PASSWORD');
        $this->seedAccount('tech1', 'role:technician', 'TECH_BOOTSTRAP_PASSWORD');
    }

    private function shouldSeedAccounts(): bool
    {
        $explicit = env('SEED_DEFAULT_ACCOUNTS');
        if ($explicit !== null) {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        // Default behavior: only seed in local/testing.
        return in_array(app()->environment(), ['local', 'testing', 'development'], true);
    }

    private function seedAccount(string $username, string $roleSpec, string $passwordEnvKey): void
    {
        [, $role] = explode(':', $roleSpec);

        if (User::where('username', $username)->exists()) {
            return;
        }

        // Resolve the password: operator-supplied wins; otherwise the well-known literal in
        // local/testing; otherwise a random 32-char string that we log ONCE so the operator
        // can recover it.
        $envPassword = env($passwordEnvKey);
        $generated   = false;

        if ($envPassword !== null && $envPassword !== '') {
            $plainPassword = $envPassword;
        } elseif (app()->environment(['local', 'testing'])) {
            // Well-known password is acceptable in local and testing only.
            $plainPassword = 'password';
        } else {
            $plainPassword = Str::password(32);
            $generated     = true;
        }

        User::create([
            'username' => $username,
            'password' => Hash::make($plainPassword),
            'role'     => $role,
        ]);

        if ($generated) {
            // Never write plaintext credentials to the application log stream. Persist the
            // one-time bootstrap secret to a mode-0600 file in storage/ so an operator can
            // read it out-of-band; the console output only names the file and reminds the
            // operator to rotate after first login.
            $dir  = storage_path('app/bootstrap-secrets');
            if (! is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            $file = $dir . "/{$username}.txt";
            @file_put_contents($file, $plainPassword . "\n");
            @chmod($file, 0600);

            $this->command?->warn(
                "DatabaseSeeder: wrote one-time bootstrap password for '{$username}' to "
                . "storage/app/bootstrap-secrets/{$username}.txt — read it out-of-band, "
                . "rotate via the admin console, then delete the file."
            );
        }
    }

    private function seedFeatureFlags(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'recommended_enabled'],
            [
                'enabled'    => true,
                'reason'     => 'Default: recommendations enabled',
                'updated_at' => now(),
            ]
        );
    }
}
