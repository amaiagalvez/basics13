<?php

namespace Basics13\Tests\Unit;

use ReflectionClass;
use Basics13\Models\User;
use Basics13\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

class ArchitectureTest extends TestCase
{
    public function test_user_accounts_are_soft_deletable_without_a_resource_policy(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses_recursive(User::class));
        $this->assertSame([], (new ReflectionClass(User::class))->getAttributes(UsePolicy::class));
    }

    public function test_raw_blade_echo_is_limited_to_the_two_factor_qr_code(): void
    {
        $rawEchoesByFile = [];
        $packageViewsPath = dirname(__DIR__, 2).'/resources/views';

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
