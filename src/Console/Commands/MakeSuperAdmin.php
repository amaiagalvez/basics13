<?php

namespace Basics13\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Basics13\Concerns\ResolvesUserModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Basics13\Concerns\ProfileValidationRules;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Creates the one account that holds every permission. It is the only way to set the flag: it is
 * not fillable and no form sends it. The answers are asked for, never read from the environment, so
 * the password does not sit in a file or in the shell history.
 */
class MakeSuperAdmin extends Command
{
    use ProfileValidationRules;
    use ResolvesUserModel;

    protected $signature = 'basics13:make-super-admin';

    protected $description = 'Create the super-admin account, which holds every permission';

    public function handle(): int
    {
        if ($this->superAdminExists()) {
            $this->components->error(__('A super-admin account already exists.'));

            return self::FAILURE;
        }

        $input = [
            'name' => trim((string) $this->ask(__('Name'))),
            'email' => trim((string) $this->ask(__('Email'))),
            'password' => (string) $this->secret(__('Password')),
        ];

        $validator = Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($input): bool {
            // Checked again inside the transaction: another run may have finished meanwhile.
            if ($this->superAdminExists()) {
                return false;
            }

            $modelClass = $this->userModelClass();
            $user = new $modelClass;
            $user->fill($input);
            $user->forceFill([
                'is_super_admin' => true,
                'active' => true,
                'email_verified_at' => now(),
            ]);
            $user->save();

            return true;
        });

        if (! $created) {
            $this->components->error(__('A super-admin account already exists.'));

            return self::FAILURE;
        }

        $this->components->info(__('Super-admin account created.'));

        return self::SUCCESS;
    }

    /**
     * Trashed accounts count too: restoring one must not leave two super-admins.
     */
    private function superAdminExists(): bool
    {
        $modelClass = $this->userModelClass();
        $query = $modelClass::query()->where('is_super_admin', true);

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            /** @phpstan-ignore method.notFound */
            $query->withTrashed();
        }

        return $query->lockForUpdate()->exists();
    }
}
