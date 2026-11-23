<?php

namespace FoF\Subscribed\Tests\Integration\Api;

use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\Subscribed\Support\NotificationPreferences;

class NotificationRecipientsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-subscribed');
        $this->prepareDatabase(['users' => [
            ['id' => 3, 'username' => 'enabled', 'email' => 'enabled@example.com', 'is_email_confirmed' => 1, 'password' => 'unused-test-password',
                'preferences' => '{"notify_postCreated_email":true}'],
            ['id' => 4, 'username' => 'disabled', 'email' => 'disabled@example.com', 'is_email_confirmed' => 1, 'password' => 'unused-test-password',
                'preferences' => '{"notify_postCreated_email":false}'],
            ['id' => 5, 'username' => 'other', 'email' => 'other@example.com', 'is_email_confirmed' => 1, 'password' => 'unused-test-password',
                'preferences' => '{"notify_discussionCreated_email":true}'],
            ['id' => 6, 'username' => 'push', 'email' => 'push@example.com', 'is_email_confirmed' => 1, 'password' => 'unused-test-password',
                'preferences' => '{ "notify_postCreated_push" : true }'],
            ['id' => 7, 'username' => 'string', 'email' => 'string@example.com', 'is_email_confirmed' => 1, 'password' => 'unused-test-password',
                'preferences' => '{"notify_postCreated_email":"true"}'],
        ]]);
    }

    public function test_selects_only_explicitly_enabled_recipients_without_sql_regexp(): void
    {
        $this->app();
        $recipients = NotificationPreferences::recipients(User::query(), 'postCreated');
        $this->assertEqualsCanonicalizing([3, 6], array_values($recipients->modelKeys()));
        $this->assertSame([5], NotificationPreferences::recipients(User::query(), 'discussionCreated')->modelKeys());
    }

    public function test_retains_caller_query_constraints(): void
    {
        $this->app();
        $recipients = NotificationPreferences::recipients(User::where('id', '!=', 3), 'postCreated');
        $this->assertSame([6], array_values($recipients->modelKeys()));
    }
}
