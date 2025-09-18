<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'visitor_ip',
        'user_agent',
        'page_type',
        'page_url',
        'page_path',
        'page_name',
        'page_params',
        'query_params',
        'resource_id', // ID universal del recurso visitado
        'referrer_url',
        'referrer_domain',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'device_type',
        'browser',
        'browser_version',
        'os',
        'os_version',
        'screen_resolution',
        'country',
        'region',
        'city',
        'timezone',
        'time_on_page',
        'is_bounce',
        'scroll_depth',
        'is_returning_visitor',
        'language',
        'is_mobile',
        'is_bot',
        'additional_data',
    ];

    protected $casts = [
        'page_params' => 'array',
        'query_params' => 'array',
        'additional_data' => 'array',
        'is_bounce' => 'boolean',
        'is_returning_visitor' => 'boolean',
        'is_mobile' => 'boolean',
        'is_bot' => 'boolean',
        'time_on_page' => 'integer',
        'scroll_depth' => 'integer',
    ];

    /**
     * Relaciones
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scopes
     */
    public function scopeByPageType($query, string $pageType)
    {
        return $query->where('page_type', $pageType);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    /**
     * Métodos estáticos para analytics
     */
    public static function trackView(array $data): self
    {
        // Evitar duplicados en la misma sesión y página en un corto periodo
        $recentView = self::where('session_id', $data['session_id'])
                         ->where('page_url', $data['page_url'])
                         ->where('created_at', '>', now()->subMinutes(5))
                         ->first();

        if ($recentView) {
            return $recentView;
        }

        return self::create($data);
    }

    public static function getPopularPages(string $pageType, int $days = 7)
    {
        return self::byPageType($pageType)
                  ->where('created_at', '>=', now()->subDays($days))
                  ->selectRaw('page_url, COUNT(*) as views_count')
                  ->groupBy('page_url')
                  ->orderByDesc('views_count')
                  ->limit(10)
                  ->get();
    }
}
