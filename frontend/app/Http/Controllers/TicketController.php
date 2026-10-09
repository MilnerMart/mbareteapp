<?php

namespace App\Http\Controllers;

use App\Services\ApiClientService;
use App\Support\AuthPermits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    private const stateList = ['pending', 'approved', 'rejected', 'all'];

    private ApiClientService $apiClient;

    public function __construct(ApiClientService $apiClient){
        $this->apiClient = $apiClient;
    }

    /**
     * scope=mine: las que le toca resolver. El admin ademas ve todas (scope=all)
     * y el entrenador las que envio el (scope=sent), que no se muestran en su perfil.
     */
    public function index(Request $request): View{
        $isAdmin = AuthPermits::isAdmin();
        $scopeList = $isAdmin ? ['mine', 'all'] : ['mine', 'sent'];
        $scope = in_array($request->query('scope'), $scopeList, true) ? $request->query('scope') : 'mine';
        $state = in_array($request->query('state'), self::stateList, true) ? $request->query('state') : 'pending';

        $data['tickets'] = $scope === 'sent'
            ? $this->querySentTickets($state)
            : ($this->apiClient->getTickets($scope, $state) ?? []);
        $data['scope'] = $scope;
        $data['state'] = $state;
        $data['isAdmin'] = $isAdmin;
        return $this->renderView('tickets.index', compact('data'));
    }

    /**
     * Detalle de una solicitud propia: nota de rechazo, historial y reenvio.
     */
    public function show(int $id): RedirectResponse|View{
        $ticket = $this->apiClient->getTicket($id);
        if(!$ticket || (int) $ticket['requesterId'] !== (int) session('auth_user.id')){
            return AuthPermits::canManageGyms()
                ? redirect()->route('ticket.index', ['scope' => 'sent'])
                : redirect()->route('user.profile', session('auth_user.id'));
        }

        $data['ticket'] = $ticket;
        // el entrenador ve sus solicitudes en "Enviadas", el alumno en su perfil
        $data['backToSent'] = AuthPermits::canManageGyms();
        return $this->renderView('tickets.show', compact('data'));
    }

    public function resubmit(Request $request, int $id): RedirectResponse{
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['note' => 'nota']);

        $response = $this->apiClient->resubmitTicket($id, $validated['note']);

        if(!$this->isApiSuccess($response)){
            return back()
                ->withErrors(['note' => $this->apiErrorMessage($response, 'No pudimos volver a enviar la solicitud.')])
                ->withInput();
        }

        return back()->with('ticket_saved', 'Solicitud '.($response['data']['number'] ?? '').' enviada nuevamente.');
    }

    public function approve(Request $request, int $id): RedirectResponse{
        return $this->resolve($request, $id, true);
    }

    public function reject(Request $request, int $id): RedirectResponse{
        return $this->resolve($request, $id, false);
    }

    /**
     * Las solicitudes propias vienen todas juntas; el filtro de estado se aplica aca.
     */
    private function querySentTickets(string $state): array{
        $ticketList = $this->apiClient->getMyTickets() ?? [];
        if($state === 'all'){
            return $ticketList;
        }
        return array_values(array_filter($ticketList, fn($ticket) => $ticket['state'] === $state));
    }

    private function resolve(Request $request, int $id, bool $approve): RedirectResponse{
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $response = $approve
            ? $this->apiClient->approveTicket($id, $validated['note'] ?? null)
            : $this->apiClient->rejectTicket($id, $validated['note'] ?? null);

        if(!$this->isApiSuccess($response)){
            return back()->withErrors(['ticket' => $this->apiErrorMessage($response, 'No pudimos resolver la solicitud.')]);
        }

        $number = $response['data']['number'] ?? '';
        return back()->with('ticket_saved', $approve ? 'Solicitud '.$number.' aprobada.' : 'Solicitud '.$number.' rechazada.');
    }
}
