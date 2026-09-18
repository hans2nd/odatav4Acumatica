<?php

namespace App\Services\SchemaValidators\Contracts;

interface ValidationRuleInterface
{
    /**
     * Mengevaluasi apakah sebuah nilai data lolos dari suatu aturan validasi skema.
     * 
     * @param string $field Nama kolom yang divalidasi
     * @param mixed $value Nilai kolom tersebut
     * @param array $options Opsi spesifik (misalnya maksimum panjang string)
     * @return string|null Mengembalikan null jika sah, atau pesan kesalahan (string) jika gagal.
     */
    public function validate(string $field, mixed $value, array $options = []): ?string;
}
