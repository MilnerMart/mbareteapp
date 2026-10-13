<?php

use App\ServiceApi\AwsApiClient;
use App\ServiceApi\AwsSesApiClient;
use App\ServiceApi\BrevoApiClient;
use App\ServiceApi\FreeExerciseDbApiClient;
use App\ServiceApi\LocalStorageApiClient;
use App\ServiceApi\WgerApiClient;

/*
| Tipos de proveedor que se pueden crear: tipo => clase que extiende ProviderApiClient.
| Lo que el proveedor puede hacer sale de las interfaces que implementa la clase
| (MessagingApi, ResourceApi...).
*/
return [
    'local-storage' => LocalStorageApiClient::class,
    'free-exercise-db' => FreeExerciseDbApiClient::class,
    'wger' => WgerApiClient::class,
    'aws-s3' => AwsApiClient::class,
    'aws-ses' => AwsSesApiClient::class,
    'brevo' => BrevoApiClient::class,
];
