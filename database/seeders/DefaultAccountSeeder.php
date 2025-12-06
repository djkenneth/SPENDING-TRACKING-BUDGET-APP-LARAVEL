<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Account;
use Illuminate\Database\Seeder;

class DefaultAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // This seeder creates default accounts for existing users who don't have any accounts

        $usersWithoutAccounts = User::whereDoesntHave('accounts')->get();

        foreach ($usersWithoutAccounts as $user) {
            $this->createDefaultAccountsForUser($user);
        }
    }

    /**
     * Create default accounts for a user
     */
    private function createDefaultAccountsForUser(User $user): void
    {
        $defaultAccounts = [
            [
                'name' => 'Cash',
                'type' => 'cash',
                'balance' => 0.00,
                'currency' => $user->currency,
                'color' => '#4CAF50',
                'icon' => 'account_balance_wallet',
                'description' => 'Cash and petty cash',
            ],
            [
                'name' => 'Savings',
                'type' => 'bank',
                'balance' => 0.00,
                'currency' => $user->currency,
                'color' => '#009688',
                'icon' => 'savings',
                'description' => 'Savings account',
            ],
        ];

        foreach ($defaultAccounts as $accountData) {
            $account = $user->accounts()->create($accountData);

            // Create initial balance history record
            $account->balanceHistory()->create([
                'balance' => $account->balance,
                'date' => now()->format('Y-m-d'),
                'change_type' => 'initial',
                'change_amount' => $account->balance,
            ]);
        }
    }
}
