<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationReadRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller {

    public function index(Request $request): JsonResponse {
        return $this->successApiResponse($this->buildInboxModel($request->user()));
    }

    public function markRead(NotificationReadRequest $request): JsonResponse {
        $user = $request->user();
        UserNotification::markReadByUserId($this->getDbConnector(), $user->id, $request->validated()['ids'] ?? null);
        return $this->successApiResponse($this->buildInboxModel($user));
    }

    private function buildInboxModel(User $user): array {
        $dbConnector = $this->getDbConnector();
        return [
            'unreadCount' => UserNotification::countUnreadByUserId($dbConnector, $user->id),
            'pendingProfileFields' => $user->getPendingProfileFieldList(),
            'items' => array_map(fn(UserNotification $notification) => $notification->buildApiModel(),
                UserNotification::queryListByUserId($dbConnector, $user->id)),
        ];
    }
}
