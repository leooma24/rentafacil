<div>
<section class="banner" style="height: auto; padding: 6rem 0;">
    <div class="container">
        <div class="banner-inner" style="flex-direction: column; align-items: flex-start;">
            <div class="banner-content" style="max-width: 100%; padding-bottom: 3rem;">
                <span class="banner-tag">Renta Fácil en {{ $city }}</span>
                <h1>Software para renta de lavadoras en {{ $city }}, {{ $state }}</h1>
                <p>¿Tienes un negocio de renta de lavadoras y secadoras en {{ $city }}? Gestiona tus equipos, cobra por WhatsApp y controla todo desde tu celular con Renta Fácil.</p>
                <div class="banner-buttons">
                    <a href="/propietario/registrar" class="btn btn-primary btn-lg">Probar 15 Días Gratis</a>
                    <a href="https://wa.me/6682493398?text=Hola%2C%20tengo%20un%20negocio%20de%20lavadoras%20en%20{{ rawurlencode($city) }}%20y%20quiero%20saber%20más" target="_blank" class="btn btn-outline btn-lg">Contactar por WhatsApp</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section style="padding: 6rem 0;">
    <div class="container">
        <div class="section-header text-center">
            <h2 class="text-color-primary-dark">¿Por qué los propietarios en {{ $city }} eligen Renta Fácil?</h2>
            <p>La herramienta más completa para gestionar tu negocio de renta de lavadoras en {{ $state }}</p>
        </div>
        <div class="features-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-map-marker-alt"></i></div>
                <h3>Control en {{ $city }}</h3>
                <p>Sabe exactamente dónde está cada lavadora que tienes rentada en {{ $city }}. Dirección, cliente, fecha de vencimiento, todo en tu celular.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fab fa-whatsapp"></i></div>
                <h3>Cobra por WhatsApp</h3>
                <p>Envía recordatorios de pago a tus clientes en {{ $city }} con un solo clic. El mensaje ya viene escrito con todos los datos.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-route"></i></div>
                <h3>Rutas optimizadas</h3>
                <p>Planifica la mejor ruta para visitar a tus clientes en {{ $city }}. El sistema calcula el orden óptimo y abre Google Maps.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-file-pdf"></i></div>
                <h3>Contratos profesionales</h3>
                <p>Genera contratos PDF al instante para cada renta. Tus clientes en {{ $city }} verán que eres un negocio profesional.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Sabe cuánto ganas</h3>
                <p>Gráficas de ingresos, rentabilidad por lavadora y proyecciones. Toma mejores decisiones para tu negocio en {{ $state }}.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                <h3>Desde tu celular</h3>
                <p>Gestiona todo desde donde estés en {{ $city }}. Sin instalar nada, funciona directo desde tu navegador.</p>
            </div>
        </div>
    </div>
</section>

<section class="cta-final">
    <div class="container text-center">
        <h2>Empieza a gestionar tus lavadoras en {{ $city }} hoy</h2>
        <p>Únete a los propietarios en {{ $state }} que ya automatizan su negocio con Renta Fácil</p>
        <div class="cta-buttons">
            <a href="/propietario/registrar" class="btn btn-primary btn-lg">Empezar Prueba Gratis</a>
            <a href="https://wa.me/6682493398?text=Hola%2C%20tengo%20lavadoras%20en%20{{ rawurlencode($city) }}%20y%20quiero%20una%20demo" target="_blank" class="btn btn-outline btn-lg">Solicitar Demo</a>
        </div>
    </div>
</section>

{{-- City-specific Schema --}}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "Renta Fácil - {{ $city }}",
    "description": "Software para gestionar negocios de renta de lavadoras y secadoras en {{ $city }}, {{ $state }}",
    "url": "{{ url("/renta-lavadoras/{$slug}") }}",
    "areaServed": {
        "@type": "City",
        "name": "{{ $city }}"
    },
    "address": {
        "@type": "PostalAddress",
        "addressLocality": "{{ $city }}",
        "addressRegion": "{{ $state }}",
        "addressCountry": "MX"
    },
    "telephone": "+52-668-249-3398",
    "priceRange": "$149-$899 MXN/mes"
}
</script>
</div>
