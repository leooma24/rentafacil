<div>
@livewire('banner')

@livewire('why-us')

<section id="servicios" class="features">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-tag">Todo lo que necesitas</span>
            <h2 class="text-color-primary-dark">NUESTROS SERVICIOS</h2>
            <p>Herramientas poderosas para que tu negocio funcione en automático</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-tachometer-alt"></i></div>
                <h3>Dashboard Inteligente</h3>
                <p>Visualiza ingresos, rentas activas, vencimientos y métricas de negocio en tiempo real con gráficas interactivas.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <h3>Cobros Automáticos</h3>
                <p>Genera links de pago con Stripe, activa pagos recurrentes y recibe confirmaciones automáticas por WhatsApp.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fab fa-whatsapp"></i></div>
                <h3>WhatsApp Integrado</h3>
                <p>Envía recordatorios de pago, avisos de vencimiento y confirmaciones directo al WhatsApp de tus clientes.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-file-pdf"></i></div>
                <h3>Contratos y Recibos</h3>
                <p>Genera contratos PDF profesionales al crear una renta y recibos automáticos con cada pago recibido.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-bell"></i></div>
                <h3>Notificaciones</h3>
                <p>Alertas en tiempo real cuando una renta vence, recibes un pago o un cliente reporta un problema.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                <h3>Calendario</h3>
                <p>Vista mensual de vencimientos, mantenimientos programados y cobros para planificar tu operación.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-wrench"></i></div>
                <h3>Mantenimientos</h3>
                <p>Registra mantenimientos preventivos y correctivos. La renta se extiende automáticamente por los días de servicio.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-qrcode"></i></div>
                <h3>Códigos QR</h3>
                <p>Genera un QR único por lavadora. Al escanearlo muestra estado, cliente actual y permite reportar problemas.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-user-friends"></i></div>
                <h3>Portal del Cliente</h3>
                <p>Tus clientes pueden ver sus rentas, historial de pagos y reportar problemas desde su propio panel.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-file-excel"></i></div>
                <h3>Exportar Reportes</h3>
                <p>Exporta rentas, pagos y clientes a Excel con un clic. Importa clientes y lavadoras masivamente.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Roles y Permisos</h3>
                <p>Control total de quién puede ver y hacer qué. Roles de administrador, propietario y empleado.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <h3>Analytics de Negocio</h3>
                <p>Tasa de ocupación, valor por cliente, proyección de ingresos y tasa de abandono para tomar mejores decisiones.</p>
            </div>
        </div>
    </div>
</section>

@livewire('other-banner')

<section id="como-funciona" class="how-it-works">
    <div class="container">
        <div class="section-header text-center">
            <span class="section-tag">Muy sencillo</span>
            <h2 class="text-color-primary-dark">CÓMO FUNCIONA</h2>
            <p>En 3 simples pasos estarás gestionando tu negocio</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Regístrate</h3>
                <p>Crea tu cuenta gratis en menos de 2 minutos. No necesitas tarjeta de crédito.</p>
            </div>
            <div class="step-arrow"><i class="fas fa-chevron-right"></i></div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Configura</h3>
                <p>Agrega tus lavadoras, clientes y configura tus precios de renta.</p>
            </div>
            <div class="step-arrow"><i class="fas fa-chevron-right"></i></div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>Gestiona</h3>
                <p>Cobra, extiende rentas, genera contratos y deja que el sistema haga el resto.</p>
            </div>
        </div>
    </div>
</section>

@livewire('prices', ['packages' => $packages])

<section id="contacto" class="lead-section">
    <div class="container">
        <div class="lead-section-inner">
            <div class="lead-section-text">
                <span class="section-tag">Contáctanos</span>
                <h2>¿Tienes un negocio de renta de lavadoras?</h2>
                <p>Déjanos tus datos y te contactamos por WhatsApp para mostrarte cómo Renta Fácil puede ayudarte a gestionar tu negocio.</p>
                <ul class="lead-benefits">
                    <li><i class="fas fa-check-circle"></i> Sin compromiso</li>
                    <li><i class="fas fa-check-circle"></i> Te contactamos en menos de 24 hrs</li>
                    <li><i class="fas fa-check-circle"></i> Demo personalizada gratis</li>
                </ul>
            </div>
            <div class="lead-section-form">
                @livewire('lead-form')
            </div>
        </div>
    </div>
</section>

<section class="cta-final">
    <div class="container text-center">
        <h2>Prueba gratis por 15 días, sin compromiso</h2>
        <p>Accede al plan más completo sin pagar. Sin tarjeta de crédito. Cancela cuando quieras.</p>
        <div class="cta-buttons">
            <a href="/propietario/registrar" class="btn btn-primary btn-lg">Empezar Prueba Gratis</a>
            <a href="https://wa.me/6682493398?text=Quiero%20una%20demo%20de%20Renta%20Fácil" target="_blank" class="btn btn-outline btn-lg">Solicitar Demo</a>
        </div>
    </div>
</section>
</div>
