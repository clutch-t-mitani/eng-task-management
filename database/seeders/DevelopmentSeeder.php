<?php

namespace Database\Seeders;

use App\Models\Engineer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $engineers = [
            '山田 太郎',
            '佐藤 花子',
            '鈴木 一郎',
        ];

        foreach ($engineers as $index => $name) {
            Engineer::query()->updateOrCreate(
                ['name' => $name],
                ['display_order' => $index + 1],
            );
        }

        $products = [
            ['name' => 'Product A', 'description' => 'メインサービス'],
            ['name' => 'Product B', 'description' => '管理ダッシュボード'],
        ];

        foreach ($products as $index => $product) {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                [
                    'description' => $product['description'],
                    'display_order' => $index + 1,
                ],
            );
        }

        $directors = [
            ['name' => '田中 美咲', 'email' => 'misaki.tanaka@example.com'],
            ['name' => '中村 健太', 'email' => 'kenta.nakamura@example.com'],
        ];

        foreach ($directors as $director) {
            $user = User::withTrashed()->firstOrNew(['email' => $director['email']]);
            $user->name = $director['name'];
            $user->password = 'password';
            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }
        }
    }
}
