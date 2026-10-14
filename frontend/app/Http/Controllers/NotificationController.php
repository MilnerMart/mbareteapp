<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\NotificationInbox;
use App\Support\NotificationLabels;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller{

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    public function open(int $id): RedirectResponse{
        $inbox = $this->markRead([$id]);
        $notification = collect($inbox['items'] ?? [])->firstWhere('id', $id);
        $url = $notification ? NotificationLabels::targetUrl($notification) : null;
        return $url ? redirect($url) : back();
    }

    public function readAll(): RedirectResponse{
        $this->markRead(null);
        return back();
    }

    // la respuesta ya trae la bandeja actualizada: la campanita se refresca sin otra llamada
    private function markRead(?array $idList): ?array{
        $response = $this->apiClient->markNotificationsRead($idList);
        if(!$this->isApiSuccess($response)){
            NotificationInbox::forget();
            return null;
        }
        NotificationInbox::store($response['data']);
        return $response['data'];
    }
}
