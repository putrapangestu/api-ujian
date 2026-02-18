<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Role::all() as $role) {
            $user = User::create([
                'name' => $role->name,
                'email' => $role->name . '@example.com',
                'password' => bcrypt('password'),
            ]);

            $user->assignRole($role);
        }
    }
}
