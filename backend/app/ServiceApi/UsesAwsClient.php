<?php

namespace App\ServiceApi;

use App\PublicException;
use Aws\Exception\AwsException;
use Closure;
use Illuminate\Support\Facades\Log;

trait UsesAwsClient {

    protected function buildAwsClientOptions(): array{
        $providerConfig = $this->getProviderConfig();
        $region = $providerConfig->getSetting('region');
        if(!$region){
            throw PublicException::validationError('Falta el ajuste region de '.$this->getProvider()->getName(), 'provider.missing_setting');
        }
        $options = ['version' => 'latest', 'region' => $region];
        $accessKeyId = $providerConfig->getCredential('access_key_id');
        $secretAccessKey = $providerConfig->getCredential('secret_access_key');
        if($accessKeyId && $secretAccessKey){
            $options['credentials'] = ['key' => $accessKeyId, 'secret' => $secretAccessKey];
        }
        $endpoint = $providerConfig->getSetting('endpoint') ?: $this->getProvider()->getBaseUrl();
        if($endpoint){
            $options['endpoint'] = $endpoint;
        }
        return $options;
    }

    protected function runAwsOrFail(string $action, string $subject, Closure $operation, string $publicMessage, string $infoCode): mixed{
        try {
            return $operation();
        } catch (AwsException $exception) {
            Log::warning($infoCode, [
                'provider' => $this->getProvider()->getSlug(),
                'providerConfigId' => $this->getProviderConfig()->getEntityId(),
                'action' => $action,
                'subject' => $subject,
                'awsCode' => $exception->getAwsErrorCode(),
                'awsMessage' => $exception->getAwsErrorMessage(),
                'httpStatus' => $exception->getStatusCode(),
            ]);
            throw PublicException::internalError($publicMessage, $infoCode);
        }
    }
}
