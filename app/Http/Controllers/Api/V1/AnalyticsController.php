<?php
// app/Http/Controllers/Api/V1/AnalyticsController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PostAnalytics;
use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get user's analytics overview
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $period = $request->get('period', '30'); // days
            $startDate = now()->subDays($period);

            // Posts analytics
            $postsQuery = $user->posts()->where('created_at', '>=', $startDate);
            $analyticsQuery = $user->analytics()->where('collected_at', '>=', $startDate);

            $overview = [
                'period' => [
                    'days' => $period,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => now()->toDateString()
                ],
                'posts_stats' => [
                    'total_posts' => $postsQuery->count(),
                    'published_posts' => $postsQuery->where('post_status', 'published')->count(),
                    'draft_posts' => $postsQuery->where('post_status', 'draft')->count(),
                    'scheduled_posts' => $postsQuery->where('post_status', 'scheduled')->count(),
                ],
                'engagement_stats' => [
                    'total_impressions' => $analyticsQuery->sum('metrics.impressions'),
                    'total_likes' => $analyticsQuery->sum('metrics.likes'),
                    'total_shares' => $analyticsQuery->sum('metrics.shares'),
                    'total_comments' => $analyticsQuery->sum('metrics.comments'),
                    'total_clicks' => $analyticsQuery->sum('metrics.clicks'),
                    'average_engagement_rate' => round($analyticsQuery->avg('metrics.engagement_rate'), 2),
                ],
                'platform_breakdown' => $this->getPlatformBreakdown($user, $startDate),
                'top_performing_posts' => $this->getTopPerformingPosts($user, $startDate),
                'growth_metrics' => $this->getGrowthMetrics($user, $period)
            ];

            return response()->json([
                'status' => 'success',
                'data' => $overview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve analytics overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed post analytics
     */
    public function postAnalytics(Request $request, string $postId): JsonResponse
    {
        try {
            $user = $request->user();;
            $post = SocialMediaPost::where('user_id', $user->_id)->findOrFail($postId);

            $analytics = PostAnalytics::where('social_media_post_id', $postId)
                ->orderBy('collected_at', 'desc')
                ->get();

            if ($analytics->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'post' => $post,
                        'analytics' => [],
                        'message' => 'No analytics data available yet'
                    ]
                ]);
            }

            $summary = [
                'total_impressions' => $analytics->sum('metrics.impressions'),
                'total_engagement' => $analytics->sum(function ($item) {
                    $metrics = $item->metrics ?? [];
                    return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
                }),
                'average_engagement_rate' => round($analytics->avg('metrics.engagement_rate'), 2),
                'best_performing_platform' => $analytics->sortByDesc('performance_score')->first()?->platform,
                'peak_engagement_time' => $analytics->sortByDesc('metrics.engagement_rate')->first()?->collected_at,
                'platforms_data' => $analytics->groupBy('platform')->map(function ($platformAnalytics) {
                    return [
                        'total_impressions' => $platformAnalytics->sum('metrics.impressions'),
                        'total_engagement' => $platformAnalytics->sum(function ($item) {
                            $metrics = $item->metrics ?? [];
                            return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
                        }),
                        'avg_engagement_rate' => round($platformAnalytics->avg('metrics.engagement_rate'), 2),
                        'performance_score' => round($platformAnalytics->avg('performance_score'), 2)
                    ];
                })
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'post' => $post,
                    'analytics' => $analytics,
                    'summary' => $summary,
                    'timeline' => $analytics->map(function ($item) {
                        return [
                            'date' => $item->collected_at,
                            'platform' => $item->platform,
                            'engagement_rate' => $item->metrics['engagement_rate'] ?? 0,
                            'impressions' => $item->metrics['impressions'] ?? 0
                        ];
                    })->sortBy('date')->values()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve post analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get platform performance comparison
     */
    public function platformComparison(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $period = $request->get('period', '30');
            $startDate = now()->subDays($period);

            $analytics = $user->analytics()
                ->where('collected_at', '>=', $startDate)
                ->get()
                ->groupBy('platform');

            $comparison = $analytics->map(function ($platformData, $platform) {
                $totalPosts = $platformData->count();
                $totalImpressions = $platformData->sum('metrics.impressions');
                $totalEngagement = $platformData->sum(function ($item) {
                    $metrics = $item->metrics ?? [];
                    return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
                });

                return [
                    'platform' => $platform,
                    'posts_count' => $totalPosts,
                    'total_impressions' => $totalImpressions,
                    'total_engagement' => $totalEngagement,
                    'avg_engagement_rate' => round($platformData->avg('metrics.engagement_rate'), 2),
                    'avg_performance_score' => round($platformData->avg('performance_score'), 2),
                    'engagement_per_post' => $totalPosts > 0 ? round($totalEngagement / $totalPosts, 2) : 0,
                    'impressions_per_post' => $totalPosts > 0 ? round($totalImpressions / $totalPosts, 2) : 0,
                ];
            })->sortByDesc('avg_performance_score')->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period' => [
                        'days' => $period,
                        'start_date' => $startDate->toDateString(),
                        'end_date' => now()->toDateString()
                    ],
                    'platforms' => $comparison,
                    'summary' => [
                        'best_performing_platform' => $comparison->first()['platform'] ?? null,
                        'total_platforms_analyzed' => $comparison->count(),
                        'total_posts_analyzed' => $comparison->sum('posts_count')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve platform comparison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get engagement timeline
     */
    public function engagementTimeline(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $period = $request->get('period', '30');
            $startDate = now()->subDays($period);

            $analytics = $user->analytics()
                ->where('collected_at', '>=', $startDate)
                ->orderBy('collected_at')
                ->get();

            // Group by date for timeline
            $timeline = $analytics->groupBy(function ($item) {
                return $item->collected_at->toDateString();
            })->map(function ($dailyData, $date) {
                $totalImpressions = $dailyData->sum('metrics.impressions');
                $totalEngagement = $dailyData->sum(function ($item) {
                    $metrics = $item->metrics ?? [];
                    return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
                });

                return [
                    'date' => $date,
                    'posts_count' => $dailyData->count(),
                    'total_impressions' => $totalImpressions,
                    'total_engagement' => $totalEngagement,
                    'avg_engagement_rate' => round($dailyData->avg('metrics.engagement_rate'), 2),
                    'platforms' => $dailyData->pluck('platform')->unique()->values()
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'timeline' => $timeline,
                    'summary' => [
                        'total_days' => $timeline->count(),
                        'avg_daily_posts' => round($timeline->avg('posts_count'), 2),
                        'avg_daily_engagement' => round($timeline->avg('total_engagement'), 2),
                        'peak_engagement_date' => $timeline->sortByDesc('total_engagement')->first()['date'] ?? null
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve engagement timeline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate analytics report
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'platforms' => 'array',
                'platforms.*' => 'string|in:twitter,facebook,instagram,linkedin,youtube,tiktok',
                'metrics' => 'array',
                'metrics.*' => 'string|in:impressions,engagement,reach,clicks,shares'
            ]);

            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $platforms = $validated['platforms'] ?? null;

            $query = $user->analytics()
                ->whereBetween('collected_at', [$startDate, $endDate]);

            if ($platforms) {
                $query->whereIn('platform', $platforms);
            }

            $analytics = $query->get();

            $report = [
                'report_info' => [
                    'generated_at' => now()->toISOString(),
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'total_days' => now()->parse($startDate)->diffInDays($endDate)
                    ],
                    'filters' => [
                        'platforms' => $platforms,
                        'metrics' => $validated['metrics'] ?? ['all']
                    ]
                ],
                'summary' => [
                    'total_posts_analyzed' => $analytics->count(),
                    'platforms_analyzed' => $analytics->pluck('platform')->unique()->count(),
                    'total_impressions' => $analytics->sum('metrics.impressions'),
                    'total_engagement' => $analytics->sum(function ($item) {
                        $metrics = $item->metrics ?? [];
                        return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
                    }),
                    'average_engagement_rate' => round($analytics->avg('metrics.engagement_rate'), 2),
                    'best_performing_platform' => $analytics->sortByDesc('performance_score')->first()?->platform
                ],
                'platform_breakdown' => $analytics->groupBy('platform')->map(function ($platformData, $platform) {
                    return [
                        'platform' => $platform,
                        'posts_count' => $platformData->count(),
                        'total_impressions' => $platformData->sum('metrics.impressions'),
                        'total_engagement' => $platformData->sum(function ($item) {
                            $metrics = $item->metrics ?? [];
                            return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
                        }),
                        'avg_performance_score' => round($platformData->avg('performance_score'), 2)
                    ];
                })->values(),
                'top_posts' => $analytics->sortByDesc('performance_score')->take(10)->map(function ($item) {
                    return [
                        'post_id' => $item->social_media_post_id,
                        'platform' => $item->platform,
                        'performance_score' => $item->performance_score,
                        'engagement_rate' => $item->metrics['engagement_rate'] ?? 0,
                        'collected_at' => $item->collected_at
                    ];
                })->values()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function getPlatformBreakdown(User $user, $startDate)
    {
        return $user->analytics()
            ->where('collected_at', '>=', $startDate)
            ->get()
            ->groupBy('platform')
            ->map(function ($platformData) {
                return [
                    'posts_count' => $platformData->count(),
                    'total_impressions' => $platformData->sum('metrics.impressions'),
                    'avg_engagement_rate' => round($platformData->avg('metrics.engagement_rate'), 2)
                ];
            });
    }

    private function getTopPerformingPosts(User $user, $startDate)
    {
        return $user->analytics()
            ->where('collected_at', '>=', $startDate)
            ->orderBy('performance_score', 'desc')
            ->limit(5)
            ->with('socialMediaPost')
            ->get()
            ->map(function ($analytics) {
                return [
                    'post_id' => $analytics->social_media_post_id,
                    'platform' => $analytics->platform,
                    'performance_score' => $analytics->performance_score,
                    'engagement_rate' => $analytics->metrics['engagement_rate'] ?? 0,
                    'post_preview' => substr($analytics->socialMediaPost->content['text'] ?? '', 0, 100)
                ];
            });
    }

    private function getGrowthMetrics(User $user, $period)
    {
        $currentPeriod = $user->analytics()
            ->where('collected_at', '>=', now()->subDays($period))
            ->get();

        $previousPeriod = $user->analytics()
            ->whereBetween('collected_at', [now()->subDays($period * 2), now()->subDays($period)])
            ->get();

        $currentEngagement = $currentPeriod->sum(function ($item) {
            $metrics = $item->metrics ?? [];
            return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
        });

        $previousEngagement = $previousPeriod->sum(function ($item) {
            $metrics = $item->metrics ?? [];
            return ($metrics['likes'] ?? 0) + ($metrics['shares'] ?? 0) + ($metrics['comments'] ?? 0);
        });

        $growthRate = $previousEngagement > 0
            ? round((($currentEngagement - $previousEngagement) / $previousEngagement) * 100, 2)
            : 0;

        return [
            'engagement_growth_rate' => $growthRate,
            'current_period_engagement' => $currentEngagement,
            'previous_period_engagement' => $previousEngagement,
            'trend' => $growthRate > 0 ? 'up' : ($growthRate < 0 ? 'down' : 'stable')
        ];
    }
}
