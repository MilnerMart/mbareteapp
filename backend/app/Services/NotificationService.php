<?php

namespace App\Services;

use App\Models\CoreConfig;
use App\ServiceApi\MessagingApi;
use App\ServiceApi\ProviderApiClient;

class NotificationService extends BaseService {

    public function sendEmail(string $to, ?string $toName, string $subject, string $html, string $text): array{
        /** @var MessagingApi $client */
        $client = ProviderApiClient::allocForAction($this->getDbConnecto(), CoreConfig::actionSendEmail);
        return $client->sendMessage(MessagingApi::channelEmail, $to, $text, [
            'subject' => $subject,
            'html' => $html,
            'toName' => $toName,
        ]);
    }
}
