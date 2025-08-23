<?php
// app/Models/ContentCalendar.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContentCalendar extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'content_calendars';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'calendar_date',
        'time_slot',
        'platforms',
        'content_type',
        'status',
        'social_media_post_id',
        'campaign_id',
        'tags',
        'notes',
        'reminders',
        'recurring',
    ];

    protected $casts = [
        'calendar_date' => 'date',
    ];

    protected $attributes = [
        'status' => 'planned',
        'content_type' => 'post',
        'platforms' => [],
        'tags' => [],
        'reminders' => [
            'one_day_before' => false,
            'one_hour_before' => false,
            'at_time' => true,
        ],
        'recurring' => [
            'enabled' => false,
            'frequency' => 'weekly',
            'end_date' => null,
        ],
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function socialMediaPost()
    {
        return $this->belongsTo(SocialMediaPost::class);
    }

    /**
     * Scopes
     */
    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('calendar_date', $date);
    }

    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('calendar_date', $year)
                    ->whereMonth('calendar_date', $month);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('calendar_date', '>=', now()->toDateString())
                    ->orderBy('calendar_date')
                    ->orderBy('time_slot');
    }
}