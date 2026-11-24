<?php

/*
 * This file is part of fof/subscribed.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Subscribed\Support;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class NotificationPreferences
{
    /**
     * SQL REGEXP is not available on every supported database (notably SQLite).
     * LIKE only narrows the candidates; decoded JSON determines eligibility.
     * Match explicitly saved preferences, just as the previous SQL query did,
     * without restricting notifications to the built-in alert/email drivers.
     *
     * @param Builder<User> $query
     * @return Collection<int, User>
     */
    public static function recipients(Builder $query, string $type): Collection
    {
        return $query
            ->where('preferences', 'like', '%"notify_'.$type.'_%')
            ->get()
            ->filter(fn (User $user) => self::hasEnabledDriver((string) $user->getRawOriginal('preferences'), $type));
    }

    public static function hasEnabledDriver(string $json, string $type): bool
    {
        $preferences = json_decode($json, true);

        if (!is_array($preferences)) {
            return false;
        }

        $pattern = '/^notify_'.preg_quote($type, '/').'_[a-z]+$/D';

        foreach ($preferences as $key => $enabled) {
            if ($enabled === true && preg_match($pattern, (string) $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
