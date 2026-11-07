<?php

namespace Tests;

use App\Contracts\ChatHistory;
use App\Support\ArrayChatHistory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any test that touches /chat would otherwise open a real Redis
        // connection — including the SecurityHeaders cache-header test,
        // which does not look chat-related at all.
        $this->app->instance(ChatHistory::class, new ArrayChatHistory);
    }
}
