<?php

namespace App\Http\Controllers;

use App\Core\EntityStatus;
use App\Http\Requests\ProgressEntryRequest;
use App\Models\Exercise;
use App\Models\ProgressEntry;
use App\Models\User;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller {

    public function index(Request $request): JsonResponse {
        $user = $request->user();
        return $this->successApiResponse([
            'height' => $user->height,
            'weight' => $user->weight,
            'entries' => ProgressEntry::buildApiModelListByUserId($this->getDbConnector(), $user->id),
        ]);
    }

    public function store(ProgressEntryRequest $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $exerciseId = isset($validated['exercise_id']) ? (int)$validated['exercise_id'] : null;
        if($exerciseId){
            $exercise = Exercise::queryByDbId($dbConnector, $exerciseId);
            if(!$exercise?->canBeSeenBy($user->id, $user->hasSeeAllPermit($dbConnector))){
                throw PublicException::validationError('El ejercicio no esta disponible');
            }
        }
        $entry = ProgressEntry::allocNew($user->id, $validated['entry_on'], $validated['metric'], $exerciseId,
            (float)$validated['value'], $validated['note'] ?? null);

        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $entry, $user){
            $entry->writeToDb($dbConnector);
            $this->syncProfileWeight($user);
        });

        $model = collect(ProgressEntry::buildApiModelListByUserId($dbConnector, $user->id))->firstWhere('id', $entry->getEntityId());
        return $this->successApiResponse($model, 201);
    }

    public function destroy(Request $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $entry = ProgressEntry::queryOwnedOrFail($dbConnector, $request->user()->id, (int)$id);
        $entry->setStatusId(EntityStatus::statusIdDeleted);
        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $entry, $request){
            $entry->writeToDb($dbConnector);
            $this->syncProfileWeight($request->user());
        });
        return $this->successApiResponse(code: 200);
    }

    // el peso del perfil acompaña al ultimo peso registrado
    private function syncProfileWeight(User $user): void {
        $latest = ProgressEntry::queryLatestBodyWeight($this->getDbConnector(), $user->id);
        if($latest && (int)round($latest->getValue()) !== (int)$user->weight){
            User::allocDbTable($this->getDbConnector())->where('id', $user->id)->update(['weight' => (int)round($latest->getValue())]);
        }
    }
}
