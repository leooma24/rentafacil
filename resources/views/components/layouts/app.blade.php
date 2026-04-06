<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <meta name="description" content="Renta Fácil - La plataforma #1 para gestionar tu negocio de renta de lavadoras. Controla rentas, cobros, clientes y mantenimientos.">
        <meta name="keywords" content="Renta Fácil, renta lavadoras, gestión lavandería, software lavadoras, renta equipos">
        <meta name="author" content="Renta Fácil">

        <meta property="og:title" content="Renta Fácil | Gestiona tu negocio de renta de lavadoras">
        <meta property="og:description" content="Controla rentas, cobros, clientes y mantenimientos desde un solo lugar.">
        <meta property="og:image" content="{{ asset('img/logo.png') }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:type" content="website">

        <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/ico">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#06b6d4">

        <title>{{ $title ?? 'Renta Fácil | Gestiona tu negocio de renta de lavadoras' }}</title>
        <link rel="stylesheet" href="{{ asset('css/main.css') }}">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"
        integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous" />

        <script src="//unpkg.com/alpinejs" defer></script>

        @if(config('services.google_analytics.enabled'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.id') }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ config('services.google_analytics.id') }}');</script>
        @endif

        @if(config('services.meta_pixel.enabled'))
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ config('services.meta_pixel.id') }}');fbq('track','PageView');</script>
        @endif

        @livewireStyles
    </head>
    <body>
        <section id="inicio" class="header-top">
            <header class="header-content">
                <div class="container">
                    <div class="header-content-inner">
                        <div class="header-content-data">
                            <div class="phone">
                                <i class="fas fa-phone-alt"></i>
                                <span>Tel: <a href="https://wa.me/6682493398?text=Más%20Información" target="_blank">668 249-3398</a></span>
                            </div>
                            <div class="email">
                                <i class="fas fa-envelope"></i>
                                <span>Email: <a href="mailto:leooma@hotmail.com" target="_blank">leooma@hotmail.com</a></span>
                            </div>
                        </div>
                        <div class="header-content-social">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="https://wa.me/6682493398?text=Más%20Información" target="_blank"><i class="fab fa-whatsapp"></i></a>
                        </div>
                    </div>
                </div>
            </header>

            <div id="header-menu">
                <div class="container">
                    <div class="header-menu">
                        <div class="logo">
                            <img src="{{ asset('img/logo.png') }}" alt="Renta Fácil Logo">
                            <span class="brand">Renta<span>Fácil</span></span>
                        </div>
                        <button class="mobile-menu-toggle" onclick="document.querySelector('.menu').classList.toggle('menu-open')" aria-label="Menú">
                            <i class="fas fa-bars"></i>
                        </button>
                        <nav class="menu">
                            <ul>
                                <li><a href="#inicio">Inicio</a></li>
                                <li><a href="#porque-nosotros">Beneficios</a></li>
                                <li><a href="#servicios">Servicios</a></li>
                                <li><a href="#como-funciona">Cómo Funciona</a></li>
                                <li><a href="#precios">Precios</a></li>
                                <li><a class="btn btn-primary btn-nav" href="/propietario">Acceder</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </section>

        {{ $slot }}

        <footer id="footer" class="footer">
            <div class="container">
                <div class="footer-grid">
                    <div class="footer-col">
                        <div class="footer-brand">
                            <span class="brand">Renta<span>Fácil</span></span>
                        </div>
                        <p>La plataforma más completa para gestionar tu negocio de renta de lavadoras en México.</p>
                    </div>
                    <div class="footer-col">
                        <h4>Enlaces</h4>
                        <ul>
                            <li><a href="#inicio">Inicio</a></li>
                            <li><a href="#porque-nosotros">Beneficios</a></li>
                            <li><a href="#precios">Precios</a></li>
                            <li><a href="/propietario">Acceder</a></li>
                        </ul>
                    </div>
                    <div class="footer-col">
                        <h4>Contacto</h4>
                        <ul>
                            <li><i class="fas fa-phone-alt"></i> 668-249-3398</li>
                            <li><i class="fas fa-envelope"></i> leooma@hotmail.com</li>
                            <li><i class="fas fa-clock"></i> Lun - Vie 08:00 - 17:00</li>
                        </ul>
                    </div>
                </div>
                <div class="footer-bottom">
                    <span>&copy; {{ date('Y') }} Renta Fácil - Todos los derechos reservados.</span>
                </div>
            </div>
        </footer>

        <!-- Floating Buttons -->
        <div class="floating-buttons">
            <a href="https://wa.me/6682493398?text=Hola%2C%20quiero%20más%20información%20sobre%20Renta%20Fácil" target="_blank" class="floating-btn floating-btn-whatsapp" title="WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>
            <a href="/propietario/registrar" class="floating-btn floating-btn-register" title="Registrarse">
                <i class="fas fa-user-plus"></i>
            </a>
        </div>

        @livewireScripts

        @if(config('services.tawkto.enabled'))
        <!--Start of Tawk.to Script-->
        <script type="text/javascript">
        var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
        (function(){
        var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
        s1.async=true;
        s1.src='https://embed.tawk.to/{{ config("services.tawkto.id") }}/default';
        s1.charset='UTF-8';
        s1.setAttribute('crossorigin','*');
        s0.parentNode.insertBefore(s1,s0);
        })();
        </script>
        <!--End of Tawk.to Script-->
        @endif
    </body>
</html>
