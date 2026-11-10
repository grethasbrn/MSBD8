<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\DbErrors;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah aturan bisnis yang dilanggar (dari service PHP maupun trigger PostgreSQL)
 * menjadi pesan validasi biasa: halaman kembali ke form beserta isian dan pesan error.
 */
trait MenerjemahkanGalat
{
    protected function terjemahkan(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['umum' => $e->getMessage()]);
        } catch (QueryException $e) {
            if ($pesan = DbErrors::message($e)) {
                throw ValidationException::withMessages(['umum' => $pesan]);
            }
            throw $e;
        }
    }
}
