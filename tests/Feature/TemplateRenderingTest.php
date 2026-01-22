<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class TemplateRenderingTest extends TestCase
{
    protected function createMockUser(array $attributes = []): Authenticatable
    {
        $defaults = [
            'id' => 1,
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ];

        $merged = array_merge($defaults, $attributes);

        return new class($merged) implements Authenticatable {
            public int $id;
            public string $email;
            public string $name;
            public ?string $phone;

            public function __construct(array $attributes)
            {
                $this->id = $attributes['id'];
                $this->email = $attributes['email'];
                $this->name = $attributes['name'];
                $this->phone = $attributes['phone'] ?? null;
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return $this->id;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }

    protected function getRenderedValue(string $template, ?Authenticatable $user = null, array $context = []): string
    {
        Http::fake();

        $automation = ActiveCampaignAutomation::create([
            'name' => 'Template Test',
            'event' => 'test.event',
            'is_active' => true,
            'system_fields' => [
                'testField' => $template,
            ],
        ]);

        $runner = app(ActiveCampaignAutomationRunner::class);
        $plan = $runner->buildExecutionPlan($automation, $user ?? $this->createMockUser(), $context);

        return $plan['system_fields']['testField'];
    }

    public function test_it_replaces_user_placeholders(): void
    {
        $result = $this->getRenderedValue(
            'Hello {user.name}, your email is {user.email}.',
            $this->createMockUser([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ])
        );

        $this->assertEquals('Hello John Doe, your email is john@example.com.', $result);
    }

    public function test_it_replaces_context_placeholders(): void
    {
        $result = $this->getRenderedValue(
            'Your first deposit was {ctx.amount} {ctx.currency}.',
            null,
            ['amount' => '200', 'currency' => 'EUR']
        );

        $this->assertEquals('Your first deposit was 200 EUR.', $result);
    }

    public function test_unknown_placeholders_are_left_untouched(): void
    {
        $result = $this->getRenderedValue(
            'Hello {user.unknown}.',
            $this->createMockUser()
        );

        $this->assertEquals('Hello {user.unknown}.', $result);
    }

    public function test_now_placeholder_is_replaced(): void
    {
        $result = $this->getRenderedValue('{now}');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    public function test_now_date_placeholder_is_replaced(): void
    {
        $result = $this->getRenderedValue('{now_date}');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    public function test_nested_context_placeholders(): void
    {
        $result = $this->getRenderedValue(
            'Order: {ctx.order.id} - Product: {ctx.order.product}',
            null,
            ['order' => ['id' => '12345', 'product' => 'Widget']]
        );

        $this->assertEquals('Order: 12345 - Product: Widget', $result);
    }

    public function test_mixed_placeholders(): void
    {
        $result = $this->getRenderedValue(
            '{user.name} bought {ctx.product} for {ctx.amount}',
            $this->createMockUser(['name' => 'Jane']),
            ['product' => 'Laptop', 'amount' => '$999']
        );

        $this->assertEquals('Jane bought Laptop for $999', $result);
    }
}
