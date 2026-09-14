@props(['value' => ''])

<x-text-input
    {{ $attributes->merge([
        'type' => 'text',
        'inputmode' => 'text',
        'autocomplete' => 'off',
        'maxlength' => '12',
        'placeholder' => '12.345.678-5',
    ]) }}
    value="{{ \App\Support\Rut\Rut::format($value) }}"
    x-data="{
        sinDv: false,
        formatear(event) {
            const entrada = event.target;
            const valorEscrito = entrada.value;
            const cursor = entrada.selectionStart ?? valorEscrito.length;
            const caracteresAntes = valorEscrito.slice(0, cursor).replace(/[^0-9Kk]/g, '').length;
            const caracteres = valorEscrito.toUpperCase().replace(/[^0-9K]/g, '').slice(0, 9);
            const tieneK = caracteres.includes('K');
            const digitos = caracteres.replace(/K/g, '');
            const limpio = tieneK ? digitos.slice(0, 8) + 'K' : caracteres;

            if (event.inputType?.startsWith('insert')) this.sinDv = false;
            if (event.inputType?.startsWith('delete') && (this.sinDv || valorEscrito.endsWith('-'))) this.sinDv = true;

            let formateado = limpio;
            if (limpio.length > 1) {
                const cuerpo = (this.sinDv ? limpio : limpio.slice(0, -1)).replace(/^0+(?=\d)/, '');
                const cuerpoFormateado = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                formateado = this.sinDv ? cuerpoFormateado : cuerpoFormateado + '-' + limpio.slice(-1);
            }

            entrada.value = formateado;

            let nuevaPosicion = 0;
            let caracteresRecorridos = 0;
            while (nuevaPosicion < formateado.length && caracteresRecorridos < caracteresAntes) {
                if (/[0-9K]/.test(formateado[nuevaPosicion])) caracteresRecorridos++;
                nuevaPosicion++;
            }
            this.$nextTick(() => entrada.setSelectionRange(nuevaPosicion, nuevaPosicion));
        }
    }"
    x-on:input="formatear($event)"
/>
