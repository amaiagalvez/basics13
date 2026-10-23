<?php

namespace Basics13\Tests\Unit\Policies;

use Basics13\Models\Setting;
use Basics13\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Basics13\Policies\SettingPolicy;
use Basics13\Support\Testing\PermissionsActor;
use Illuminate\Contracts\Auth\Authenticatable;

class SettingPolicyTest extends TestCase
{
    public function test_listing_needs_settings_view(): void
    {
        $policy = new SettingPolicy;

        $this->assertTrue($policy->viewAny($this->actor(['settings.view'])));
        $this->assertFalse($policy->viewAny($this->actor([])));
        $this->assertFalse($policy->viewAny($this->actor(['settings.edit'])));
    }

    public function test_editing_needs_settings_edit(): void
    {
        $policy = new SettingPolicy;

        $this->assertTrue($policy->update($this->actor(['settings.edit']), new Setting));
        $this->assertFalse($policy->update($this->actor([]), new Setting));
        $this->assertFalse($policy->update($this->actor(['settings.view']), new Setting));
    }

    public function test_settings_manage_grants_both(): void
    {
        $policy = new SettingPolicy;
        $manager = $this->actor(['settings.manage']);

        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->update($manager, new Setting));
    }

    public function test_an_actor_without_the_roles_concern_holds_nothing(): void
    {
        $policy = new SettingPolicy;

        $this->assertFalse($policy->viewAny(new GenericUser(['id' => 1])));
        $this->assertFalse($policy->update(new GenericUser(['id' => 1]), new Setting));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function actor(array $permissions): Authenticatable
    {
        return PermissionsActor::holding($permissions);
    }
}
