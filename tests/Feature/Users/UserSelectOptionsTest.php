<?php

namespace Basics13\Tests\Feature\Users;

use Basics13\Models\User;
use Basics13\Queries\ListQueryBase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserSelectOptionsTest extends UserTestCase
{
    use RefreshDatabase;

    public function test_options_list_only_active_accounts(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Zzz account']));
        User::factory()->create(['name' => 'Active account']);
        User::factory()->archived()->create(['name' => 'Archived account']);

        $this->getJson(route('users.options', ['q' => 'account']))
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.text', 'Active account')
            ->assertJsonPath('results.1.text', 'Zzz account');
    }

    public function test_options_are_capped_at_the_page_size(): void
    {
        $this->actingAs(User::factory()->create());
        User::factory()->count(ListQueryBase::PER_PAGE + 5)->create();

        $this->getJson(route('users.options'))
            ->assertOk()
            ->assertJsonCount(ListQueryBase::PER_PAGE, 'results');
    }
}
