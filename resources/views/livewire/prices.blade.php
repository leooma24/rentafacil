<section id="precios" class="pricing">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-tag">Planes flexibles</span>
            <h2 class="text-color-primary-dark">NUESTROS PRECIOS</h2>
            <p>Elige el plan que mejor se adapte a tu negocio. Todos incluyen 15 días gratis.</p>
        </div>
        <div class="pricing-inner">
            @foreach ($packages as $package)
            <div class="pricing-card {{ $loop->index === 2 ? 'pricing-card-featured' : '' }}">
                @if($loop->index === 2)
                <div class="pricing-badge">Más Popular</div>
                @endif
                <h3 class="pricing-card-title">{{ $package->name }}</h3>
                <div class="pricing-card-price">
                    @if($package->price > 0)
                    <span class="pricing-currency">$</span>
                    <span class="pricing-amount">{{ number_format($package->price, 0) }}</span>
                    <span class="pricing-period">/mes</span>
                    @else
                    <span class="pricing-amount" style="font-size: 3.6rem;">Gratis</span>
                    <span class="pricing-period">para siempre</span>
                    @endif
                </div>
                <ul class="pricing-card-features">
                    <li><i class="fas fa-check"></i> {{ $package->max_washers >= 9999 ? 'Lavadoras ilimitadas' : $package->max_washers . ' lavadoras' }}</li>
                    <li><i class="fas fa-check"></i> {{ $package->max_clients >= 9999 ? 'Clientes ilimitados' : $package->max_clients . ' clientes' }}</li>
                    <li><i class="fas fa-check"></i> Dashboard con gráficas</li>
                    <li><i class="fas fa-check"></i> Contratos PDF</li>
                    <li><i class="fas fa-check"></i> Cobros por WhatsApp</li>
                </ul>

                {{-- Extra features (hidden by default) --}}
                <ul class="pricing-card-features pricing-extra-features" id="extra-{{ $loop->index }}" style="display: none; padding-top: 0;">
                    @if($package->price > 0)
                    <li><i class="fas fa-check"></i> Links de pago online</li>
                    <li><i class="fas fa-check"></i> Notificaciones automáticas</li>
                    <li><i class="fas fa-check"></i> Portal del cliente</li>
                    <li><i class="fas fa-check"></i> Reportes Excel</li>
                    <li><i class="fas fa-check"></i> Planificador de rutas</li>
                    <li><i class="fas fa-check"></i> Códigos QR por lavadora</li>
                    <li><i class="fas fa-check"></i> Calendario de vencimientos</li>
                    <li><i class="fas fa-check"></i> Analytics de negocio</li>
                    <li><i class="fas fa-check"></i> Soporte telefónico</li>
                    @else
                    <li><i class="fas fa-check"></i> Reportes semanales</li>
                    <li><i class="fas fa-check"></i> Soporte por chat</li>
                    @endif
                </ul>

                <button class="pricing-toggle" onclick="
                    var el = document.getElementById('extra-{{ $loop->index }}');
                    var isHidden = el.style.display === 'none';
                    el.style.display = isHidden ? 'block' : 'none';
                    this.innerHTML = isHidden ? '<i class=\'fas fa-chevron-up\'></i> Ver menos' : '<i class=\'fas fa-chevron-down\'></i> +{{ $package->price > 0 ? '9' : '2' }} funciones más';
                ">
                    <i class="fas fa-chevron-down"></i> +{{ $package->price > 0 ? '9' : '2' }} funciones más
                </button>

                <a href="/propietario/registrar" class="btn {{ $loop->index === 2 ? 'btn-primary' : 'btn-outline-dark' }} btn-block">
                    {{ $package->price > 0 ? 'Contratar' : 'Empezar Gratis' }}
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
