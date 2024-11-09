<section id="precios" class="pricing">
    <div class="container">
        <h2 class="text-color-primary-dark text-center">NUESTROS PRECIOS</h2>
        <div class="pricing-inner">
            @foreach ($packages as $package)
            <div class="pricing-content text-center">
                <h2>{{ $package->name }}</h2>
                <div>$ <span class="price">{{ $package->price }}</span> Men.</div>
                <ul>
                    <li>{{ $package->max_washers}} máquinas de lavado</li>
                    <li>{{ $package->max_clients}} Clientes</li>
                    <li>Soporte Chat</li>
                    @if($package->price > 0)
                    <li>Soporte Telefónico</li>
                    <li>Reportes diarios</li>
                    <li>Notificaciones de Vencimientos</li>
                    @else
                    <li>Reportes semanales</li>
                    @endif
                    <li>Soporte 24/7</li>
                    <li>Acceso a la plataforma</li>
                    <li>Reportes de uso</li>
                </ul>

                <a href="/propietario/registrar" class="btn btn-primary">Contratar</a>


            </div>
            @endforeach

        </div>
    </div>
</section>
