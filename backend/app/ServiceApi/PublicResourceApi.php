<?php

namespace App\ServiceApi;

interface PublicResourceApi {

    const kindImage = 'image', kindVideo = 'video';

    function searchPublicResources(string $query, int $limit = 10): array;

    function listPublicResourceMedia(string $itemKey): array;

    function obtainPublicResource(string $resourceKey): array;
}
