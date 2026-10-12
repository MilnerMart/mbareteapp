<?php

namespace App\ServiceApi;

use App\PublicException;
use Aws\SesV2\SesV2Client;

class AwsSesApiClient extends ProviderApiClient implements MessagingApi {

    use UsesAwsClient;

    private ?SesV2Client $sesClient = null;

    static function clientLabel(): string{
        return 'Amazon SES';
    }

    static function credentialKeyList(): array{
        return ['access_key_id', 'secret_access_key'];
    }

    static function settingKeyList(): array{
        return ['region', 'senderEmail', 'senderName', 'configurationSet'];
    }

    static function supportedChannels(): array{
        return [self::channelEmail];
    }

    protected function buildAuthHeaders(): array{
        return [];
    }

    function sendMessage(string $channel, string $to, string $message, array $options = []): array{
        if($channel !== self::channelEmail){
            throw PublicException::validationError($this->getProvider()->getName().' no envia por el canal '.$channel);
        }
        $providerConfig = $this->getProviderConfig();
        $senderEmail = $providerConfig->getSetting('senderEmail');
        if(!$senderEmail){
            throw PublicException::validationError('Falta el ajuste senderEmail de '.$this->getProvider()->getName(), 'provider.missing_setting');
        }

        $body = ['Text' => ['Data' => $message, 'Charset' => 'UTF-8']];
        if(!empty($options['html'])){
            $body['Html'] = ['Data' => $options['html'], 'Charset' => 'UTF-8'];
        }
        $request = [
            'FromEmailAddress' => self::formatAddress($senderEmail, $providerConfig->getSetting('senderName')),
            'Destination' => ['ToAddresses' => [self::formatAddress($to, $options['toName'] ?? null)]],
            'Content' => ['Simple' => [
                'Subject' => ['Data' => $options['subject'] ?? $this->getProvider()->getName(), 'Charset' => 'UTF-8'],
                'Body' => $body,
            ]],
        ];
        if($configurationSet = $providerConfig->getSetting('configurationSet')){
            $request['ConfigurationSetName'] = $configurationSet;
        }

        $result = $this->runAwsOrFail('enviar', $to, fn() => $this->getSesClient()->sendEmail($request),
            'No se pudo enviar el correo con '.$this->getProvider()->getName(), 'provider.ses_error');
        return ['id' => $result['MessageId'] ?? null];
    }

    private function getSesClient(): SesV2Client{
        return $this->sesClient ??= new SesV2Client($this->buildAwsClientOptions());
    }

    private static function formatAddress(string $email, ?string $name): string{
        if(!$name){
            return $email;
        }
        $displayName = mb_check_encoding($name, 'ASCII') ? '"'.addcslashes($name, '"\\').'"' : mb_encode_mimeheader($name, 'UTF-8', 'B');
        return $displayName.' <'.$email.'>';
    }
}
