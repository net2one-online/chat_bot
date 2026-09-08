<?php

namespace App\Http\Controllers;

use App\Models\BitrixToken;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected const RANGES = [
        'today' => ['label' => 'Hoy', 'days' => 0, 'start_of_day' => true],
        '7days' => ['label' => '7 dias', 'days' => 7],
        '15days' => ['label' => '15 dias', 'days' => 15],
        'month' => ['label' => '1 mes', 'days' => 30],
        'quarter' => ['label' => 'Trimestral', 'days' => 90],
        'semester' => ['label' => 'Semestral', 'days' => 180],
        'custom' => ['label' => 'Personalizado'],
    ];

    public function index(Request $request)
    {
        $requestedRange = (string) $request->query('range', '');
        $range = array_key_exists($requestedRange, self::RANGES) ? $requestedRange : '';

        [$from, $to] = $this->resolveDateRange($request, $range);

        $rangeFilters = fn ($query) => $query->whereBetween('created_at', [$from, $to]);

        $totalConversations = Conversation::where($rangeFilters)->count();
        $activeConversations = Conversation::where($rangeFilters)->where('status', 'active')->count();
        $transferredConversations = Conversation::where($rangeFilters)->where('status', 'transferred')->count();
        $totalMessages = Message::where($rangeFilters)->count();
        $totalBots = Bot::where($rangeFilters)->count();
        $activeBots = Bot::where($rangeFilters)->where('status', 'active')->count();

        $token = BitrixToken::first();
        $setupCompleted = $token?->setup_completed ?? true;
        $embedMode = $request->session()->get('iframe_mode') || $request->query('embed') === '1';
        $ranges = self::RANGES;

        return view('dashboard', compact(
            'totalConversations',
            'activeConversations',
            'transferredConversations',
            'totalMessages',
            'totalBots',
            'activeBots',
            'setupCompleted',
            'embedMode',
            'ranges',
            'range',
            'from',
            'to',
            'ranges',
        ));
    }

    /**
     * Resolve the from/to dates for the requested range. When no range is
     * requested (or it is invalid) the range covers all time.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveDateRange(Request $request, string $range): array
    {
        $now = CarbonImmutable::now();

        if ($range === 'custom') {
            $from = (string) $request->query('from', '');
            $to = (string) $request->query('to', '');

            $fromDate = $from !== '' ? CarbonImmutable::parse($from)->startOfDay() : $now->startOfDay();
            $toDate = $to !== '' ? CarbonImmutable::parse($to)->endOfDay() : $now->endOfDay();

            if ($fromDate->greaterThan($toDate)) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }

            return [$fromDate->toDateTimeString(), $toDate->toDateTimeString()];
        }

        if (! array_key_exists($range, self::RANGES)) {
            return [$now->subYears(5)->toDateTimeString(), $now->endOfDay()->toDateTimeString()];
        }

        $config = self::RANGES[$range];
        $days = (int) ($config['days'] ?? 0);

        if ($days === 0) {
            return [$now->startOfDay()->toDateTimeString(), $now->endOfDay()->toDateTimeString()];
        }

        return [
            $now->subDays($days - 1)->startOfDay()->toDateTimeString(),
            $now->endOfDay()->toDateTimeString(),
        ];
    }
}
