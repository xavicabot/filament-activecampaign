<?php

namespace XaviCabot\FilamentActiveCampaign\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Exceptions\ActiveCampaignException;

class ActiveCampaignClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
    ) {
    }

    /**
     * @throws ActiveCampaignException
     */
    protected function newRequest()
    {
        if (empty($this->baseUrl) || empty($this->apiKey)) {
            throw new ActiveCampaignException('ActiveCampaign base_url or api_key not configured.');
        }

        return Http::withHeaders([
            'Api-Token'    => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ])->baseUrl(rtrim($this->baseUrl, '/') . '/api/3');
    }

    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            throw new ActiveCampaignException(
                'ActiveCampaign API error: ' . $response->body(),
                $response
            );
        }

        return $response->json() ?? [];
    }

    // CONTACTOS
    public function syncContact(array $contact): array
    {
        $response = $this->newRequest()
            ->post('contact/sync', ['contact' => $contact]);

        return $this->handle($response);
    }

    public function getContactByEmail(string $email): ?array
    {
        $response = $this->newRequest()
            ->get('contacts', ['email' => $email]);

        $data = $this->handle($response);

        return $data['contacts'][0] ?? null;
    }

    // LISTAS
    public function subscribeContactToList(string $contactId, string $listId, int $status = 1): array
    {
        $payload = [
            'contactList' => [
                'list'    => $listId,
                'contact' => $contactId,
                'status'  => $status, // 1 = subscribed, 2 = unsubscribed
            ],
        ];

        $response = $this->newRequest()
            ->post('contactLists', $payload);

        return $this->handle($response);
    }

    // TAGS
    public function attachTagToContact(string $contactId, string $tagId): array
    {
        $payload = [
            'contactTag' => [
                'contact' => $contactId,
                'tag'     => $tagId,
            ],
        ];

        $response = $this->newRequest()
            ->post('contactTags', $payload);

        return $this->handle($response);
    }

    public function listTags(array $params = []): array
    {
        $response = $this->newRequest()
            ->get('tags', $params);

        return $this->handle($response);
    }

    /**
     * Create a new tag in ActiveCampaign
     *
     * @param string $name Tag name
     * @param string $tagType Tag type (default: 'contact')
     * @param string|null $description Optional tag description
     * @return array API response with created tag data
     * @throws ActiveCampaignException
     */
    public function createTag(string $name, string $tagType = 'contact', ?string $description = null): array
    {
        $payload = [
            'tag' => [
                'tag' => $name,
                'tagType' => $tagType,
            ],
        ];

        if ($description !== null) {
            $payload['tag']['description'] = $description;
        }

        $response = $this->newRequest()
            ->post('tags', $payload);

        return $this->handle($response);
    }

    // CAMPOS
    public function listFields(array $params = []): array
    {
        $response = $this->newRequest()
            ->get('fields', $params);

        return $this->handle($response);
    }

    public function setFieldValue(string $contactId, string $fieldId, string $value): array
    {
        $payload = [
            'fieldValue' => [
                'contact' => $contactId,
                'field'   => $fieldId,
                'value'   => $value,
            ],
        ];

        $response = $this->newRequest()
            ->post('fieldValues', $payload);

        return $this->handle($response);
    }

    public function listLists(array $params = []): array
    {
        $response = $this->newRequest()
            ->get('lists', $params);

        return $this->handle($response);
    }
}
