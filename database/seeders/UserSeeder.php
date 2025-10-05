<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@organic.com',
            'phone' => '0123456789',
            'address' => '123 Đường ABC, Quận 1, TP.HCM',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        // Create regular users
        $users = [
            [
                'name' => 'Nguyễn Văn A',
                'email' => 'user1@example.com',
                'phone' => '0987654321',
                'address' => '456 Đường XYZ, Quận 2, TP.HCM',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ],
            [
                'name' => 'Trần Thị B',
                'email' => 'user2@example.com',
                'phone' => '0912345678',
                'address' => '789 Đường DEF, Quận 3, TP.HCM',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ],
            [
                'name' => 'Lê Văn C',
                'email' => 'user3@example.com',
                'phone' => '0923456789',
                'address' => '321 Đường GHI, Quận 4, TP.HCM',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
}
