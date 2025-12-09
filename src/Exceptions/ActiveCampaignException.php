<?php

namespace XaviCabot\FilamentActiveCampaign\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class ActiveCampaignException extends Exception
{
    public function __construct(
        string $message,
        protected ?Response $response = null,
    ) {
        parent::__construct($message);
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
