<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use XaviCabot\FilamentActiveCampaign\Services\TemplateRenderer;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class TemplateRenderingTest extends TestCase
{
    protected function makeRenderer(): TemplateRenderer
    {
        return new TemplateRenderer();
    }

    public function test_it_replaces_user_placeholders(): void
    {
        $renderer = $this->makeRenderer();

        $template = 'Hello {user.name}, your email is {user.email}.';
        $user = [
            'id'    => 1,
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ];
        $ctx = [];

        $rendered = $renderer->render($template, $user, $ctx);

        $this->assertSame(
            'Hello John Doe, your email is john@example.com.',
            $rendered
        );
    }

    public function test_it_replaces_context_placeholders(): void
    {
        $renderer = $this->makeRenderer();

        $template = 'Your first deposit was {ctx.amount} {ctx.currency}.';
        $user = [];
        $ctx = [
            'amount'   => '200',
            'currency' => 'EUR',
        ];

        $rendered = $renderer->render($template, $user, $ctx);

        $this->assertSame(
            'Your first deposit was 200 EUR.',
            $rendered
        );
    }

    public function test_unknown_placeholders_are_left_untouched(): void
    {
        $renderer = $this->makeRenderer();

        $template = 'Hello {user.unknown}.';
        $user = ['name' => 'John Doe'];
        $ctx = [];

        $rendered = $renderer->render($template, $user, $ctx);

        $this->assertSame('Hello {user.unknown}.', $rendered);
    }
}
