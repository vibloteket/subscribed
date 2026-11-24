<?php

/*
 * This file is part of fof/subscribed.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Subscribed\Jobs;

use Flarum\Notification\NotificationSyncer;
use Flarum\Post\Post;
use Flarum\Queue\AbstractJob;
use Flarum\User\User;
use FoF\Subscribed\Blueprints\PostCreatedBlueprint;
use FoF\Subscribed\Support\NotificationPreferences;

class SendNotificationWhenPostIsCreated extends AbstractJob
{

    public function __construct(protected Post $post)
    {
    }

    public function handle(NotificationSyncer $notifications): void
    {
        $post = $this->post;

        $notify = NotificationPreferences::recipients(
            User::query()
                ->where('users.id', '!=', $post->user_id),
            'postCreated'
        );

        $notify = $notify->filter(function (User $recipient) use ($post) {
            return $recipient->can('subscribePostCreated') && $post->isVisibleTo($recipient);
        });

        $notifications->sync(
            new PostCreatedBlueprint($post),
            $notify->all()
        );
    }
}
