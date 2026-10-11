<?php

namespace App\Http\Controllers;

use App\Models\Permit;
use App\Models\User;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicResourceController extends Controller
{
    private const searchLimit = 12;

    public function provider(Request $request): JsonResponse {
        $this->usePublicResourcesOrFail($request->user());
        return $this->successApiResponse(['provider' => $this->getResourceService()->publicProviderModel()]);
    }

    public function search(Request $request): JsonResponse {
        $this->usePublicResourcesOrFail($request->user());
        $query = (string)$request->query('q', '');
        return $this->successApiResponse($this->getResourceService()->searchPublicResources($query, self::searchLimit));
    }

    public function media(Request $request, string $itemKey): JsonResponse {
        $this->usePublicResourcesOrFail($request->user());
        return $this->successApiResponse($this->getResourceService()->listPublicResourceMedia($itemKey));
    }

    private function usePublicResourcesOrFail(?User $user): void {
        if(!$user?->hasPermit($this->getDbConnector(), Permit::createCatalogEntityPermitSlug)){
            throw PublicException::forbiddenError('No tienes permisos para usar recursos publicos');
        }
    }
}
