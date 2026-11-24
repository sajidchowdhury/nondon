<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FiscalYear extends Model
{
    use HasFactory;

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

    /**
     * Scope: Only active fiscal year.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_closed', false);
    }

    /**
     * Generate the next fiscal-year serial number.
     *
     * Format: YY-NNNNN (e.g., 25-00001, 25-00002, ..., 25-01209)
     * The serial is a global sequence per fiscal year, not per-product.
     * Each physical garment receives the next number regardless of design.
     *
     * @return string The formatted serial (e.g., "25-00001")
     */
    public function generateNextSerial(): string
    {
        $next = $this->current_serial_sequence + 1;

        return $this->serial_prefix . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
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

    /**
     * Check if a date falls within this fiscal year.
     */
    public function containsDate(\DateTimeInterface $date): bool
    {
        $date = \Carbon\Carbon::instance($date);
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get the current active fiscal year.
     *
     * @return self|null
     */
    public static function current(): ?self
    {
        return static::active()->first();
    }
}
