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

    public function index(Request $request): View{
        $isAdmin = AuthPermits::isAdmin();
        // solo el admin puede ver todas las solicitudes, el resto ve las que le toca resolver
        $scope = $isAdmin && $request->query('scope') === 'all' ? 'all' : 'mine';
        $state = in_array($request->query('state'), self::stateList, true) ? $request->query('state') : 'pending';

        $data['tickets'] = $this->apiClient->getTickets($scope, $state) ?? [];
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
            return redirect()->route('user.profile', session('auth_user.id'));
        }

        $data['ticket'] = $ticket;
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
