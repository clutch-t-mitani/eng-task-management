<?php

namespace Database\Seeders;

use App\Models\Engineer;
use App\Models\Issue;
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

        $engineerByName = Engineer::query()->pluck('id', 'name');
        $productByName = Product::query()->pluck('id', 'name');
        $directorByEmail = User::query()->pluck('id', 'email');

        $issues = [
            [
                'title' => 'ログイン画面のバリデーション修正',
                'github_issue_number' => 101,
                'director_email' => 'misaki.tanaka@example.com',
                'engineer_name' => '山田 太郎',
                'status' => '完了',
                'product_name' => 'Product A',
                'display_order' => 1,
                'schedule' => ['planned_start' => '2026-04-01', 'actual_start' => '2026-04-01', 'planned_end' => '2026-04-10', 'actual_end' => '2026-04-09', 'planned_hours' => 16, 'actual_hours' => 15.5],
            ],
            [
                'title' => 'パスワードリセット機能の実装',
                'github_issue_number' => 102,
                'director_email' => 'misaki.tanaka@example.com',
                'engineer_name' => '佐藤 花子',
                'status' => '完了',
                'product_name' => 'Product A',
                'display_order' => 2,
                'schedule' => ['planned_start' => '2026-04-05', 'actual_start' => '2026-04-06', 'planned_end' => '2026-04-15', 'actual_end' => '2026-04-16', 'planned_hours' => 20, 'actual_hours' => 22],
            ],
            [
                'title' => 'ダッシュボードのレスポンシブ対応',
                'github_issue_number' => 103,
                'director_email' => 'kenta.nakamura@example.com',
                'engineer_name' => '山田 太郎',
                'status' => '作業中',
                'product_name' => 'Product A',
                'display_order' => 3,
                'schedule' => ['planned_start' => '2026-04-15', 'actual_start' => '2026-04-15', 'planned_end' => '2026-04-25', 'actual_end' => null, 'planned_hours' => 24, 'actual_hours' => 12.5],
            ],
            [
                'title' => 'CSVエクスポート機能',
                'github_issue_number' => 104,
                'director_email' => 'misaki.tanaka@example.com',
                'engineer_name' => '鈴木 一郎',
                'status' => 'テスト中',
                'product_name' => 'Product A',
                'display_order' => 4,
                'schedule' => ['planned_start' => '2026-04-10', 'actual_start' => '2026-04-12', 'planned_end' => '2026-04-22', 'actual_end' => null, 'planned_hours' => 18, 'actual_hours' => 17],
            ],
            [
                'title' => '通知設定ページの実装',
                'github_issue_number' => 105,
                'director_email' => 'kenta.nakamura@example.com',
                'engineer_name' => '佐藤 花子',
                'status' => '未着手',
                'product_name' => 'Product A',
                'display_order' => 5,
                'schedule' => ['planned_start' => '2026-05-01', 'actual_start' => null, 'planned_end' => '2026-05-08', 'actual_end' => null, 'planned_hours' => 12, 'actual_hours' => null],
            ],
            [
                'title' => 'APIレート制限の実装',
                'github_issue_number' => 106,
                'director_email' => 'misaki.tanaka@example.com',
                'engineer_name' => '山田 太郎',
                'status' => '保留',
                'product_name' => 'Product A',
                'display_order' => 6,
                'schedule' => ['planned_start' => '2026-04-20', 'actual_start' => '2026-04-20', 'planned_end' => '2026-04-27', 'actual_end' => null, 'planned_hours' => 10, 'actual_hours' => 4],
            ],
            [
                'title' => 'ユーザー管理画面リニューアル',
                'github_issue_number' => 201,
                'director_email' => 'kenta.nakamura@example.com',
                'engineer_name' => '佐藤 花子',
                'status' => '作業中',
                'product_name' => 'Product B',
                'display_order' => 1,
                'schedule' => ['planned_start' => '2026-05-01', 'actual_start' => '2026-05-02', 'planned_end' => '2026-05-20', 'actual_end' => null, 'planned_hours' => 32, 'actual_hours' => 9],
            ],
            [
                'title' => '権限管理機能の追加',
                'github_issue_number' => 202,
                'director_email' => 'kenta.nakamura@example.com',
                'engineer_name' => '鈴木 一郎',
                'status' => '未着手',
                'product_name' => 'Product B',
                'display_order' => 2,
                'schedule' => ['planned_start' => '2026-05-15', 'actual_start' => null, 'planned_end' => '2026-05-30', 'actual_end' => null, 'planned_hours' => 28, 'actual_hours' => null],
            ],
            [
                'title' => 'バグ修正: 検索結果ページのクラッシュ',
                'github_issue_number' => 301,
                'director_email' => 'misaki.tanaka@example.com',
                'engineer_name' => '山田 太郎',
                'status' => '作業中',
                'product_name' => 'Product A',
                'display_order' => 7,
                'schedule' => ['planned_start' => '2026-04-20', 'actual_start' => '2026-04-21', 'planned_end' => '2026-04-24', 'actual_end' => null, 'planned_hours' => 8, 'actual_hours' => 6.5],
            ],
            [
                'title' => 'パフォーマンス改善: DB クエリ最適化',
                'github_issue_number' => 302,
                'director_email' => 'kenta.nakamura@example.com',
                'engineer_name' => '鈴木 一郎',
                'status' => '未着手',
                'product_name' => 'Product B',
                'display_order' => 3,
                'schedule' => ['planned_start' => '2026-05-10', 'actual_start' => null, 'planned_end' => '2026-05-25', 'actual_end' => null, 'planned_hours' => 16, 'actual_hours' => null],
            ],
        ];

        foreach ($issues as $issueData) {
            $productId = $productByName[$issueData['product_name']];
            $issue = Issue::query()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'github_issue_number' => $issueData['github_issue_number'],
                ],
                [
                    'title' => $issueData['title'],
                    'github_url' => sprintf('https://github.com/example/repo/issues/%d', $issueData['github_issue_number']),
                    'director_id' => $directorByEmail[$issueData['director_email']],
                    'engineer_id' => $engineerByName[$issueData['engineer_name']],
                    'status' => $issueData['status'],
                    'is_managed' => true,
                    'display_order' => $issueData['display_order'],
                    'github_state' => $issueData['status'] === '完了' ? 'closed' : 'open',
                    'github_synced_at' => now(),
                ],
            );

            $issue->schedule()->updateOrCreate(
                ['issue_id' => $issue->id],
                $issueData['schedule'],
            );
        }
    }
}
