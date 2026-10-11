<?php

use App\ServiceApi\FreeExerciseDbApiClient;
use App\ServiceApi\LocalStorageApiClient;

/*
| Tipos de proveedor que se pueden crear: tipo => clase que extiende ProviderApiClient.
| Lo que el proveedor puede hacer sale de las interfaces que implementa la clase
| (MessagingApi, ResourceApi...).
*/
return [
    'local-storage' => LocalStorageApiClient::class,
    'free-exercise-db' => FreeExerciseDbApiClient::class,
];
