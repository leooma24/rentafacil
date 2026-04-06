<div>
    @if($submitted)
        <div class="lead-form-success">
            <div class="lead-form-success-icon"><i class="fas fa-check-circle"></i></div>
            <h3>Listo, recibimos tus datos</h3>
            <p>Nos pondremos en contacto contigo por WhatsApp muy pronto.</p>
        </div>
    @else
        <form wire:submit="submit" class="lead-form">
            <div class="lead-form-row">
                <div class="lead-form-field">
                    <input type="text" wire:model="name" placeholder="Tu nombre *" required>
                    @error('name') <span class="lead-form-error">{{ $message }}</span> @enderror
                </div>
                <div class="lead-form-field">
                    <input type="tel" wire:model="phone" placeholder="Tu WhatsApp *" required>
                    @error('phone') <span class="lead-form-error">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="lead-form-row">
                <div class="lead-form-field">
                    <input type="email" wire:model="email" placeholder="Tu email (opcional)">
                </div>
                <div class="lead-form-field">
                    <input type="text" wire:model="city" placeholder="Tu ciudad (opcional)">
                </div>
            </div>
            <div class="lead-form-row">
                <div class="lead-form-field">
                    <input type="number" wire:model="num_washers" placeholder="¿Cuántas lavadoras tienes o quieres rentar?" min="1">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg lead-form-btn" wire:loading.attr="disabled">
                <span wire:loading.remove>Quiero más información</span>
                <span wire:loading>Enviando...</span>
            </button>
        </form>
    @endif
</div>
