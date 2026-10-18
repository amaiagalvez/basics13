<?php

namespace Basics13\Tests\Unit\Transformers;

use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
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
        $this->assertSame('', $blocked['hint']);
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
}

final class ForceDeleteBlockingUserListTransformer extends UserListTransformer
{
    protected function getForceDeleteBlockCondition(Model $record): bool
    {
        return true;
    }
}
