<?php

namespace Basics13\Models;

use Carbon\CarbonImmutable;
use Basics13\Concerns\HasDefaultRow;
use Illuminate\Database\Eloquent\Model;
use Basics13\Concerns\TracksAuditColumns;
use Basics13\Concerns\TransactionalDelete;
use Basics13\Policies\EmailTemplatePolicy;
use Illuminate\Database\Eloquent\SoftDeletes;
use Basics13\Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * An editable email: subject and body in the four languages, with English as the one every other
 * language falls back to, plus the banner and legal text it is wrapped with.
 *
 * @property int $id
 * @property string $name
 * @property string|null $banner
 * @property string|null $legal_text
 * @property string|null $subject_eu
 * @property string|null $subject_es
 * @property string|null $subject_fr
 * @property string $subject_en
 * @property string|null $body_eu
 * @property string|null $body_es
 * @property string|null $body_fr
 * @property string $body_en
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
#[Fillable([
    'name', 'banner', 'legal_text',
    'subject_eu', 'subject_es', 'subject_fr', 'subject_en',
    'body_eu', 'body_es', 'body_fr', 'body_en',
    'notes',
])]
#[UsePolicy(EmailTemplatePolicy::class)]
#[UseFactory(EmailTemplateFactory::class)]
class EmailTemplate extends Model
{
    /** @use HasFactory<EmailTemplateFactory> */
    use HasDefaultRow, HasFactory, SoftDeletes, TracksAuditColumns, TransactionalDelete;

    /**
     * The languages a template is written in; the last one is the fallback.
     *
     * @var list<string>
     */
    public const LOCALES = ['eu', 'es', 'fr', 'en'];

    public const FALLBACK_LOCALE = 'en';

    protected $table = 'BAS_email_templates';

    /**
     * @var array{active: bool, default: bool}
     */
    protected $attributes = [
        'active' => true,
        'default' => false,
    ];

    /**
     * @return array{active: 'boolean', default: 'boolean'}
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'default' => 'boolean',
        ];
    }
}
