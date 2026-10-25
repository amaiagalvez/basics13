<?php

namespace Basics13\Tests\Unit\Transformers;

use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Basics13\Transformers\UserListTransformer;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The concrete account transformer. Its force-delete guard is fixed to false, so the hint branch is
 * reached through a subclass that only flips the condition, which keeps the parent's own hint.
 */
final class UserListTransformerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('dashboard', fn () => null)->name('dashboard');
        Route::get('users', fn () => null)->name('users.index');
        Route::get('users/archived', fn () => null)->name('users.archived.index');
        Route::get('users/trash', fn () => null)->name('users.trash.index');
        Route::delete('users/{record}', fn () => null)->name('users.destroy');
        Route::patch('users/{record}/archive', fn () => null)->name('users.archive');
        Route::patch('users/archived/{record}', fn () => null)->name('users.archived.activate');
        Route::patch('users/trash/{record}', fn () => null)->name('users.trash.restore');
        Route::delete('users/trash/{record}', fn () => null)->name('users.trash.destroy');
    }

    public function test_the_trash_view_blocks_force_delete_and_carries_the_hint(): void
    {
        $transformer = new ForceDeleteBlockingUserListTransformer;
        $account = new User(['name' => 'Ane Bezeroa', 'email' => 'ane@example.test']);
        $account->id = 7;

        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator([$account], 1, 15);

        /** @var array{rows: list<array{actions: list<array<string, mixed>>}>} $list */
        $list = $transformer->trash($records, '');

        $blocked = $list['rows'][0]['actions'][1];

        $this->assertSame('blocked', $blocked['type']);
        $this->assertSame(__('basics13::messages.delete_permanently'), $blocked['label']);
        $this->assertSame('user-force-delete-blocked-7', $blocked['test']);
        $this->assertSame(__('basics13::messages.user_force_delete_disabled'), $blocked['hint']);
    }

    public function test_routes_use_the_plural_resource_key_by_default(): void
    {
        $list = (new UserListTransformer)->active(new LengthAwarePaginator([], 0, 15), '');

        $this->assertSame(route('users.index'), $list['search']['action']);
        $this->assertSame([
            route('users.index'),
            route('users.archived.index'),
            route('users.trash.index'),
        ], array_column($list['tabs'], 'url'));
    }

    public function test_language_name_returns_native_name_for_valid_language(): void
    {
        $transformer = new UserListTransformer;

        $account = new User(['name' => 'Ane', 'email' => 'ane@example.test', 'lang' => 'eu']);
        $account->id = 1;

        $reflection = new \ReflectionMethod($transformer, 'languageName');
        $reflection->setAccessible(true);

        $this->assertSame('Euskara', $reflection->invoke($transformer, $account));
    }

    public function test_language_name_returns_empty_for_invalid_language(): void
    {
        $transformer = new UserListTransformer;

        $account = new User(['name' => 'Ane', 'email' => 'ane@example.test', 'lang' => 'xx']);
        $account->id = 1;

        $reflection = new \ReflectionMethod($transformer, 'languageName');
        $reflection->setAccessible(true);

        $this->assertSame('', $reflection->invoke($transformer, $account));
    }

    public function test_language_name_returns_empty_when_lang_is_null(): void
    {
        $transformer = new UserListTransformer;

        $account = new User(['name' => 'Ane', 'email' => 'ane@example.test', 'lang' => null]);
        $account->id = 1;

        $reflection = new \ReflectionMethod($transformer, 'languageName');
        $reflection->setAccessible(true);

        $this->assertSame('', $reflection->invoke($transformer, $account));
    }

    public function test_role_ids_returns_empty_when_roles_not_loaded(): void
    {
        $transformer = new UserListTransformer;

        $account = new User(['id' => 1, 'name' => 'Ane', 'email' => 'ane@example.test']);
        // roles relation not loaded

        $reflection = new \ReflectionMethod($transformer, 'roleIds');
        $reflection->setAccessible(true);

        $this->assertSame([], $reflection->invoke($transformer, $account));
    }

    public function test_role_ids_returns_ids_when_roles_loaded(): void
    {
        $transformer = new UserListTransformer;

        $role1 = new Role(['id' => 1, 'name' => 'Admin', 'slug' => 'admin']);
        $role2 = new Role(['id' => 2, 'name' => 'Editor', 'slug' => 'editor']);
        $role1->setAttribute('id', 1);
        $role2->setAttribute('id', 2);
        $account = new User(['id' => 1, 'name' => 'Ane', 'email' => 'ane@example.test']);
        $account->setRelation('roles', new Collection([$role1, $role2]));

        $reflection = new \ReflectionMethod($transformer, 'roleIds');
        $reflection->setAccessible(true);

        $this->assertEqualsCanonicalizing(['1', '2'], $reflection->invoke($transformer, $account));
    }

    public function test_resource_label_returns_users(): void
    {
        $transformer = new UserListTransformer;

        $reflection = new \ReflectionMethod($transformer, 'resourceLabel');
        $reflection->setAccessible(true);

        $this->assertSame(__('Users'), $reflection->invoke($transformer));
    }

    public function test_resource_key_returns_user(): void
    {
        $transformer = new UserListTransformer;

        $reflection = new \ReflectionMethod($transformer, 'resourceKey');
        $reflection->setAccessible(true);

        $this->assertSame('user', $reflection->invoke($transformer));
    }

    public function test_active_list_shows_archive_action_when_delete_blocked(): void
    {
        // Subclass that blocks delete and shows archive instead
        $transformer = new ArchiveOnDeleteBlockUserListTransformer;
        $account = new User(['name' => 'Ane Bezeroa', 'email' => 'ane@example.test']);
        $account->id = 7;

        /** @var LengthAwarePaginator<int, Model> $records */
        $records = new LengthAwarePaginator([$account], 1, 15);

        /** @var array{rows: list<array{actions: list<array<string, mixed>>}>} $list */
        $list = $transformer->active($records, '');

        $actions = $list['rows'][0]['actions'];

        // First action should be edit (form-modal), second should be archive (not delete)
        $this->assertSame('form-modal', $actions[0]['type']);
        $this->assertSame('confirm-modal', $actions[1]['type']);
        $this->assertSame(__('basics13::messages.archive'), $actions[1]['label']);
        $this->assertSame(__('basics13::messages.archive_instead_of_delete'), $actions[1]['hint']);
    }
}

final class ForceDeleteBlockingUserListTransformer extends UserListTransformer
{
    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return true;
    }
}

final class ArchiveOnDeleteBlockUserListTransformer extends UserListTransformer
{
    protected function getDeleteBlockCondition(Model $record): bool
    {
        return true;
    }
}
