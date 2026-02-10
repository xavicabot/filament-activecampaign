<?php

namespace XaviCabot\FilamentActiveCampaign\Exceptions;

use Illuminate\Http\Client\Response;

class ActiveCampaignValidationException extends ActiveCampaignException
{
    public function __construct(
        string $message,
        ?Response $response = null,
        protected array $validationErrors = [],
    ) {
        parent::__construct($message, $response);
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}
