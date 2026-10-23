<?php

namespace Basics13\Http\Requests;

use Basics13\Models\SessionLog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The administration dashboard covers the last 7, 30 or 90 days. The period is a selector, not a
 * form: any other value falls back to the default instead of failing validation.
 */
class SessionDashboardRequest extends FormRequest
{
    /**
     * @var list<int>
     */
    public const PERIODS = [7, 30, 90];

    public const DEFAULT_PERIOD = 7;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SessionLog::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function period(): int
    {
        $period = $this->query('period');

        return is_string($period) && in_array((int) $period, self::PERIODS, true) && (string) (int) $period === $period
            ? (int) $period
            : self::DEFAULT_PERIOD;
    }
}
