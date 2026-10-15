<?php

namespace App\Http\Controllers;

use App\Http\Requests\GymSeasonRequest;
use App\Http\Requests\GymVisibilityRequest;
use App\Models\EntityLog;
use App\Models\Gym;
use App\Models\GymSeason;
use App\Models\GymUser;
use App\Models\Permit;
use App\Models\RoutineExerciseLog;
use App\Models\User;
use App\Models\UserNotification;
use App\PublicException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GymRankingController extends Controller {

    public function show(Request $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $viewer */
        $viewer = $request->user();
        $gym = Gym::queryByDbIdOrFail($dbConnector, (int)$id);
        $canManage = $this->canManageRanking($viewer, $gym);
        if(!$canManage){
            if(!GymUser::isMember($dbConnector, $gym->getEntityId(), $viewer->id)){
                throw PublicException::forbiddenError('No perteneces a este gimnasio');
            }
            if(!$gym->isRankingPublic()){
                throw PublicException::forbiddenError('El entrenador todavia no publico el ranking');
            }
        }

        $latest = GymSeason::queryLatest($dbConnector, $gym->getEntityId());
        $current = $latest && !$latest['isClosed'] ? ['status' => GymSeason::status($latest)] + $latest : null;
        if($current && $current['status'] === GymSeason::statusActive){
            $current['standings'] = GymSeason::buildStandingList($dbConnector, $gym, $current['startsOn'], $current['endsOn']);
        }

        $closedNumberList = GymSeason::queryClosedNumberList($dbConnector, $gym->getEntityId());
        $requestedNumber = $request->integer('season') ?: null;
        $recapNumber = in_array($requestedNumber, $closedNumberList, true) ? $requestedNumber : ($closedNumberList[0] ?? null);
        $recap = $recapNumber ? GymSeason::queryClosed($dbConnector, $gym->getEntityId(), $recapNumber) : null;
        $isRecapUnseen = UserNotification::markSeasonClosedRead($dbConnector, $viewer->id, $gym->getEntityId()) > 0;

        $viewerContext = [
            'id' => $viewer->id,
            'isOwner' => $gym->isOwnedBy($viewer->id),
            'isMember' => GymUser::isMember($dbConnector, $gym->getEntityId(), $viewer->id),
            'isPublicMember' => in_array($viewer->id, GymSeason::queryParticipantIdList($dbConnector, $gym), true),
        ];
        $userMap = $this->queryUserMap(array_merge(array_column($current['standings'] ?? [], 'userId'), array_column($recap['standings'] ?? [], 'userId')));

        return $this->successApiResponse([
            'gym' => ['id' => $gym->getEntityId(), 'name' => $gym->getName(), 'isRankingPublic' => $gym->isRankingPublic()],
            'canManage' => $canManage,
            'current' => $current ? $this->buildSeasonModel($current, $current['status'], $userMap, $viewerContext) : null,
            'recap' => $recap ? $this->buildSeasonModel($recap, GymSeason::statusEnded, $userMap, $viewerContext) : null,
            'openRecap' => $recap !== null && ($requestedNumber === $recapNumber || $isRecapUnseen),
            'closedSeasonNumbers' => $closedNumberList,
        ]);
    }

    public function startSeason(GymSeasonRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $gym = $this->queryManageableGymOrFail($request, $id);
        $validated = $request->validated();
        $season = GymSeason::startOrFail($dbConnector, $gym, $validated['starts_on'], $validated['ends_on']);
        GymSeason::notifyStartIfDue($dbConnector, $gym);
        EntityLog::addNew($dbConnector, $request->user()->id, $gym, EntityLog::actionIdSeasonStarted, $gym->getName(), [
            'season' => $season['number'], 'startsOn' => $season['startsOn'], 'endsOn' => $season['endsOn'],
        ]);
        return $this->successApiResponse($this->buildSeasonDatesModel($season), 201);
    }

    public function updateSeason(GymSeasonRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $gym = $this->queryManageableGymOrFail($request, $id);
        $validated = $request->validated();
        $season = GymSeason::updateDatesOrFail($dbConnector, $gym, $validated['starts_on'] ?? null, $validated['ends_on']);
        GymSeason::notifyStartIfDue($dbConnector, $gym);
        EntityLog::addNew($dbConnector, $request->user()->id, $gym, EntityLog::actionIdSeasonUpdated, $gym->getName(), [
            'season' => $season['number'], 'startsOn' => $season['startsOn'], 'endsOn' => $season['endsOn'],
        ]);
        return $this->successApiResponse($this->buildSeasonDatesModel($season));
    }

    public function updateVisibility(GymVisibilityRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $gym = $this->queryManageableGymOrFail($request, $id);

        $isPublic = (bool)$request->validated()['is_public'];
        if($gym->isRankingPublic() !== $isPublic){
            $gym->setRankingPublic($isPublic);
            $gym->writeToDb($dbConnector);
            EntityLog::addNew($dbConnector, $user->id, $gym, EntityLog::actionIdUpdated, $gym->getName(), ['isRankingPublic' => $isPublic]);
        }
        return $this->successApiResponse(['isRankingPublic' => $isPublic]);
    }

    private function queryManageableGymOrFail(Request $request, string $id): Gym {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $gym = Gym::queryByDbIdOrFail($dbConnector, (int)$id);
        if(!$user->hasPermit($dbConnector, Permit::createGymEntityPermitSlug) || !$this->canManageRanking($user, $gym)){
            throw PublicException::forbiddenError('No tienes permisos sobre este gimnasio');
        }
        return $gym;
    }

    private function buildSeasonDatesModel(array $season): array {
        return [
            'number' => $season['number'],
            'startsOn' => $season['startsOn'],
            'endsOn' => $season['endsOn'],
            'status' => GymSeason::status($season),
        ];
    }

    private function canManageRanking(User $user, Gym $gym): bool {
        return $gym->isOwnedBy($user->id) || $user->hasSeeAllPermit($this->getDbConnector());
    }

    private function queryUserMap(array $userIdList): array {
        return User::whereIn('id', array_unique($userIdList))->get()
            ->mapWithKeys(fn(User $user) => [$user->id => $user->buildPublicApiModel() + ['gender' => $user->gender]])->all();
    }

    private function buildSeasonModel(array $season, string $status, array $userMap, array $viewerContext): array {
        $today = CarbonImmutable::parse(RoutineExerciseLog::today());
        $standingList = array_values(array_filter(array_map(
            fn(array $standing) => isset($userMap[$standing['userId']]) ? $standing + [
                'user' => $userMap[$standing['userId']],
                'isPodium' => GymSeason::isOnPodium($standing),
            ] : null,
            $season['standings']
        )));
        $awardList = array_values(array_filter(array_map(
            fn(array $award) => isset($userMap[$award['userId']]) ? $award + ['user' => $userMap[$award['userId']]] : null,
            $season['awards']
        )));
        $viewerStanding = collect($standingList)->firstWhere('userId', $viewerContext['id']);

        return [
            'number' => $season['number'],
            'startsOn' => $season['startsOn'],
            'endsOn' => $season['endsOn'],
            'status' => $status,
            'days' => (int)CarbonImmutable::parse($season['startsOn'])->diffInDays(CarbonImmutable::parse($season['endsOn'])) + 1,
            'daysLeft' => $status === GymSeason::statusActive ? (int)$today->diffInDays(CarbonImmutable::parse($season['endsOn'])) + 1 : 0,
            'daysToStart' => $status === GymSeason::statusScheduled ? (int)$today->diffInDays(CarbonImmutable::parse($season['startsOn'])) : 0,
            'standings' => $standingList,
            'awards' => $awardList,
            'viewer' => [
                'standing' => $viewerStanding,
                'awardSlugs' => array_column(array_filter($awardList, fn(array $award) => $award['userId'] === $viewerContext['id']), 'slug'),
                'reason' => match (true) {
                    $viewerStanding !== null => null,
                    $viewerContext['isOwner'] => 'owner',
                    !$viewerContext['isMember'] => 'guest',
                    !$viewerContext['isPublicMember'] && $status !== GymSeason::statusEnded => 'private',
                    $status === GymSeason::statusScheduled => null,
                    default => 'absent',
                },
            ],
        ];
    }
}
