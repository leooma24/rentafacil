@livewire('banner')

<section id="package">
    <div class="container">
        <div class="package-inner">
            <div class="package-content">
                <h2 class="text-color">
                    {{ $package->name }}
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
                </h2>

                <h3>Contratar</h3>
                <form id="payment-form">
                    @csrf
                    <div id="card-element"></div>
                    <div id="payment-element">
                        <!-- Mount the Payment Element here -->
                      </div>
                    <button type="submit" class="btn btn-primary">Pagar</button>
                </form>
                <div id="payment-result"></div>
            </div>
        </div>
    </div>
</section>

<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripe = Stripe("{{ env('STRIPE_KEY') }}");
    const elements = stripe.elements();
    const cardElement = elements.create('card');
    cardElement.mount('#card-element');


    const form = document.getElementById('payment-form');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const { clientSecret } = await fetch('/create-payment-intent', {
            method: 'POST',
            body: JSON.stringify({ packageId: {{ $package->id }} }),
            headers: { 'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
            },
        }).then((r) => r.json());

        const { error } = await stripe.confirmCardPayment(clientSecret, {
            payment_method: { card: cardElement }
        });

        const resultDiv = document.getElementById('payment-result');
        if (error) {
            resultDiv.textContent = error.message;
        } else {
            resultDiv.textContent = '¡Pago exitoso!';
        }
    });
</script>
