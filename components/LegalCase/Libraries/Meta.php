<?php

namespace Components\LegalCase\Libraries;

/**
 * Presentación compartida por las vistas internas: tonos de las insignias y formato
 * de fechas. Única definición, para que un estado tenga el mismo color en el listado,
 * en la ficha y en el panel.
 *
 * Solo variantes que existen en `resources/css/app.css` (badge-success, -warning,
 * -danger, -info, -secondary, -light).
 */
class Meta
{
    public const TONES = [
        // asuntos
        'open' => 'badge-success', 'on_hold' => 'badge-warning', 'closed' => 'badge-secondary',
        // actualizaciones
        'draft' => 'badge-light', 'pending_review' => 'badge-warning', 'approved' => 'badge-info',
        'published' => 'badge-success', 'superseded' => 'badge-secondary', 'revoked' => 'badge-danger',
        // plazos
        'to_validate' => 'badge-warning', 'validated' => 'badge-info', 'done' => 'badge-success', 'cancelled' => 'badge-secondary',
        // hilos
        'new' => 'badge-danger', 'assigned' => 'badge-warning', 'waiting_client' => 'badge-info', 'answered' => 'badge-success',
        // solicitudes
        'pending' => 'badge-warning', 'submitted' => 'badge-info', 'accepted' => 'badge-success', 'rejected' => 'badge-danger',
        // documentos
        'internal' => 'badge-light', 'privileged' => 'badge-danger', 'review' => 'badge-warning', 'shared' => 'badge-success',
        // envíos
        'sent' => 'badge-success', 'failed' => 'badge-danger', 'skipped' => 'badge-secondary',
    ];

    public static function badge(string $group, string $status): string
    {
        return '<span class="badge ' . (self::TONES[$status] ?? 'badge-light') . '">'
            . esc(lang('Lgc.' . $group . '_' . $status)) . '</span>';
    }

    public static function date(?string $value, bool $withTime = true): string
    {
        if (!$value || !strtotime($value)) {
            return '—';
        }

        return date($withTime ? 'Y-m-d H:i' : 'Y-m-d', strtotime($value));
    }

    /** Valor para un <input type="datetime-local">. */
    public static function inputDt(?string $value): string
    {
        return $value && strtotime($value) ? date('Y-m-d\TH:i', strtotime($value)) : '';
    }

    public static function isPast(?string $value): bool
    {
        return $value && strtotime($value) && strtotime($value) < time();
    }
}
