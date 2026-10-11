<?php

namespace App\ServiceApi;

interface ResourceApi {

    function uploadResource(string $localPath, string $resourceKey, array $options = []): array;

    function obtainResource(string $resourceKey, array $options = []): array;

    function deleteResource(string $resourceKey, array $options = []): bool;
}
