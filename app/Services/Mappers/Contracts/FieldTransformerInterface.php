<?php

namespace App\Services\Mappers\Contracts;

interface FieldTransformerInterface
{
    /**
     * Mentransformasi nilai mentah menjadi bentuk terstandarisasi.
     * 
     * @param mixed $value Nilai dari penyedia sumber data.
     * @param array $options Konfigurasi spesifik (opsional) untuk transformasi.
     * @return mixed Nilai akhir yang telah ternormalisasi.
     */
    public function transform(mixed $value, array $options = []): mixed;
}
