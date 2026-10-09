@use('App\Support\TicketLabels')
@php
    $ticket = $data['ticket'];
    $history = $ticket['history'] ?? [];
@endphp
@extends('layouts.layout')

@section('title', 'Solicitud ' . $ticket['number'] . ' | Mbarete App')

@section('css')
    @vite(['resources/css/ticket.css'])
@endsection

@section('content')
    <section class="ticket-page">
        <a href="{{ route('user.profile', session('auth_user.id')) }}" class="ticket-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Mi perfil</span>
        </a>

        @if (session('ticket_saved'))
            <div class="alert alert-success">{{ session('ticket_saved') }}</div>
        @endif

        <div class="ticket-detail">
            <div class="ticket-item-head">
                <span class="ticket-number">{{ $ticket['number'] }}</span>
                <span class="ticket-type">{{ TicketLabels::typeLabel($ticket['type']) }}</span>
                <span class="ticket-state ticket-state-{{ $ticket['state'] }}">{{ TicketLabels::stateLabel($ticket['state']) }}</span>
            </div>

            <h1>{{ TicketLabels::requesterSummary($ticket) }}</h1>
            <span class="ticket-detail-date">Enviada el {{ TicketLabels::formatDate($ticket['createdAt']) }}</span>

            @if ($ticket['state'] === 'rejected')
                <div class="ticket-rejection">
                    <span class="ticket-rejection-label">Motivo del rechazo</span>
                    <p>{{ $ticket['resolutionNote'] ?: 'No se dejo una nota.' }}</p>
                </div>
            @endif

            @if (!empty($history))
                <div class="ticket-history">
                    <h2>Historial</h2>
                    <ol class="ticket-history-list">
                        <li class="ticket-history-item">
                            <span class="ticket-history-action">Enviada</span>
                            <span class="ticket-history-meta">{{ TicketLabels::formatDate($ticket['createdAt']) }}</span>
                        </li>
                        @foreach ($history as $item)
                            <li class="ticket-history-item ticket-history-{{ $item['action'] }}">
                                <span class="ticket-history-action">{{ TicketLabels::historyActionLabel($item['action']) }}</span>
                                <span class="ticket-history-meta">
                                    @if ($item['action'] === 'resubmitted')
                                        por vos
                                    @elseif (!empty($item['userName']))
                                        por {{ $item['userName'] }}
                                    @endif
                                    · {{ TicketLabels::formatDate($item['at']) }}
                                </span>
                                @if (!empty($item['note']))
                                    <p class="ticket-history-note">“{{ $item['note'] }}”</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($ticket['canResubmit'] ?? false)
                <form method="POST" action="{{ route('ticket.resubmit', $ticket['id']) }}" class="ticket-resubmit-form">
                    @csrf
                    <label for="note" class="form-label">Volver a enviar la solicitud</label>
                    <textarea
                        id="note"
                        name="note"
                        rows="3"
                        minlength="5"
                        maxlength="500"
                        class="form-control @error('note') is-invalid @enderror"
                        placeholder="Contale a quien la revisa por que deberia aprobarla"
                        required>{{ old('note') }}</textarea>
                    @error('note')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <small class="ticket-hint">Solo podes reenviarla una vez.</small>
                    <button type="submit" class="btn ticket-approve-btn">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Volver a enviar</span>
                    </button>
                </form>
            @elseif ($ticket['state'] === 'rejected')
                <p class="ticket-hint">Ya reenviaste esta solicitud una vez, no se puede volver a enviar.</p>
            @endif
        </div>
    </section>
@endsection
