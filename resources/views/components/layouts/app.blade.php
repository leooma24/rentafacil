<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <meta name="description" content="Renta Fácil: Software #1 en México para gestionar tu negocio de renta de lavadoras y secadoras a domicilio. Controla rentas, cobros por WhatsApp, clientes, mantenimientos, contratos PDF y rutas de cobranza. Prueba gratis 15 días.">
        <meta name="keywords" content="renta de lavadoras, software renta lavadoras, app renta lavadoras, gestión lavadoras, renta lavadoras a domicilio, sistema renta lavadoras, control lavadoras, cobro renta lavadoras, renta secadoras, renta lavadoras México, software lavandería, app lavandería, renta lavadoras Culiacán, renta lavadoras Guadalajara, renta lavadoras Monterrey, renta lavadoras CDMX">
        <meta name="author" content="Renta Fácil">
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
        <link rel="canonical" href="{{ url('/') }}">

        <meta property="og:title" content="Renta Fácil | Software para gestionar tu negocio de renta de lavadoras">
        <meta property="og:description" content="Controla rentas, cobros, clientes y mantenimientos desde tu celular. Cobra por WhatsApp, genera contratos PDF y planifica rutas. Prueba gratis 15 días.">
        <meta property="og:image" content="{{ url('img/logo.png') }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Renta Fácil">
        <meta property="og:locale" content="es_MX">

        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Renta Fácil | Software para renta de lavadoras">
        <meta name="twitter:description" content="Gestiona tu negocio de renta de lavadoras desde tu celular. Prueba gratis 15 días.">
        <meta name="twitter:image" content="{{ url('img/logo.png') }}">

        <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/ico">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#06b6d4">

        {{-- Schema.org Structured Data --}}
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "SoftwareApplication",
            "name": "Renta Fácil",
            "applicationCategory": "BusinessApplication",
            "operatingSystem": "Web, iOS, Android",
            "description": "Software para gestionar negocios de renta de lavadoras y secadoras a domicilio. Control de rentas, cobros, clientes, mantenimientos y rutas.",
            "url": "{{ url('/') }}",
            "image": "{{ asset('img/logo.png') }}",
            "offers": {
                "@type": "Offer",
                "price": "0",
                "priceCurrency": "MXN",
                "description": "Prueba gratis 15 días"
            },
            "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": "4.9",
                "reviewCount": "47"
            },
            "author": {
                "@type": "Organization",
                "name": "Renta Fácil",
                "url": "{{ url('/') }}",
                "contactPoint": {
                    "@type": "ContactPoint",
                    "telephone": "+52-668-249-3398",
                    "contactType": "customer service",
                    "availableLanguage": "Spanish"
                }
            }
        }
        </script>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                {"@type": "Question", "name": "¿Qué es Renta Fácil?", "acceptedAnswer": {"@type": "Answer", "text": "Renta Fácil es un software para gestionar negocios de renta de lavadoras y secadoras a domicilio. Permite controlar rentas, cobros, clientes, mantenimientos y rutas desde cualquier dispositivo."}},
                {"@type": "Question", "name": "¿Cuánto cuesta Renta Fácil?", "acceptedAnswer": {"@type": "Answer", "text": "Planes desde $149 MXN al mes. Incluye plan gratuito con 3 lavadoras y prueba gratis de 15 días con todas las funciones."}},
                {"@type": "Question", "name": "¿Cómo funciona la prueba gratis?", "acceptedAnswer": {"@type": "Answer", "text": "Al registrarte recibes 15 días gratis con el plan completo. Sin tarjeta de crédito, sin compromiso. Si te gusta, eliges un plan."}},
                {"@type": "Question", "name": "¿Puedo cobrar por WhatsApp?", "acceptedAnswer": {"@type": "Answer", "text": "Sí. Desde la tabla de rentas puedes enviar recordatorios de pago directo al WhatsApp del cliente con un clic."}},
                {"@type": "Question", "name": "¿Funciona en celular?", "acceptedAnswer": {"@type": "Answer", "text": "Sí. Renta Fácil es una app web progresiva (PWA) que funciona en cualquier celular, tablet o computadora sin instalar nada."}}
            ]
        }
        </script>

        <title>{{ $title ?? 'Renta Fácil | Software #1 para Renta de Lavadoras y Secadoras en México' }}</title>
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
                                <li><a href="/demo">Ver demo</a></li>
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
                        <h4>Ciudades</h4>
                        <ul class="footer-cities">
                            <li><a href="/renta-lavadoras/culiacan">Culiacán</a></li>
                            <li><a href="/renta-lavadoras/mazatlan">Mazatlán</a></li>
                            <li><a href="/renta-lavadoras/guadalajara">Guadalajara</a></li>
                            <li><a href="/renta-lavadoras/monterrey">Monterrey</a></li>
                            <li><a href="/renta-lavadoras/cdmx">CDMX</a></li>
                            <li><a href="/renta-lavadoras/tijuana">Tijuana</a></li>
                            <li><a href="/renta-lavadoras/hermosillo">Hermosillo</a></li>
                            <li><a href="/renta-lavadoras/leon">León</a></li>
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
            <button class="floating-btn floating-btn-chat" title="Ayuda" onclick="document.getElementById('chatbot').style.display = document.getElementById('chatbot').style.display === 'none' ? 'flex' : 'none'">
                <i class="fas fa-comment-dots"></i>
            </button>
        </div>

        <!-- Chatbot -->
        <div id="chatbot" style="display: none; position: fixed; bottom: 9rem; right: 2.5rem; width: 360px; max-height: 500px; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); flex-direction: column; z-index: 1001; overflow: hidden;">
            <div style="background: linear-gradient(135deg, #0e7490, #06b6d4); color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="font-size: 15px;">Asistente Renta Fácil</strong>
                    <div style="font-size: 12px; opacity: 0.85;">Respuestas instantáneas</div>
                </div>
                <button onclick="document.getElementById('chatbot').style.display='none'" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <div id="chat-messages" style="flex: 1; overflow-y: auto; padding: 16px; max-height: 300px; font-size: 14px;"></div>
            <div style="padding: 12px 16px; border-top: 1px solid #e2e8f0;">
                <div style="display: flex; flex-wrap: wrap; gap: 6px;" id="chat-options"></div>
                <div style="display: flex; gap: 8px; margin-top: 8px;">
                    <input id="chat-input" type="text" placeholder="Escribe tu pregunta..." style="flex: 1; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none;" onkeydown="if(event.key==='Enter')sendChatMessage()">
                    <button onclick="sendChatMessage()" style="background: #06b6d4; color: white; border: none; border-radius: 8px; padding: 0 14px; cursor: pointer; font-size: 14px;"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>

        <script>
        const chatFAQ = [
            {q: "¿Qué es Renta Fácil?", a: "Renta Fácil es una plataforma para gestionar tu negocio de renta de lavadoras y secadoras. Controla rentas, cobros, clientes y mantenimientos desde tu celular o computadora."},
            {q: "¿Cuánto cuesta?", a: "Tenemos planes desde <strong>$149/mes</strong>. También hay un plan gratuito con hasta 3 lavadoras. Y puedes probar el plan completo <strong>gratis por 15 días</strong> sin tarjeta de crédito."},
            {q: "¿Cómo funciona el trial?", a: "Al registrarte, automáticamente recibes <strong>15 días gratis</strong> con el plan más completo. Sin tarjeta, sin compromiso. Si te gusta, eliges un plan. Si no, no se cobra nada."},
            {q: "¿Qué incluye?", a: "Dashboard con gráficas, cobros por WhatsApp, contratos PDF, recibos, calendario de vencimientos, planificador de rutas, códigos QR, portal del cliente, reportes Excel, notificaciones automáticas y mucho más."},
            {q: "¿Puedo cobrar por WhatsApp?", a: "¡Sí! Desde la tabla de rentas puedes enviar un recordatorio de pago directo al WhatsApp del cliente con un solo clic. El mensaje ya viene escrito con los datos."},
            {q: "¿Funciona en celular?", a: "Sí, funciona perfecto desde cualquier celular, tablet o computadora. Es una app web progresiva (PWA) que puedes instalar en tu celular sin ir a la tienda de apps."},
            {q: "¿Cómo me registro?", a: "Es muy fácil: <a href='/propietario/registrar' style='color: #06b6d4; font-weight: 600;'>clic aquí para registrarte</a>. Solo necesitas nombre, email y contraseña. En 2 minutos estarás listo."},
            {q: "¿Cómo pago?", a: "Puedes pagar con <strong>tarjeta de crédito/débito</strong> o por <strong>transferencia SPEI</strong>. Dentro de la plataforma en la sección 'Mi Plan' encontrarás todas las opciones."},
            {q: "¿Necesito instalar algo?", a: "No. Renta Fácil funciona directamente desde tu navegador. Opcionalmente puedes 'instalar' la app en tu celular desde el navegador para acceso más rápido."},
            {q: "¿Mis datos están seguros?", a: "Sí. Usamos encriptación, backups automáticos diarios y servidores seguros. Tu información siempre está protegida y respaldada."},
        ];
        const quickOptions = ["¿Qué es Renta Fácil?", "¿Cuánto cuesta?", "¿Cómo funciona el trial?", "¿Qué incluye?", "¿Cómo me registro?"];

        function initChat() {
            addBotMessage("¡Hola! 👋 Soy el asistente de Renta Fácil. ¿En qué te puedo ayudar?");
            showQuickOptions();
        }

        function showQuickOptions() {
            const container = document.getElementById('chat-options');
            container.innerHTML = '';
            quickOptions.forEach(q => {
                const btn = document.createElement('button');
                btn.textContent = q;
                btn.style.cssText = 'background: #ecfeff; color: #0e7490; border: 1px solid #67e8f9; border-radius: 20px; padding: 6px 12px; font-size: 12px; cursor: pointer; font-weight: 500;';
                btn.onclick = () => handleQuestion(q);
                container.appendChild(btn);
            });
        }

        function handleQuestion(question) {
            addUserMessage(question);
            const match = chatFAQ.find(f => f.q.toLowerCase() === question.toLowerCase());
            if (match) {
                setTimeout(() => addBotMessage(match.a), 500);
            } else {
                findBestMatch(question);
            }
            setTimeout(showQuickOptions, 600);
        }

        function findBestMatch(input) {
            const words = input.toLowerCase().split(/\s+/);
            let bestMatch = null, bestScore = 0;
            chatFAQ.forEach(f => {
                const target = (f.q + ' ' + f.a).toLowerCase();
                let score = words.filter(w => w.length > 2 && target.includes(w)).length;
                if (score > bestScore) { bestScore = score; bestMatch = f; }
            });
            if (bestMatch && bestScore >= 1) {
                setTimeout(() => addBotMessage(bestMatch.a), 500);
            } else {
                setTimeout(() => addBotMessage("No tengo una respuesta exacta para eso. 😅 ¿Te gustaría hablar con alguien de nuestro equipo?<br><br><a href='https://wa.me/6682493398?text=Hola%2C%20tengo%20una%20pregunta%20sobre%20Renta%20Fácil' target='_blank' style='display: inline-block; background: #25D366; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px;'>Hablar por WhatsApp</a>"), 500);
            }
        }

        function sendChatMessage() {
            const input = document.getElementById('chat-input');
            if (!input.value.trim()) return;
            handleQuestion(input.value.trim());
            input.value = '';
        }

        function addBotMessage(text) {
            const div = document.createElement('div');
            div.style.cssText = 'background: #f0fdfa; border-radius: 12px 12px 12px 4px; padding: 10px 14px; margin-bottom: 10px; font-size: 13px; color: #334155; line-height: 1.6; max-width: 90%;';
            div.innerHTML = text;
            document.getElementById('chat-messages').appendChild(div);
            document.getElementById('chat-messages').scrollTop = 99999;
        }

        function addUserMessage(text) {
            const div = document.createElement('div');
            div.style.cssText = 'background: #06b6d4; color: white; border-radius: 12px 12px 4px 12px; padding: 10px 14px; margin-bottom: 10px; font-size: 13px; line-height: 1.6; max-width: 90%; margin-left: auto;';
            div.textContent = text;
            document.getElementById('chat-messages').appendChild(div);
            document.getElementById('chat-messages').scrollTop = 99999;
        }

        document.addEventListener('DOMContentLoaded', initChat);
        </script>

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
