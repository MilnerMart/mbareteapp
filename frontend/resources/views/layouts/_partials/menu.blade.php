<header>
   <nav class="navbar navbar-expand-lg {{ $themeView['navbarClass'] }} navbar-custom">
        <div class="container">

            <div class="navbar-brand-group">
                <div class="logo-container">
                    <img src="{{ asset('images/ChavukuLogo.png') }}" alt="logo">
                </div>
                <form method="POST" action="{{ route('theme.update') }}" class="theme-switch-form" data-theme-switch>
                    @csrf
                    <input type="hidden" name="theme" value="{{ $themeView['nextTheme'] }}">
                    <button type="submit" @class(['theme-switch', 'is-light' => $themeView['isLight']]) role="switch"
                        aria-checked="{{ $themeView['switchChecked'] }}" aria-label="Modo claro" title="{{ $themeView['switchTitle'] }}">
                        <span class="theme-switch-knob" aria-hidden="true"></span>
                        <i class="fa-solid fa-moon theme-switch-icon is-moon" aria-hidden="true"></i>
                        <i class="fa-solid fa-sun theme-switch-icon is-sun" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
            <button 
                class="navbar-toggler" 
                type="button" 
                data-bs-toggle="collapse" 
                data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ms-auto">

                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('muscle.index') }}">Inicio</a>
                    </li>

                    @if(session('auth_user'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('routine.index') }}">Rutinas</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('calendar.index') }}">Calendario</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('progress.index') }}">Progreso</a>
                        </li>
                    @endif

                    {{-- quien gestiona gimnasios ve los suyos como alumno desde el selector de "Gimnasios" --}}
                    @if(($canBelongToGym ?? false) && !($canManageGyms ?? false))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('gym.member') }}">Mis gimnasios</a>
                        </li>
                    @endif

                    @if($canManageGyms ?? false)
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('gym.index') }}">Gimnasios</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('ticket.index') }}">Solicitudes</a>
                        </li>
                    @endif

                    @if($canConfigureApp ?? false)
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('config.index') }}">Configuración</a>
                        </li>
                    @endif

                    @if($notificationPanel ?? null)
                        <li class="nav-item dropdown nav-notifications">
                            <button type="button" class="nav-link nav-bell @if ($notificationPanel['unreadCount']) has-unread @endif" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" aria-label="Avisos{{ $notificationPanel['unreadCount'] ? ', '.$notificationPanel['unreadCount'].' sin leer' : '' }}">
                                <i class="fa-solid fa-bell"></i>
                                @if ($notificationPanel['unreadCount'])
                                    <span class="nav-bell-badge">{{ $notificationPanel['badgeText'] }}</span>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-menu-end notification-menu">
                                <div class="notification-menu-header">
                                    <strong>Avisos</strong>
                                    @if ($notificationPanel['hasUnreadNotifications'])
                                        <form method="POST" action="{{ route('notification.read-all') }}">
                                            @csrf
                                            <button type="submit" class="notification-read-all">Marcar todo como leido</button>
                                        </form>
                                    @endif
                                </div>
                                @if (empty($notificationPanel['items']))
                                    <p class="notification-empty">No tienes avisos por ahora.</p>
                                @else
                                    <ul class="notification-list">
                                        @foreach ($notificationPanel['items'] as $notification)
                                            <li>
                                                <a href="{{ $notification['url'] }}" class="notification-item @unless ($notification['isRead']) is-unread @endunless">
                                                    <i class="{{ $notification['icon'] }} notification-icon" aria-hidden="true"></i>
                                                    <span class="notification-body">
                                                        <span class="notification-text">{{ $notification['text'] }}</span>
                                                        @if ($notification['detail'])
                                                            <span class="notification-detail">{{ $notification['detail'] }}</span>
                                                        @endif
                                                        @if ($notification['timeLabel'])
                                                            <span class="notification-time">{{ $notification['timeLabel'] }}</span>
                                                        @endif
                                                    </span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </li>
                    @endif

                    @if(session('auth_user'))
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('user.profile', session('auth_user.id')) }}">Perfil</a>
                        </li>

                        <li class="nav-item nav-logout-item">
                            <form method="POST" action="{{ route('user.logout') }}" class="nav-logout-form">
                                @csrf
                                <button type="submit" class="nav-link nav-logout-button">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    <span>Salir</span>
                                </button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('user.login') }}">Ingresar</a>
                        </li>
                    @endif

                </ul>
            </div>

        </div>
    </nav>
</header>
