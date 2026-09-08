<?php

namespace App\Support;

use App\Models\BitrixToken;
use Illuminate\Http\Request;

/**
 * Holds the currently active Bitrix24 portal (member_id) for the lifespan of a
 * web request or a queued job. All tenant-scoped models filter by this value.
 */
class TenantContext
{
    protected static ?string $memberId = null;

    public static function set(?string $memberId): void
    {
        static::$memberId = $memberId ?: null;
    }

    public static function memberId(): ?string
    {
        return static::$memberId;
    }

    public static function clear(): void
    {
        static::$memberId = null;
    }

    /**
     * Resolve the tenant from a request: prefer an explicit member_id posted by
     * Bitrix24, then the session, then fall back to the single configured token.
     */
    public static function resolveFromRequest(Request $request): ?string
    {
        $member = (string) ($request->input('member_id') ?: $request->query('member_id', ''));
        $member = trim($member);

        if ($member === '') {
            $member = (string) $request->session()->get('bitrix_member_id', '');
            $member = trim($member);
        }

        if ($member === '') {
            $member = (string) self::firstConfiguredMemberId();
        }

        if ($member !== '') {
            static::set($member);
        }

        return static::memberId();
    }

    public static function firstConfiguredMemberId(): string
    {
        return (string) BitrixToken::query()->orderBy('id')->value('member_id');
    }
}
