<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <meta name="description" content="Renta Fácil es una aplicación para automatizar tus máquinas de lavado.">
        <meta name="keywords" content="Renta Fácil, Máquinas de lavado, Lavandería, App, Automatización">
        <meta name="author" content="Renta Fácil">

        <meta property="og:title" content="Renta Fácil | Aplicación para automatizar tus máquinas de lavado.">
        <meta property="og:description" content="Renta Fácil es una aplicación para automatizar tus máquinas de lavado.">
        <meta property="og:image" content="{{ asset('img/logo.png') }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:type" content="website">

        <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/ico">


        <title>{{ $title ?? 'Renta Fácil | Aplicación para automatizar tus máquinas de lavado.' }}</title>
        <link rel="stylesheet" href="{{ asset('css/main.css') }}">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"
        integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous" />

        <script src="//unpkg.com/alpinejs" defer></script>
        @livewireStyles
    </head>
    <body>
        <section id="inicio" class="header-top">
            <header class="header-content">
                <div class="container">
                    <div class="header-content-inner">
                        <div class="header-content-data">
                            <div class="phone">
                                <i class="fa fa-phone"></i>
                                <span>Tel: <a href="https://wa.me/6682493398?text=Más%20Información" target="_blank">668 249-3398</a></span>
                            </div>

                            <div class="email">
                                <i class="fa fa-envelope"></i>
                                <span>Email: <a href="mailto:leooma@hotmail.com" target="_blank">leooma@hotmail.com</a></span>
                            </div>
                        </div>

                        <div class="header-content-social">
                            <i class="fab fa-facebook"></i>
                            <i class="fab fa-twitter"></i>
                            <i class="fab fa-instagram"></i>
                            <i class="fab fa-pinterest"></i>
                        </div>
                    </div>
                </div>
            </header>

            <div id="header-menu">
                <div class="container">
                    <div class="header-menu">
                        <div class="logo">
                            <img src="{{ asset('img/logo.png') }}" alt="Logo">

                            <span class="brand">Renta<span>Fácil</span></span>
                        </div>
                        <nav class="menu">
                            <ul>
                                <li><a href="#inicio">Inicio</a></li>
                                <li><a href="#porque-nosotros">¿Porqué Nosotros?</a></li>
                                <li><a href="#servicios">Nuestros Servicios</a></li>
                                <li><a href="#precios">Precios</a></li>
                                <li><a class="btn btn-accent" href="/propietario">App</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
<!--



-->
        {{ $slot }}

        <footer id="footer" class="footer">
            <div class="container">
                <div class="footer-inner">
                    <div class="footer-content text-center">
                        <span>&copy; 2024 Renta Fácil - Todos los derechos reservados.</span>
                    </div>

                </div>
            </div>

        <script defer src="https://use.fontawesome.com/releases/v5.15.4/js/solid.js"
            integrity="sha384-/BxOvRagtVDn9dJ+JGCtcofNXgQO/CCCVKdMfL115s3gOgQxWaX/tSq5V8dRgsbc" crossorigin="anonymous">
        </script>
        <script defer src="https://use.fontawesome.com/releases/v5.15.4/js/fontawesome.js"
            integrity="sha384-dPBGbj4Uoy1OOpM4+aRGfAOc0W37JkROT+3uynUgTHZCHZNMHfGXsmmvYTffZjYO" crossorigin="anonymous">
        </script>

        @livewireScripts
    </body>
</html>
