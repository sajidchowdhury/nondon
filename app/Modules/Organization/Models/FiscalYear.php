<?php

namespace App\Modules\Organization\Models;

use App\Modules\Audit\Traits\Auditable;
use Carbon\Carbon;
use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    use Auditable, HasFactory;

    protected static function newFactory()
    {
        return FiscalYearFactory::new();
    }

    /**
     * Audit configuration.
     */
    protected string $auditPrefix = 'fiscal_year';

    protected string $auditCategory = 'organization';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'serial_prefix',
        'current_serial_sequence',
        'is_active',
        'is_closed',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'is_closed' => 'boolean',
            'current_serial_sequence' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Only active fiscal year (not closed, is_active = true).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_closed', false);
    }

    /**
     * Scope: Only closed fiscal years.
     */
    public function scopeClosed($query)
    {
        return $query->where('is_closed', true);
    }

    /**
     * Scope: Order chronologically (oldest first).
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('start_date', 'asc');
    }

    /**
     * Scope: Order by most recent first.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('start_date', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | Serial Generation
    |--------------------------------------------------------------------------
    */

    /**
     * Generate the next fiscal-year serial number.
     *
     * Format: YY-NNNNN (e.g., 25-00001, 25-00002, ..., 25-01209)
     * The serial is a global sequence per fiscal year, not per-product.
     * Each physical garment receives the next number regardless of design.
     *
     * Note: This method returns the serial string but does NOT increment
     * the counter. Use FiscalYearService::generateNextSerial() for
     * concurrency-safe serial generation within a transaction.
     *
     * @return string The formatted serial (e.g., "25-00001")
     */
    public function generateNextSerial(): string
    {
        $next = $this->current_serial_sequence + 1;

        return $this->serial_prefix.'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Increment the serial sequence counter.
     * This should be called within a transaction after creating a garment.
     *
     * @return int The new sequence value
     */
    public function incrementSequence(): int
    {
        $this->increment('current_serial_sequence');

        return $this->fresh()->current_serial_sequence;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if a date falls within this fiscal year.
     */
    public function containsDate(\DateTimeInterface $date): bool
    {
        $date = Carbon::instance($date);

        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get the current active fiscal year.
     */
    public static function current(): ?self
    {
        return static::active()->first();
    }

    /**
     * Check if this fiscal year is currently active.
     */
    public function isCurrent(): bool
    {
        return $this->is_active && ! $this->is_closed;
    }

    /**
     * Get the total number of garments received in this fiscal year.
     * (Based on the serial sequence counter.)
     */
    public function getGarmentsReceivedCountAttribute(): int
    {
        return $this->current_serial_sequence;
    }

    /**
     * Get the date range as a display string.
     */
    public function getDateRangeAttribute(): string
    {
        return $this->start_date->format('M d, Y').' — '.$this->end_date->format('M d, Y');
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->is_closed) {
            return __('Closed');
        }

        if ($this->is_active) {
            return __('Active');
        }

        return __('Inactive');
    }
}
