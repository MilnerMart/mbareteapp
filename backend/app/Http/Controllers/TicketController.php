<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketResolveRequest;
use App\Http\Requests\TicketResubmitRequest;
use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserRole;
use App\PublicException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller {

    /**
     * Bandeja de solicitudes. scope=mine (default): las que le toca resolver al usuario,
     * scope=all: todas (solo admin). state=pending (default)|approved|rejected|all.
     */
    public function index(Request $request): JsonResponse {
        $dbConnector = $this->getDbConnector();
        /**  @var User $user */
        $user = $request->user();
        $isAdmin = $user->hasSeeAllPermit($dbConnector);
        $stateSlug = $request->query('state', 'pending');
        $stateId = $stateSlug === 'all' ? null : Ticket::stateIdFromSlug($stateSlug);
        if($stateSlug !== 'all' && !$stateId){
            throw PublicException::validationError('Estado de solicitud invalido: '.$stateSlug);
        }

        if($request->query('scope') === 'all'){
            $this->seeAllPermitOrFail($user);
            $ticketList = Ticket::queryList($dbConnector, $stateId);
        } else {
            $ticketList = Ticket::queryInboxList($dbConnector, $user->id, $isAdmin, $stateId);
        }

        return $this->successApiResponse(Ticket::buildApiModelList($this->getDbConnector(), $ticketList));
    }

    public function show(Request $request, string $id): JsonResponse {
        $ticket = Ticket::queryByDbIdOrFail($this->getDbConnector(), (int)$id);
        $user = $request->user();
        if($ticket->getRequesterId() !== $user->id){
            $this->canResolveOrFail($user, $ticket);
        }
        return $this->successApiResponse(Ticket::buildApiModelList($this->getDbConnector(), [$ticket])[0]);
    }

    /**
     * Solicitudes que hizo el usuario logueado.
     */
    public function myIndex(Request $request): JsonResponse {
        $ticketList = Ticket::queryListByRequesterId($this->getDbConnector(), $request->user()->id);
        return $this->successApiResponse(Ticket::buildApiModelList($this->getDbConnector(), $ticketList));
    }

    public function approve(TicketResolveRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $ticket = Ticket::queryByDbIdOrFail($dbConnector, (int)$id);
        $user = $request->user();
        $this->canResolveOrFail($user, $ticket);
        $ticket->resolve(Ticket::stateIdApproved, $user->id, $request->validated()['note'] ?? null);

        $dbConnector->getEnvConecction()->transaction(function() use ($dbConnector, $ticket){
            if($ticket->isGymJoin()){
                if(!$ticket->getGymId() || !Gym::queryByDbId($dbConnector, $ticket->getGymId())){
                    throw PublicException::validationError('El gimnasio de la solicitud ya no existe');
                }
                GymUser::addNewGymUser($dbConnector, $ticket->getGymId(), $ticket->getRequesterId());
            } elseif($ticket->isTrainerRequest()) {
                $trainerRole = Role::queryBySlugOrFail($dbConnector, Role::trainerRoleSlug);
                if(!UserRole::hasUserRole($dbConnector, $ticket->getRequesterId(), $trainerRole->getEntityId())){
                    UserRole::addNewUserRole($dbConnector, $ticket->getRequesterId(), $trainerRole->getEntityId());
                }
            }
            $ticket->writeToDb($dbConnector);
        });

        return $this->successApiResponse(Ticket::buildApiModelList($this->getDbConnector(), [$ticket])[0]);
    }

    public function reject(TicketResolveRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $ticket = Ticket::queryByDbIdOrFail($dbConnector, (int)$id);
        $user = $request->user();
        $this->canResolveOrFail($user, $ticket);
        $ticket->resolve(Ticket::stateIdRejected, $user->id, $request->validated()['note'] ?? null);
        $ticket->writeToDb($dbConnector);

        return $this->successApiResponse(Ticket::buildApiModelList($this->getDbConnector(), [$ticket])[0]);
    }

    /**
     * El solicitante vuelve a enviar su solicitud rechazada con una nota (hasta Ticket::maxResubmitCount veces).
     */
    public function resubmit(TicketResubmitRequest $request, string $id): JsonResponse {
        $dbConnector = $this->getDbConnector();
        $ticket = Ticket::queryByDbIdOrFail($dbConnector, (int)$id);
        if($ticket->getRequesterId() !== $request->user()->id){
            throw PublicException::forbiddenError('Solo quien hizo la solicitud puede volver a enviarla');
        }
        $ticket->resubmit($request->validated()['note']);
        $ticket->writeToDb($dbConnector);

        return $this->successApiResponse(Ticket::buildApiModelList($dbConnector, [$ticket])[0]);
    }

    /**
     * El alta de entrenador la resuelve solo el admin; el ingreso a un gimnasio, su dueño o el admin.
     */
    private function canResolveOrFail(User $user, Ticket $ticket): void {
        $dbConnector = $this->getDbConnector();
        if($user->hasSeeAllPermit($dbConnector)){
            return;
        }
        if($ticket->isGymJoin() && $ticket->getGymId()){
            $gym = Gym::queryByDbId($dbConnector, $ticket->getGymId());
            if($gym?->isOwnedBy($user->id)){
                return;
            }
        }
        throw PublicException::forbiddenError('No tienes permisos sobre esta solicitud');
    }
}
