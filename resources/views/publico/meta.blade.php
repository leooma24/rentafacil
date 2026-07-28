{{--
    Las etiquetas que lee WhatsApp para armar la vista previa del link.
    Sin ellas el mensaje sale sin logo ni título, que es como se veía el demo.

    La imagen tiene que ir con URL absoluta, y por eso va con url() y no con
    asset(): config/app.php trae 'asset_url' en '/' —Laravel lo trae en null—
    así que asset() devuelve rutas relativas y WhatsApp no las resuelve.

    En recibos y estados de cuenta la descripción va genérica a propósito: la
    vista previa no debe enseñar el monto ni el nombre del cliente.
--}}
<meta property="og:title" content="{{ $titulo }}">
<meta property="og:description" content="{{ $descripcion }}">
<meta property="og:image" content="{{ url('img/icon-512.png') }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Renta Fácil">
<meta property="og:locale" content="es_MX">

<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $titulo }}">
<meta name="twitter:description" content="{{ $descripcion }}">
<meta name="twitter:image" content="{{ url('img/icon-512.png') }}">

<link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/ico">
