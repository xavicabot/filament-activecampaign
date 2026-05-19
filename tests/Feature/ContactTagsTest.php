<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignClient;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class ContactTagsTest extends TestCase
{
    public function test_client_list_contact_tags_calls_correct_endpoint(): void
    {
        Http::fake([
            '*/api/3/contacts/42/contactTags' => Http::response([
                'contactTags' => [
                    ['id' => '900', 'contact' => '42', 'tag' => '10'],
                    ['id' => '901', 'contact' => '42', 'tag' => '11'],
                ],
            ], 200),
        ]);

        $client = app(ActiveCampaignClient::class);
        $result = $client->listContactTags('42');

        $this->assertCount(2, $result['contactTags']);
        $this->assertSame('900', $result['contactTags'][0]['id']);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/3/contacts/42/contactTags')
                && $request->method() === 'GET';
        });
    }

    public function test_client_detach_contact_tag_calls_correct_endpoint(): void
    {
        Http::fake([
            '*/api/3/contactTags/900' => Http::response([], 200),
        ]);

        $client = app(ActiveCampaignClient::class);
        $client->detachContactTag('900');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/3/contactTags/900')
                && $request->method() === 'DELETE';
        });
    }

    public function test_service_get_contact_by_email_returns_contact_array(): void
    {
        Http::fake([
            '*/api/3/contacts*' => Http::response([
                'contacts' => [
                    ['id' => '42', 'email' => 'jane@example.com'],
                ],
            ], 200),
        ]);

        $service = app(ActiveCampaignService::class);
        $contact = $service->getContactByEmail('jane@example.com');

        $this->assertNotNull($contact);
        $this->assertSame('42', $contact['id']);
        $this->assertSame('jane@example.com', $contact['email']);
    }

    public function test_service_get_contact_by_email_returns_null_when_missing(): void
    {
        Http::fake([
            '*/api/3/contacts*' => Http::response(['contacts' => []], 200),
        ]);

        $service = app(ActiveCampaignService::class);

        $this->assertNull($service->getContactByEmail('missing@example.com'));
    }

    public function test_service_get_contact_tags_proxies_to_client(): void
    {
        Http::fake([
            '*/api/3/contacts/42/contactTags' => Http::response([
                'contactTags' => [
                    ['id' => '900', 'contact' => '42', 'tag' => '10'],
                ],
            ], 200),
        ]);

        $service = app(ActiveCampaignService::class);
        $tags = $service->getContactTags('42');

        $this->assertArrayHasKey('contactTags', $tags);
        $this->assertSame('10', $tags['contactTags'][0]['tag']);
    }

    public function test_service_add_tag_to_contact_accepts_string(): void
    {
        Cache::flush();

        Http::fake([
            '*/api/3/tags*' => Http::response([
                'tags' => [['id' => '55', 'tag' => 'vip']],
            ], 200),
            '*/api/3/contactTags' => Http::response(['contactTag' => []], 200),
        ]);

        $service = app(ActiveCampaignService::class);
        $service->addTagToContact('42', 'vip');

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/api/3/contactTags')) {
                return false;
            }

            if ($request->method() !== 'POST') {
                return false;
            }

            $body = json_decode($request->body(), true);

            return $body['contactTag']['contact'] === '42'
                && $body['contactTag']['tag'] === '55';
        });
    }

    public function test_service_add_tag_to_contact_accepts_array_of_names(): void
    {
        Cache::flush();

        Http::fake([
            '*/api/3/tags*' => Http::sequence()
                ->push(['tags' => [['id' => '55', 'tag' => 'vip']]], 200)
                ->push(['tags' => [['id' => '56', 'tag' => 'beta']]], 200),
            '*/api/3/contactTags' => Http::response(['contactTag' => []], 200),
        ]);

        $service = app(ActiveCampaignService::class);
        $service->addTagToContact('42', ['vip', 'beta']);

        $attached = collect(Http::recorded())
            ->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/api/3/contactTags')
                && $pair[0]->method() === 'POST')
            ->map(fn ($pair) => json_decode($pair[0]->body(), true)['contactTag']['tag'])
            ->values()
            ->all();

        $this->assertSame(['55', '56'], $attached);
    }

    public function test_service_remove_tag_from_contact_detaches_matching_association(): void
    {
        Cache::flush();

        Http::fake([
            '*/api/3/tags*' => Http::response([
                'tags' => [['id' => '55', 'tag' => 'vip']],
            ], 200),
            '*/api/3/contacts/42/contactTags' => Http::response([
                'contactTags' => [
                    ['id' => '900', 'contact' => '42', 'tag' => '10'],
                    ['id' => '901', 'contact' => '42', 'tag' => '55'],
                ],
            ], 200),
            '*/api/3/contactTags/901' => Http::response([], 200),
        ]);

        $service = app(ActiveCampaignService::class);
        $service->removeTagFromContact('42', 'vip');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/3/contactTags/901')
                && $request->method() === 'DELETE';
        });

        // It must not delete the unrelated association
        Http::assertNotSent(function ($request) {
            return str_ends_with($request->url(), '/api/3/contactTags/900')
                && $request->method() === 'DELETE';
        });
    }

    public function test_service_remove_tag_from_contact_is_noop_when_tag_not_attached(): void
    {
        Cache::flush();

        Http::fake([
            '*/api/3/tags*' => Http::response([
                'tags' => [['id' => '55', 'tag' => 'vip']],
            ], 200),
            '*/api/3/contacts/42/contactTags' => Http::response([
                'contactTags' => [
                    ['id' => '900', 'contact' => '42', 'tag' => '10'],
                ],
            ], 200),
        ]);

        $service = app(ActiveCampaignService::class);
        $service->removeTagFromContact('42', 'vip');

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/api/3/contactTags/')
                && $request->method() === 'DELETE';
        });
    }
}
