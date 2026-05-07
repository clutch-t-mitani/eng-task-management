<?php

namespace Tests\Feature;

use App\Models\Engineer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_master_apis(): void
    {
        $this->getJson('/api/v1/engineers')->assertUnauthorized();
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_engineer_crud_and_reorder(): void
    {
        $user = User::factory()->create();

        $first = Engineer::query()->create(['name' => '山田 太郎', 'display_order' => 1]);
        $second = Engineer::query()->create(['name' => '佐藤 花子', 'display_order' => 2]);

        $created = $this->actingAs($user)->postJson('/api/v1/engineers', [
            'name' => '鈴木 一郎',
        ])->assertCreated()->assertJsonPath('name', '鈴木 一郎');

        $this->actingAs($user)->putJson("/api/v1/engineers/{$first->id}", [
            'name' => '山田 次郎',
        ])->assertOk()->assertJsonPath('name', '山田 次郎');

        $this->actingAs($user)->patchJson('/api/v1/engineers/reorder', [
            'ordered_ids' => [$second->id, $first->id, $created->json('id')],
        ])->assertOk()->assertJsonPath('0.id', $second->id);

        $this->assertDatabaseHas('engineers', [
            'id' => $second->id,
            'display_order' => 1,
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/engineers/{$first->id}")->assertOk();
        $this->assertSoftDeleted($first);
    }

    public function test_product_crud_and_reorder(): void
    {
        $user = User::factory()->create();

        $first = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $second = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);

        $created = $this->actingAs($user)->postJson('/api/v1/products', [
            'name' => 'Product C',
            'description' => '新規',
        ])->assertCreated()->assertJsonPath('description', '新規');

        $this->actingAs($user)->putJson("/api/v1/products/{$first->id}", [
            'name' => 'Product A2',
            'description' => null,
        ])->assertOk()->assertJsonPath('name', 'Product A2');

        $this->actingAs($user)->patchJson('/api/v1/products/reorder', [
            'ordered_ids' => [$second->id, $first->id, $created->json('id')],
        ])->assertOk()->assertJsonPath('0.id', $second->id);

        $this->actingAs($user)->deleteJson("/api/v1/products/{$first->id}")->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $first->id]);
    }

    public function test_reorder_requires_all_registered_ids(): void
    {
        $user = User::factory()->create();

        $firstEngineer = Engineer::query()->create(['name' => '山田 太郎', 'display_order' => 1]);
        $secondEngineer = Engineer::query()->create(['name' => '佐藤 花子', 'display_order' => 2]);
        $firstProduct = Product::query()->create(['name' => 'Product A', 'display_order' => 1]);
        $secondProduct = Product::query()->create(['name' => 'Product B', 'display_order' => 2]);

        $this->actingAs($user)->patchJson('/api/v1/engineers/reorder', [
            'ordered_ids' => [$secondEngineer->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ordered_ids'])
            ->assertJsonPath('errors.ordered_ids.0', '並び順には登録済みエンジニアのIDをすべて指定してください。');

        $this->actingAs($user)->patchJson('/api/v1/products/reorder', [
            'ordered_ids' => [$secondProduct->id, $firstProduct->id, 999999],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ordered_ids'])
            ->assertJsonPath('errors.ordered_ids.0', '並び順には登録済みプロダクトのIDをすべて指定してください。');

        $this->assertDatabaseHas('engineers', [
            'id' => $firstEngineer->id,
            'display_order' => 1,
        ]);
        $this->assertDatabaseHas('engineers', [
            'id' => $secondEngineer->id,
            'display_order' => 2,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $firstProduct->id,
            'display_order' => 1,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $secondProduct->id,
            'display_order' => 2,
        ]);
    }

    public function test_user_crud_with_password_update(): void
    {
        $actor = User::factory()->create();

        $created = $this->actingAs($actor)->postJson('/api/v1/users', [
            'name' => '田中 美咲',
            'email' => 'misaki@example.com',
            'password' => 'secret-password',
        ])->assertCreated()->assertJsonMissing(['password']);

        $userId = $created->json('id');

        $this->actingAs($actor)->putJson("/api/v1/users/{$userId}", [
            'name' => '田中 美咲',
            'email' => 'misaki.new@example.com',
            'password' => 'new-secret-password',
        ])->assertOk()->assertJsonPath('email', 'misaki.new@example.com');

        $user = User::query()->findOrFail($userId);
        $this->assertTrue(Hash::check('new-secret-password', $user->password));

        $this->actingAs($actor)->deleteJson("/api/v1/users/{$userId}")->assertOk();
        $this->assertSoftDeleted($user);
    }

    public function test_user_email_can_be_reused_after_soft_delete(): void
    {
        $actor = User::factory()->create();
        $deletedUser = User::factory()->create([
            'email' => 'reused@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $deletedUser->delete();

        $created = $this->actingAs($actor)->postJson('/api/v1/users', [
            'name' => '再登録ユーザー',
            'email' => 'reused@example.com',
            'password' => 'new-password',
        ])->assertCreated()
            ->assertJsonPath('email', 'reused@example.com');

        Auth::forgetGuards();
        Auth::shouldUse('web');

        $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'reused@example.com',
            'password' => 'old-password',
        ])->assertUnprocessable();

        $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'reused@example.com',
            'password' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('id', $created->json('id'));
    }

    public function test_active_user_email_must_stay_unique(): void
    {
        $actor = User::factory()->create();
        $existing = User::factory()->create(['email' => 'active@example.com']);
        $target = User::factory()->create(['email' => 'target@example.com']);

        $this->actingAs($actor)->postJson('/api/v1/users', [
            'name' => '重複ユーザー',
            'email' => 'active@example.com',
            'password' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'このメールアドレスは既に使用されています。');

        $this->actingAs($actor)->putJson("/api/v1/users/{$target->id}", [
            'name' => '更新ユーザー',
            'email' => $existing->email,
            'password' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'このメールアドレスは既に使用されています。');
    }

    public function test_user_can_update_email_to_soft_deleted_user_email(): void
    {
        $actor = User::factory()->create();
        $deletedUser = User::factory()->create(['email' => 'deleted@example.com']);
        $target = User::factory()->create(['email' => 'target@example.com']);
        $deletedUser->delete();

        $this->actingAs($actor)->putJson("/api/v1/users/{$target->id}", [
            'name' => '更新ユーザー',
            'email' => 'deleted@example.com',
            'password' => null,
        ])->assertOk()
            ->assertJsonPath('email', 'deleted@example.com');
    }

    public function test_user_cannot_delete_self_or_last_user(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->deleteJson("/api/v1/users/{$actor->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ログイン中のユーザー自身は削除できません。');

        $other = User::factory()->create();
        $actor->delete();

        $this->actingAs($other)->deleteJson("/api/v1/users/{$actor->id}")
            ->assertNotFound();

        $this->actingAs($other)->deleteJson("/api/v1/users/{$other->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ログイン中のユーザー自身は削除できません。');
    }

    public function test_master_validation_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/engineers', [
            'name' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('errors.name.0', '氏名は必ず入力してください。');

        $this->actingAs($user)->postJson('/api/v1/products', [
            'name' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('errors.name.0', 'プロダクト名は必ず入力してください。');

        $this->actingAs($user)->postJson('/api/v1/users', [
            'name' => 'No Password',
            'email' => 'no-password@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('errors.password.0', 'パスワードは必ず入力してください。');
    }

    private function fromSpa(): static
    {
        return $this->withHeader('Referer', 'http://localhost:5173');
    }
}
