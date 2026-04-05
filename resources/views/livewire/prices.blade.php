<section id="precios" class="pricing">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-tag">Planes flexibles</span>
            <h2 class="text-color-primary-dark">NUESTROS PRECIOS</h2>
            <p>Elige el plan que mejor se adapte a tu negocio</p>
        </div>
        <div class="pricing-inner">
            @foreach ($packages as $package)
            <div class="pricing-card {{ $loop->index === 1 ? 'pricing-card-featured' : '' }}">
                @if($loop->index === 1)
                <div class="pricing-badge">Popular</div>
                @endif
                <h3 class="pricing-card-title">{{ $package->name }}</h3>
                <div class="pricing-card-price">
                    <span class="pricing-currency">$</span>
                    <span class="pricing-amount">{{ number_format($package->price, 0) }}</span>
                    <span class="pricing-period">/mes</span>
                </div>
                <ul class="pricing-card-features">
                    <li><i class="fas fa-check"></i> {{ $package->max_washers }} lavadoras</li>
                    <li><i class="fas fa-check"></i> {{ $package->max_clients }} clientes</li>
                    <li><i class="fas fa-check"></i> Dashboard con gráficas</li>
                    <li><i class="fas fa-check"></i> Contratos PDF</li>
                    <li><i class="fas fa-check"></i> Cobros por WhatsApp</li>
                    @if($package->price > 0)
                    <li><i class="fas fa-check"></i> Pagos con Stripe</li>
                    <li><i class="fas fa-check"></i> Notificaciones automáticas</li>
                    <li><i class="fas fa-check"></i> Portal del cliente</li>
                    <li><i class="fas fa-check"></i> Reportes Excel</li>
                    <li><i class="fas fa-check"></i> Soporte telefónico</li>
                    @else
                    <li><i class="fas fa-check"></i> Reportes semanales</li>
                    <li><i class="fas fa-check"></i> Soporte por chat</li>
                    @endif
                </ul>
                <a href="/propietario/registrar" class="btn {{ $loop->index === 1 ? 'btn-primary' : 'btn-outline-dark' }} btn-block">
                    {{ $package->price > 0 ? 'Contratar' : 'Empezar Gratis' }}
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
