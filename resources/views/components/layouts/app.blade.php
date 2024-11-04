<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Renta Fácil | Aplicación para automatizar tus maquinas de lavado.' }}</title>
        <link rel="stylesheet" href="{{ asset('css/main.css') }}">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"
        integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous" />
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
                                <span>Tel: 668 2493398</span>
                            </div>

                            <div class="email">
                                <i class="fa fa-envelope"></i>
                                <span>Email: leooma@hotmail.com</span>
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

        <section class="banner">
            <div class="container">
                <div class="banner-inner">

                    <div class="banner-content">
                        <h1>Somos la mejor aplicación para que manejes tus maquinas de lavado</h1>
                        <p>Renta maquinas de lavado de forma fácil y conveniente, sin complicaciones de mantenimiento ni costos adicionales. Gestiona tus equipos y optimiza tu tiempo con nuestra plataforma</p>
                        <a href="/propietario" class="btn">Empieza Ahora</a>
                    </div>

                    <div class="banner-footer">
                        <div class="banner-footer-inner banner-footer--1">
                            <i class="fa fa-phone"></i>
                            <div class="banner-footer-content">
                                <span>¿Tienes alguna duda? LLamanos</span>
                                <span>123-456-7890</span>
                            </div>
                        </div>

                        <div class="banner-footer-inner banner-footer--2">
                            <i class="fa fa-clock"></i>
                            <div class="banner-footer-content">
                                <span>Estamos abiertos</span>
                                <span>Lunes a viernes de 08:00 - 17:00</span>
                            </div>
                        </div>

                        <div class="banner-footer-inner banner-footer--3">
                            <i class="fa fa-check"></i>
                            <div class="banner-footer-content">
                                <span>¿Necesitas ayuda? Envíanos un correo</span>
                                <a href="mailto:soporte@tu-app.co">Soporte@tu-app.co</a>
                            </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="porque-nosotros" class="why-choose-us">
            <div class="container">
                <div class="why-choose-us-inner">
                    <div class=why-choose-us-content>
                        <h2 class="text-color-primary-dark">¿POR QUÉ ELEGIRNOS?</h2>
                        <p>Conoce las razones por las que somos la mejor opción para ti</p>
                    </div>

                    <div class="why-choose-items">
                        <div class="why-choose-item text-center">
                            <div class="why-choose-item-icon">
                                <i class="fa fa-check"></i>
                            </div>
                            <h3>Facilidad de uso</h3>
                            <p>Maneja tus maquinas de lavado de forma fácil y rápida</p>
                        </div>

                        <div class="why-choose-item text-center">
                            <div class="why-choose-item-icon">
                                <i class="fa fa-check"></i>
                            </div>
                            <h3>Soporte 24/7</h3>
                            <p>Contamos con soporte técnico las 24 horas del día</p>
                        </div>

                        <div class="why-choose-item text-center">
                            <div class="why-choose-item-icon">
                                <i class="fa fa-check"></i>
                            </div>
                            <h3>Seguridad</h3>
                            <p>Protegemos tus datos y tu información personal</p>
                        </div>

                        <div class="why-choose-item text-center">
                            <div class="why-choose-item-icon">
                                <i class="fa fa-check"></i>
                            </div>
                            <h3>Optimización</h3>
                            <p>Optimiza tu tiempo y tus recursos con nuestra plataforma</p>
                        </div>
                    </div>
                </div>
        </section>

        <section class="other-banner">
            <div class="container">
                <div class="other-banner-inner">
                    <div class="other-banner-content">
                        <h2>¿Quieres saber más sobre nosotros?</h2>
                        <p>Conoce más sobre nuestra plataforma y los beneficios que te ofrecemos</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="precios" class="pricing">
            <div class="container">
                <h2 class="text-color-primary-dark text-center">NUESTROS PRECIOS</h2>
                <div class="pricing-inner">
                    @foreach ($packages as $package)
                    <div class="pricing-content text-center">
                        <h2>{{ $package->name }}</h2>
                        <div>$ <span class="price">{{ $package->price }}</span> Men.</div>
                        <ul>
                            <li>{{ $package->max_washers}} Lavadora</li>
                            <li>{{ $package->max_clients}} Clientes</li>
                            <li>Soporte 24/7</li>
                            <li>Acceso a la plataforma</li>
                            <li>Reportes de uso</li>
                        </ul>
                    </div>
                    @endforeach

                </div>
            </div>
        </section>



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
