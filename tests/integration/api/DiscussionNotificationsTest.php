<?php

namespace FoF\Subscribed\Tests\Integration\Api;

use Flarum\Extend;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use Flarum\Testing\integration\TestCase;
use Illuminate\Mail\Events\MessageSent;

class DiscussionNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-subscribed');
        $this->prepareDatabase([
            'settings' => [
                ['key' => 'mail_driver', 'value' => 'null'],
                ['key' => 'mail_from', 'value' => 'forum@example.com'],
            ],
            'groups' => [
                ['id' => 5, 'name_singular' => 'Subscriber', 'name_plural' => 'Subscribers'],
            ],
            'group_permission' => [
                ['group_id' => 5, 'permission' => 'subscribeDiscussionCreated'],
                ['group_id' => 5, 'permission' => 'subscribePostCreated'],
            ],
            'group_user' => [
                ['group_id' => 5, 'user_id' => 2],
                ['group_id' => 5, 'user_id' => 3],
                ['group_id' => 5, 'user_id' => 4],
            ],
            'users' => [
                $this->user(2, 'author', true),
                $this->user(3, 'recipient', true),
                $this->user(4, 'disabled', false),
                $this->user(5, 'nopermission', true),
            ],
        ]);
    }

    private function user(int $id, string $name, bool $enabled): array
    {
        return [
            'id' => $id, 'username' => $name, 'email' => $name.'@example.com',
            'is_email_confirmed' => 1, 'password' => 'unused-test-password',
            'preferences' => json_encode([
                'notify_discussionCreated_email' => $enabled,
                'notify_discussionCreated_alert' => $enabled,
                'notify_postCreated_email' => $enabled,
                'notify_postCreated_alert' => $enabled,
            ]),
        ];
    }

    public static function channels(): array
    {
        return [
            'both channels' => [true, true, true],
            'email only' => [true, false, true],
            'alert only' => [false, true, true],
            'both disabled' => [false, false, true],
            'invisible to recipient' => [true, true, false],
        ];
    }

    #[DataProvider('channels')]
    public function test_posting_discussion_and_reply_delivers_alerts_and_rendered_mail(bool $email, bool $alert, bool $visible): void
    {
        if (!$visible) {
            // Simulate an access restriction imposed by another extension.
            foreach ([Discussion::class, Post::class] as $model) {
                $this->extend((new Extend\ModelVisibility($model))->scope(function (User $actor, Builder $query) {
                    if ($actor->id === 3) {
                        $query->whereRaw('1 = 0');
                    }
                }));
            }
        }

        $this->database()->table('users')->where('id', 3)->update(['preferences' => json_encode([
            'notify_discussionCreated_email' => $email,
            'notify_discussionCreated_alert' => $alert,
            'notify_postCreated_email' => $email,
            'notify_postCreated_alert' => $alert,
        ])]);
        // Real mail rendering and sending pipeline, with a null transport: no
        // network or external recipients. MessageSent fires after transport send.
        $mail = [];
        $this->app()->getContainer()->make('events')->listen(MessageSent::class, function (MessageSent $event) use (&$mail) {
            $mail[] = $event->message;
        });

        $response = $this->send($this->request('POST', '/api/discussions', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'discussions', 'attributes' => [
                'title' => 'Notification regression', 'content' => 'First post',
            ]]],
        ])->withAttribute('bypassThrottling', true));
        $body = (string) $response->getBody();
        $this->assertSame(201, $response->getStatusCode(), $body);
        $discussionId = json_decode($body, true)['data']['id'];
        $this->assertDelivery('discussionCreated', $mail, 1, $email && $visible, $alert && $visible);

        $response = $this->send($this->request('POST', '/api/posts', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'posts', 'attributes' => ['content' => 'A reply'],
                'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => $discussionId]]],
            ]],
        ])->withAttribute('bypassThrottling', true));
        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertDelivery('postCreated', $mail, 2, $email && $visible, $alert && $visible);
    }

    private function assertDelivery(string $type, array $mail, int $expectedCount, bool $email, bool $alert): void
    {
        $this->assertSame(($email || $alert) ? [3] : [], $this->database()->table('notifications')->where('type', $type)->pluck('user_id')->all());
        $this->assertCount($email ? $expectedCount : 0, $mail);
        if (!$email) {
            return;
        }
        $message = $mail[$expectedCount - 1];
        $this->assertSame('recipient@example.com', $message->getTo()[0]->getAddress());
        $this->assertNotEmpty($message->getSubject());
        $this->assertNotEmpty($message->getTextBody());
        $this->assertNotEmpty($message->getHtmlBody());
    }
}
