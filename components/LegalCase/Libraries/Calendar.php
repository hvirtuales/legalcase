<?php

namespace Components\LegalCase\Libraries;

use Components\LegalCase\Models\CalendarDayModel;
use Components\LegalCase\Models\SettingsModel;

/**
 * Calendario de días hábiles del despacho (motor DETERMINISTA, §5 LC-05).
 *
 * Días no hábiles:
 *  · sábados y domingos;
 *  · festivos nacionales de Colombia (Ley 51 de 1983), calculados para cualquier año:
 *      - fijos: 1 ene, 1 may, 20 jul, 7 ago, 8 dic, 25 dic;
 *      - trasladables al lunes siguiente: 6 ene, 19 mar, 29 jun, 15 ago, 12 oct, 1 nov, 11 nov;
 *      - según la Pascua: jueves y viernes santos; Ascensión (+43), Corpus Christi (+64) y
 *        Sagrado Corazón (+71), todos en lunes;
 *  · en modo `judicial`, además, la vacancia judicial colectiva del 20 de diciembre al 10 de
 *    enero (Ley 270 de 1996, art. 146);
 *  · los días que el despacho registre (cierres, suspensiones de términos decretadas).
 *
 * Sirve para PROPONER fechas: el plazo calculado nace igualmente «por validar». El cómputo
 * empieza el día hábil siguiente a la fecha de inicio (notificación), como en el CGP, art. 118.
 * No conoce los festivos de otros países: para EE. UU. u otra jurisdicción, el despacho registra
 * sus días no hábiles.
 */
class Calendar
{
    /** @var array<int,array<string,string>> festivos nacionales por año [Y-m-d => nombre] */
    private static array $national = [];

    /** @var array<string,string> días propios del despacho */
    private array $custom = [];
    private string $mode;

    public function __construct(private int $companyId)
    {
        $s          = (new SettingsModel())->forCompanyOrDefault($companyId);
        $this->mode = in_array($s['calendar_mode'] ?? 'judicial', ['common', 'judicial'], true) ? (string) $s['calendar_mode'] : 'judicial';
        if ($companyId > 0) {
            foreach ((new CalendarDayModel())->where('company_id', $companyId)->findAll(2000) as $d) {
                $this->custom[(string) $d['day']] = (string) $d['name'];
            }
        }
    }

    private static function easter(int $year): int
    {
        // Pascua gregoriana (algoritmo anónimo), sin depender de la extensión calendar.
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return mktime(12, 0, 0, $month, $day, $year);
    }

    private static function nextMonday(int $t): int
    {
        $n = (int) date('N', $t);

        return $n === 1 ? $t : strtotime('next monday', $t) + 12 * 3600;
    }

    /** Festivos nacionales de Colombia de un año: [Y-m-d => nombre]. */
    public static function colombia(int $year): array
    {
        if (isset(self::$national[$year])) {
            return self::$national[$year];
        }
        $out = [];
        foreach (['01-01' => 'Año Nuevo', '05-01' => 'Día del Trabajo', '07-20' => 'Independencia', '08-07' => 'Batalla de Boyacá', '12-08' => 'Inmaculada Concepción', '12-25' => 'Navidad'] as $md => $n) {
            $out[$year . '-' . $md] = $n;
        }
        foreach (['01-06' => 'Reyes Magos', '03-19' => 'San José', '06-29' => 'San Pedro y San Pablo', '08-15' => 'Asunción', '10-12' => 'Día de la Raza', '11-01' => 'Todos los Santos', '11-11' => 'Independencia de Cartagena'] as $md => $n) {
            $out[date('Y-m-d', self::nextMonday(strtotime($year . '-' . $md . ' 12:00:00')))] = $n;
        }
        $e = self::easter($year);
        $out[date('Y-m-d', $e - 3 * 86400)]  = 'Jueves Santo';
        $out[date('Y-m-d', $e - 2 * 86400)]  = 'Viernes Santo';
        $out[date('Y-m-d', $e + 43 * 86400)] = 'Ascensión del Señor';
        $out[date('Y-m-d', $e + 64 * 86400)] = 'Corpus Christi';
        $out[date('Y-m-d', $e + 71 * 86400)] = 'Sagrado Corazón';
        ksort($out);

        return self::$national[$year] = $out;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /** Motivo por el que un día no es hábil, o null si lo es. */
    public function reason(string $ymd): ?string
    {
        $t = strtotime($ymd . ' 12:00:00');
        if ((int) date('N', $t) >= 6) {
            return lang('Lgc.calWeekend');
        }
        if (isset($this->custom[$ymd])) {
            return $this->custom[$ymd];
        }
        $nat = self::colombia((int) date('Y', $t));
        if (isset($nat[$ymd])) {
            return $nat[$ymd];
        }
        if ($this->mode === 'judicial') {
            $md = date('m-d', $t);
            if ($md >= '12-20' || $md <= '01-10') {
                return lang('Lgc.calVacancy');
            }
        }

        return null;
    }

    public function isBusinessDay(string $ymd): bool
    {
        return $this->reason($ymd) === null;
    }

    /**
     * Fecha en que vence un término de `$days` días hábiles contado desde el día hábil
     * siguiente a `$fromYmd`. Devuelve Y-m-d.
     */
    public function addBusinessDays(string $fromYmd, int $days): string
    {
        $t = strtotime($fromYmd . ' 12:00:00');
        $n = max(0, $days);
        while ($n > 0) {
            $t += 86400;
            if ($this->isBusinessDay(date('Y-m-d', $t))) {
                $n--;
            }
        }

        return date('Y-m-d', $t);
    }

    /** Días calendario; si el último no es hábil, se corre al siguiente hábil. */
    public function addCalendarDays(string $fromYmd, int $days): string
    {
        $t = strtotime($fromYmd . ' 12:00:00') + max(0, $days) * 86400;
        while (!$this->isBusinessDay(date('Y-m-d', $t))) {
            $t += 86400;
        }

        return date('Y-m-d', $t);
    }

    /** Días no hábiles (no fines de semana) entre dos fechas, para mostrarlos al validar. */
    public function nonWorkingBetween(string $fromYmd, string $toYmd): array
    {
        $out = [];
        for ($t = strtotime($fromYmd . ' 12:00:00') + 86400; $t <= strtotime($toYmd . ' 12:00:00'); $t += 86400) {
            $d = date('Y-m-d', $t);
            if ((int) date('N', $t) < 6 && ($r = $this->reason($d)) !== null) {
                $out[$d] = $r;
            }
        }

        return $out;
    }
}
