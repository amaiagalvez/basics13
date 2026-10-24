<?php

namespace Basics13\Tests\Unit;

use ReflectionClass;
use Basics13\Models\Role;
use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Basics13\Models\EmailTemplate;
use Illuminate\Support\Facades\File;
use Basics13\Models\EmailConfiguration;
use Illuminate\Database\Eloquent\SoftDeletes;
use Basics13\Tests\ChecksResourceArchitecture;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

class ArchitectureTest extends TestCase
{
    use ChecksResourceArchitecture;

    protected function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Roles and the managed email resources are canonical resources. Users are not: their policy is registered for the configured
     * model instead of declared on it.
     */
    protected function resources(): array
    {
        return [
            'Role' => Role::class,
            'EmailConfiguration' => EmailConfiguration::class,
            'EmailTemplate' => EmailTemplate::class,
        ];
    }

    public function test_user_accounts_are_soft_deletable_without_a_resource_policy(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(User::class));
        $this->assertSame([], (new ReflectionClass(User::class))->getAttributes(UsePolicy::class));
    }

    public function test_raw_blade_echo_is_limited_to_the_two_factor_qr_code(): void
    {
        $rawEchoesByFile = [];
        $packageViewsPath = $this->packageRoot().'/resources/views';

        foreach (File::allFiles($packageViewsPath) as $file) {
            $relativePath = $file->getRelativePathname();

            if (str_starts_with($relativePath, 'vendor/')) {
                continue;
            }

            preg_match_all('/\{!!.*?!!\}/s', (string) file_get_contents($file->getPathname()), $matches);

            if ($matches[0] !== []) {
                $rawEchoesByFile[$relativePath] = count($matches[0]);
            }
        }

        $this->assertSame([
            'pages/settings/⚡two-factor-setup-modal.blade.php' => 1,
        ], $rawEchoesByFile);
    }
}
