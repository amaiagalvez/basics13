<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Basics13\Concerns\HasDefaultRow;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Basics13\Concerns\TransactionalDelete;
use Illuminate\Database\Eloquent\SoftDeletes;
use Basics13\Policies\EmailConfigurationPolicy;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Basics13\Database\Factories\EmailConfigurationFactory;

/**
 * An SMTP server the application sends through. The password is encrypted at rest and hidden from
 * every serialization; `active` and `default` are never mass assigned.
 *
 * @property int $id
 * @property string $name
 * @property string $sender_name
 * @property string $sender_email
 * @property string $host
 * @property int $port
 * @property string|null $username
 * @property string|null $password
 * @property string $encryption
 * @property bool $default
 * @property string|null $notes
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'sender_name', 'sender_email', 'host', 'port', 'username', 'password', 'encryption', 'notes'])]
#[Hidden(['password'])]
#[UsePolicy(EmailConfigurationPolicy::class)]
#[UseFactory(EmailConfigurationFactory::class)]
class EmailConfiguration extends Model
{
    /** @use HasFactory<EmailConfigurationFactory> */
    use HasDefaultRow, HasFactory, SoftDeletes, TracksAuditColumns, TransactionalDelete;

    /**
     * The encryption values the form offers: STARTTLS, implicit TLS, or none.
     *
     * @var list<string>
     */
    public const ENCRYPTIONS = ['tls', 'ssl', 'none'];

    protected $table = 'BAS_email_configurations';

    /**
     * @var array{active: bool, default: bool, encryption: string}
     */
    protected $attributes = [
        'active' => true,
        'default' => false,
        'encryption' => 'tls',
    ];

    /**
     * @return array{active: 'boolean', default: 'boolean', port: 'integer', password: 'encrypted'}
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'default' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }
}
