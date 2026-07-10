<header>
   <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">

            <div class="logo-container">
                <img src="{{ asset('images/LeoncioLogo.png') }}" alt="logo">
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

                    <li class="nav-item">
                        <a class="nav-link" href="#">Rutinas</a>
                    </li>

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
