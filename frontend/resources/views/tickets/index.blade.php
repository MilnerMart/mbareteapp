@use('App\Support\TicketLabels')
@php
    $tickets = $data['tickets'];
    $scope = $data['scope'];
    $state = $data['state'];
    $isAdmin = $data['isAdmin'];
    $stateFilters = ['pending' => 'Pendientes', 'approved' => 'Aprobadas', 'rejected' => 'Rechazadas', 'all' => 'Todas'];
    // el admin alterna entre las suyas y todas; el entrenador entre las que recibe y las que envio
    $scopeTabs = $isAdmin ? ['mine' => 'Mias', 'all' => 'Todas'] : ['mine' => 'Recibidas', 'sent' => 'Enviadas'];
    $scopeEyebrows = ['mine' => 'Solicitudes para mi', 'all' => 'Todas las solicitudes', 'sent' => 'Solicitudes que envie'];
    $isSent = $scope === 'sent';
@endphp
@extends('layouts.layout')

@section('title', 'Solicitudes | Mbarete App')

@section('css')
    @vite(['resources/css/ticket.css'])
@endsection

@section('content')
    <section class="ticket-page">
        <div class="ticket-header">
            <div>
                <span class="ticket-eyebrow">{{ $scopeEyebrows[$scope] }}</span>
                <h1>Solicitudes</h1>
            </div>
            <nav class="ticket-tabs" aria-label="Alcance">
                @foreach ($scopeTabs as $scopeSlug => $scopeName)
                    <a href="{{ route('ticket.index', ['scope' => $scopeSlug, 'state' => $state]) }}"
                        @class(['ticket-tab', 'active' => $scope === $scopeSlug])>{{ $scopeName }}</a>
                @endforeach
            </nav>
        </div>

        <nav class="ticket-filters" aria-label="Estado">
            @foreach ($stateFilters as $stateSlug => $stateName)
                <a href="{{ route('ticket.index', ['scope' => $scope, 'state' => $stateSlug]) }}"
                    @class(['ticket-filter', 'active' => $state === $stateSlug])>{{ $stateName }}</a>
            @endforeach
        </nav>

        @if (session('ticket_saved'))
            <div class="alert alert-success">{{ session('ticket_saved') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @if (empty($tickets))
            <div class="ticket-empty">
                <i class="fa-solid fa-inbox"></i>
                <p>No hay solicitudes {{ $state === 'all' ? '' : strtolower($stateFilters[$state]) }}.</p>
            </div>
        @else
            <ul class="ticket-list">
                @foreach ($tickets as $ticket)
                    @php
                        $requester = $ticket['refs']['requester'] ?? null;
                        $requesterName = $requester ? trim(($requester['name'] ?? '') . ' ' . ($requester['last_name'] ?? '')) : 'Usuario eliminado';
                        $gymName = $ticket['refs']['gym']['name'] ?? null;
                        $resolver = $ticket['refs']['resolver'] ?? null;
                        $resolverName = $resolver ? trim(($resolver['name'] ?? '') . ' ' . ($resolver['last_name'] ?? '')) : null;
                    @endphp
                    <li class="ticket-item">
                        <div class="ticket-item-head">
                            <span class="ticket-number">{{ $ticket['number'] }}</span>
                            <span class="ticket-type">{{ TicketLabels::typeLabel($ticket['type']) }}</span>
                            <span class="ticket-state ticket-state-{{ $ticket['state'] }}">{{ TicketLabels::stateLabel($ticket['state']) }}</span>
                        </div>

                        <div class="ticket-item-body">
                            @unless ($isSent)
                                <img
                                    src="{{ $requester['profile_image_url'] ?? asset('images/leoncioBiceps.png') }}"
                                    alt=""
                                    class="ticket-avatar">
                            @endunless
                            <div class="ticket-info">
                                @if ($isSent)
                                    <strong>{{ TicketLabels::requesterSummary($ticket) }}</strong>
                                @else
                                    <strong>{{ $requesterName ?: 'Usuario' }}</strong>
                                    @if (!empty($requester['email']))
                                        <span>{{ $requester['email'] }}</span>
                                    @endif
                                @endif
                                <span>
                                    @switch ($ticket['type'])
                                        @case('gym-join')
                                            Quiere sumarse a <strong>{{ $gymName ?? 'un gimnasio eliminado' }}</strong>
                                            @break
                                        @case('muscle-create')
                                            Propone un musculo
                                            @break
                                        @case('exercise-create')
                                            Propone un ejercicio
                                            @break
                                        @default
                                            Quiere ser entrenador
                                    @endswitch
                                    · {{ TicketLabels::formatDate($ticket['createdAt']) }}
                                </span>
                                @if (($ticket['resubmitCount'] ?? 0) > 0)
                                    @php
                                        $lastRejection = collect($ticket['history'] ?? [])->where('action', 'rejected')->last();
                                    @endphp
                                    <span class="ticket-resubmitted">
                                        <span class="ticket-resubmitted-tag">Reenviada</span>
                                        @if (!empty($lastRejection['note']))
                                            Rechazada antes: “{{ $lastRejection['note'] }}”
                                        @endif
                                    </span>
                                    @if (!empty($ticket['requesterNote']))
                                        <span class="ticket-requester-note">Nota del usuario: “{{ $ticket['requesterNote'] }}”</span>
                                    @endif
                                @endif
                                @if ($ticket['state'] !== 'pending')
                                    <span class="ticket-resolution">
                                        {{ TicketLabels::stateLabel($ticket['state']) }}
                                        @if ($resolverName) por {{ $resolverName }} @endif
                                        el {{ TicketLabels::formatDate($ticket['resolvedAt']) }}
                                        @if (!empty($ticket['resolutionNote']))
                                            — “{{ $ticket['resolutionNote'] }}”
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if (!empty($ticket['refs']['entity']))
                            @include('tickets._entity', ['entity' => $ticket['refs']['entity']])
                        @endif

                        @if ($isSent)
                            <div class="ticket-actions">
                                <a href="{{ route('ticket.show', $ticket['id']) }}" class="btn ticket-approve-btn">
                                    <i class="fa-solid fa-eye"></i>
                                    <span>{{ ($ticket['canResubmit'] ?? false) ? 'Ver y reenviar' : 'Ver detalle' }}</span>
                                </a>
                            </div>
                        @elseif ($ticket['state'] === 'pending')
                            <form method="POST" class="ticket-actions">
                                @csrf
                                <input
                                    type="text"
                                    name="note"
                                    class="form-control"
                                    maxlength="500"
                                    placeholder="Nota (opcional)"
                                    aria-label="Nota para la solicitud {{ $ticket['number'] }}">
                                <button type="submit" formaction="{{ route('ticket.approve', $ticket['id']) }}" class="btn ticket-approve-btn">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Aprobar</span>
                                </button>
                                <button
                                    type="submit"
                                    formaction="{{ route('ticket.reject', $ticket['id']) }}"
                                    class="btn ticket-reject-btn"
                                    data-confirm-reject
                                    data-ticket-number="{{ $ticket['number'] }}"
                                    data-requester-name="{{ $requesterName ?: 'Usuario' }}">
                                    <i class="fa-solid fa-xmark"></i>
                                    <span>Rechazar</span>
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
