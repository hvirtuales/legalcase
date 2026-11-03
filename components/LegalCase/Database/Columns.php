<?php

namespace Components\LegalCase\Database;

/**
 * Atajos de columnas para las migraciones del componente.
 *
 * Existen para que las 20 tablas declaren los mismos tipos de la misma forma: una
 * FK que en una tabla es `INT(11) unsigned` y en otra `INT` sin signo no casa en
 * MySQL y la migración falla al crear la clave foránea.
 *
 * Solo tipos que Forge traduce a los tres motores (contrato §8): nada de ENUM ni
 * JSON nativo; las estructuras van en TEXT y las listas cerradas en VARCHAR con su
 * validación en el modelo.
 */
trait Columns
{
    protected function id(): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true];
    }

    protected function fk(bool $null = false): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => $null];
    }

    protected function str(int $len, ?string $default = '', bool $null = false): array
    {
        $col = ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
        if ($default !== null) {
            $col['default'] = $default;
        }

        return $col;
    }

    protected function text(): array
    {
        return ['type' => 'TEXT', 'null' => true];
    }

    protected function flag(int $default = 0): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'default' => $default];
    }

    protected function int(int $default = 0): array
    {
        return ['type' => 'INT', 'constraint' => 11, 'default' => $default];
    }

    protected function dt(): array
    {
        return ['type' => 'DATETIME', 'null' => true];
    }

    protected function date(): array
    {
        return ['type' => 'DATE', 'null' => true];
    }

    /** created_at / updated_at / deleted_at */
    protected function stamps(bool $softDelete = true): array
    {
        $out = ['created_at' => $this->dt(), 'updated_at' => $this->dt()];
        if ($softDelete) {
            $out['deleted_at'] = $this->dt();
        }

        return $out;
    }
}
