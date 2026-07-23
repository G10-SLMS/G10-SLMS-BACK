<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\MentionParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionParserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_mentioned_user_ids_and_ignores_invalid_users(): void
    {
        $john = User::factory()->create([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $trainer = User::factory()->create([
            'name' => 'Trainer 01',
            'email' => 'trainer01@example.com',
        ]);

        $admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
        ]);

        $service = new MentionParserService();

        $mentionedUserIds = $service->extractUserIds(
            'Please review [**@john**](https://github.com/john), [**@trainer01**](https://github.com/trainer01), @admin, and @ghost.'
        );

        $this->assertSame([
            $john->id,
            $trainer->id,
            $admin->id,
        ], $mentionedUserIds);
    }

    public function test_it_prevents_duplicate_mentions_and_ignores_email_addresses(): void
    {
        $john = User::factory()->create([
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $service = new MentionParserService();

        $mentionedUserIds = $service->extractUserIds(
            'Thanks @john, [**@john**](https://github.com/john), and test@example.com.'
        );

        $this->assertSame([$john->id], $mentionedUserIds);
    }
}
