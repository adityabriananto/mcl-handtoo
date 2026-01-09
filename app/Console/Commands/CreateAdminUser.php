<?php

namespace App\Console\Commands;

use App\Models\User;
use Hash;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        User::create([
            'name' => 'Administrator MCL',
            'email' => 'admin@mcl.com',
            'password' => Hash::make('Admin123!'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }
}
