<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default branches
        $mainBranch = Branch::create([
            'name' => '본점',
            'code' => 'MAIN',
            'address' => '서울특별시 강남구 테헤란로 123',
            'phone' => '02-1234-5678',
            'is_active' => true,
        ]);

        $gangnamBranch = Branch::create([
            'name' => '강남점',
            'code' => 'GANGNAM',
            'address' => '서울특별시 강남구 역삼동 456',
            'phone' => '02-2345-6789',
            'is_active' => true,
        ]);

        // Create super admin
        User::create([
            'name' => '최고관리자',
            'email' => 'admin@aceacademy.com',
            'password' => Hash::make('password'),
            'phone' => '010-0000-0000',
            'role' => 'super_admin',
            'status' => 'active',
            'branch_id' => $mainBranch->id,
        ]);

        // Create branch admin for Gangnam
        User::create([
            'name' => '강남점 관리자',
            'email' => 'gangnam@aceacademy.com',
            'password' => Hash::make('password'),
            'phone' => '010-1111-1111',
            'role' => 'branch_admin',
            'status' => 'active',
            'branch_id' => $gangnamBranch->id,
        ]);

        // Create test student
        User::create([
            'name' => '테스트 학생',
            'email' => 'student@aceacademy.com',
            'password' => Hash::make('password'),
            'phone' => '010-2222-2222',
            'role' => 'student',
            'status' => 'active',
            'branch_id' => $mainBranch->id,
        ]);

        // Create test parent
        User::create([
            'name' => '테스트 학부모',
            'email' => 'parent@aceacademy.com',
            'password' => Hash::make('password'),
            'phone' => '010-3333-3333',
            'role' => 'parent',
            'status' => 'active',
            'branch_id' => $mainBranch->id,
        ]);
    }
}
