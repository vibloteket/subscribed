<?php

namespace FoF\Subscribed\Tests\Integration\Api;

use Flarum\Discussion\Discussion;
use Flarum\Flags\Flag;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\Post;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\Subscribed\Jobs;
use PHPUnit\Framework\Attributes\DataProvider;

class NotificationJobsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('flarum-flags', 'flarum-approval', 'fof-subscribed');
        $preferences = [];
        foreach (['discussionCreated', 'postCreated', 'postFlagged', 'postUnapproved', 'userCreated'] as $type) {
            $preferences['notify_'.$type.'_email'] = true;
        }
        $this->prepareDatabase([
            'users' => [
                ['id' => 2, 'username' => 'author', 'email' => 'author@example.com',
                    'password' => 'unused', 'is_email_confirmed' => 1, 'preferences' => json_encode($preferences)],
                // Admin receives all types, but is never the initiating user.
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'A discussion', 'slug' => 'a-discussion', 'user_id' => 2, 'created_at' => '2026-01-01 00:00:00',
                    'first_post_id' => 1, 'last_post_id' => 2, 'last_post_number' => 2, 'comment_count' => 2],
            ],
            'posts' => [
                ['id' => 1, 'number' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment',
                    'created_at' => '2026-01-01 00:00:00', 'content' => '<t>First post</t>', 'is_approved' => 1],
                ['id' => 2, 'number' => 2, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment',
                    'created_at' => '2026-01-01 00:01:00', 'content' => '<t>Reply</t>', 'is_approved' => 1],
            ],
            'flags' => [
                ['id' => 1, 'post_id' => 2, 'user_id' => 2, 'type' => 'user', 'created_at' => '2026-01-01 00:02:00'],
            ],
        ]);
    }

    public static function types(): array
    {
        return array_map(fn ($type) => [$type], ['discussionCreated', 'postCreated', 'postFlagged', 'postUnapproved', 'userCreated']);
    }

    #[DataProvider('types')]
    public function test_each_job_selects_recipients_on_the_database(string $type): void
    {
        $this->app();
        // Update persisted raw JSON, including whitespace, without depending on
        // registration/defaults for the selected notification type.
        $this->database()->table('users')->where('id', 1)->update([
            'preferences' => '{ "notify_'.$type.'_email" : true }',
        ]);
        $job = match ($type) {
            'discussionCreated' => new Jobs\SendNotificationWhenDiscussionIsStarted(Discussion::findOrFail(1)),
            'postCreated' => new Jobs\SendNotificationWhenPostIsCreated(Post::findOrFail(2)),
            'postFlagged' => new Jobs\SendNotificationWhenPostIsFlagged(Flag::findOrFail(1)),
            'postUnapproved' => new Jobs\SendNotificationWhenPostIsUnapproved(Post::findOrFail(2)),
            'userCreated' => new Jobs\SendNotificationWhenUserIsCreated(User::findOrFail(2)),
        };
        $syncer = $this->createMock(NotificationSyncer::class);
        $syncer->expects($this->once())->method('sync')->with(
            $this->callback(fn ($blueprint) => $blueprint->getType() === $type),
            $this->callback(fn ($users) => array_map(fn ($user) => $user->id, array_values($users)) === [1])
        );
        $job->handle($syncer);
    }
}
