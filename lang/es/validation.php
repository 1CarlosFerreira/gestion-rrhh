<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',
    'email' => 'El campo :attribute debe ser un correo electrónico válido.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual es incorrecta.',
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.', 'array' => 'Debe seleccionar al menos :min elementos.'],
    'max' => ['string' => 'El campo :attribute no debe superar :max caracteres.'],
    'between' => ['numeric' => 'El campo :attribute debe estar entre :min y :max.'],
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'after_or_equal' => 'El campo :attribute debe ser posterior o igual a :date.',
    'string' => 'El campo :attribute debe ser texto.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'array' => 'El campo :attribute debe ser una lista.',
    'exists' => 'La selección de :attribute no es válida.',
    'distinct' => 'El campo :attribute contiene valores repetidos.',
    'password' => [
        'letters' => 'La contraseña debe contener al menos una letra.',
        'mixed' => 'La contraseña debe contener mayúsculas y minúsculas.',
        'numbers' => 'La contraseña debe contener al menos un número.',
        'symbols' => 'La contraseña debe contener al menos un símbolo.',
        'uncompromised' => 'La contraseña indicada aparece en una filtración de datos. Elige otra.',
    ],
    'attributes' => [
        'name' => 'nombre', 'email' => 'correo electrónico', 'password' => 'contraseña',
        'current_password' => 'contraseña actual', 'password_confirmation' => 'confirmación de contraseña',
        'persona_ids' => 'funcionarios', 'unidad_servicio_id' => 'unidad/servicio',
    ],
];
