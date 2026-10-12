<?php

namespace App\ServiceApi;

use App\PublicException;

class BrevoApiClient extends ProviderApiClient implements MessagingApi {

    private const defaultBaseUrl = 'https://api.brevo.com/v3';

    static function clientLabel(): string{
        return 'Brevo';
    }

    static function credentialKeyList(): array{
        return ['api_key'];
    }

    static function settingKeyList(): array{
        return ['senderEmail', 'senderName'];
    }

    static function supportedChannels(): array{
        return [self::channelEmail];
    }

    protected function getBaseUrl(): string{
        $baseUrl = $this->getProviderConfig()->getSetting('baseUrl') ?: $this->getProvider()->getBaseUrl();
        return rtrim($baseUrl ?: self::defaultBaseUrl, '/');
    }

    protected function buildAuthHeaders(): array{
        $apiKey = $this->getProviderConfig()->getCredential('api_key');
        if(!$apiKey){
            throw PublicException::validationError('Falta la credencial api_key de '.$this->getProvider()->getName(), 'provider.missing_credential');
        }
        return ['api-key' => $apiKey];
    }

    function sendMessage(string $channel, string $to, string $message, array $options = []): array{
        if($channel !== self::channelEmail){
            throw PublicException::validationError($this->getProvider()->getName().' no envia por el canal '.$channel);
        }
        $senderEmail = $this->getProviderConfig()->getSetting('senderEmail');
        if(!$senderEmail){
            throw PublicException::validationError('Falta el ajuste senderEmail de '.$this->getProvider()->getName(), 'provider.missing_setting');
        }

        $recipient = array_filter(['email' => $to, 'name' => $options['toName'] ?? null]);
        $response = $this->postJson('/smtp/email', array_filter([
            'sender' => array_filter(['email' => $senderEmail, 'name' => $this->getProviderConfig()->getSetting('senderName')]),
            'to' => [$recipient],
            'subject' => $options['subject'] ?? $this->getProvider()->getName(),
            'htmlContent' => $options['html'] ?? null,
            'textContent' => $message,
        ]));
        return ['id' => $response['messageId'] ?? null];
    }
}
