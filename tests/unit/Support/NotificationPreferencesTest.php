<?php

namespace FoF\Subscribed\Tests\Unit\Support;

use Flarum\Testing\unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use FoF\Subscribed\Support\NotificationPreferences;

class NotificationPreferencesTest extends TestCase
{
    #[DataProvider('preferences')]
    public function test_explicit_preferences(string $json, string $type, bool $expected): void
    {
        $this->assertSame($expected, NotificationPreferences::hasEnabledDriver($json, $type));
    }

    public static function preferences(): array
    {
        return [
            ['{"notify_postCreated_email":true}', 'postCreated', true],
            ['{ "notify_postCreated_email" : true }', 'postCreated', true],
            ['{"notify_postCreated_alert":true,"notify_postCreated_email":false}', 'postCreated', true],
            ['{"notify_postCreated_push":true}', 'postCreated', true],
            ['{"notify_postCreated_email":false}', 'postCreated', false],
            ['{"notify_postCreated_email":"true"}', 'postCreated', false],
            ['{"notify_postCreated_email":1}', 'postCreated', false],
            ['{"notify_postCreated_email_extra":true}', 'postCreated', false],
            ['{"notify_postCreated_EMAIL":true}', 'postCreated', false],
            ['{"notify_postCreated_email":true}', 'discussionCreated', false],
            ['{"notify_postUnapproved_alert":true}', 'postUnapproved', true],
            ['{"notify_postFlagged_alert":true}', 'postFlagged', true],
            ['{"notify_userCreated_email":true}', 'userCreated', true],
            ['{"notify_discussionCreated_email":true}', 'discussionCreated', true],
            ['{"unrelated":{"notify_postCreated_email":true}}', 'postCreated', false],
            ['{}', 'postCreated', false],
            ['null', 'postCreated', false],
            ['', 'postCreated', false],
            ['invalid JSON', 'postCreated', false],
        ];
    }
}
