<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Competition extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'ticket_numbers' => 'array',
        'sold_tickets' => 'array',
        'has_instant_prizes' => 'boolean',
        'on_homepage' => 'boolean',
    ];

    /**
     * Check if the competition has enough tickets available.
     *
     * @param int $quantity
     * @return bool
     */
    public function hasEnoughTickets(int $quantity): bool
    {
        $totalTickets = $this->number_of_entries ?? 0;
        $soldTickets = $this->tickets()->where('status', 'sold')->count();
        $remainingTickets = $totalTickets - $soldTickets;

        return $remainingTickets >= $quantity;
    }

    /**
     * Get the specified number of tickets from the available ticket numbers.
     *
     * @param int $quantity
     * @return array
     */
    public function getTickets(int $quantity): array
    {
        $ticketNumbers = $this->ticket_numbers ?? [];

        if (count($ticketNumbers) < $quantity) {
            return [];
        }

        return array_slice($ticketNumbers, 0, $quantity);
    }

    /**
     * Assign tickets and move them to the sold_tickets list.
     *
     * @param int $quantity
     * @return array|null
     */
    public function assignTickets(int $quantity): ?array
    {
        if (!$this->hasEnoughTickets($quantity)) {
            Log::error("Not enough tickets available", [
                'competition_id' => $this->id,
                'requested' => $quantity,
                'available' => count($this->ticket_numbers ?? [])
            ]);
            return null;
        }

        $ticketNumbers = $this->ticket_numbers ?? [];
        $soldTickets = $this->sold_tickets ?? [];

        $assignedTickets = array_slice($ticketNumbers, 0, $quantity);
        $remainingTickets = array_slice($ticketNumbers, $quantity);
        $newSoldTickets = array_merge($soldTickets, $assignedTickets);

        $this->ticket_numbers = $remainingTickets;
        $this->sold_tickets = $newSoldTickets;

        return $assignedTickets;
    }

    /**
     * Determine if the competition is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'started' && $this->end_date->isFuture();
    }

    /**
     * Scope to get only active competitions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'started')->where('end_date', '>', now());
    }

    /**
     * Get the category associated with the competition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get images for the competition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany(CompetitionImage::class)->orderBy('order', 'asc');
    }

    /**
     * Get questions related to the competition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questions()
    {
        return $this->hasMany(CompetitionQuestion::class);
    }

    /**
     * Get instant prizes for the competition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function instantPrizes()
    {
        return $this->hasMany(InstantPrize::class)->with('instantTickets');
    }

    /**
     * Get order items related to the competition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all individual tickets associated with the competition.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
