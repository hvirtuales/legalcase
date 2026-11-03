<?php

namespace Components\LeadFlow\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Credenciales de USCIS **por firma**, en los ajustes de la empresa.
 *
 * Van aquí y no en `cmp_lfl_providers` —donde vive la app de Docketwise— porque el
 * flujo es distinto y esa diferencia decide la arquitectura:
 *
 *  - Docketwise es OAuth de usuario sobre una app GLOBAL: la plataforma registra una
 *    app y cada firma autoriza su cuenta.
 *  - USCIS es `client_credentials`: **las credenciales SON la cuenta**. No hay paso de
 *    autorización que separe «la app» de «la firma».
 *
 * Por eso cada firma registra su propia app en el portal de USCIS y pega aquí su
 * client_id y su secret. Lo importante no es dónde se guardan, es la consecuencia:
 * **el cupo de 1.000 consultas al día es de cada firma**, no de la plataforma. Con un
 * cupo compartido, una firma con tráfico se lo agotaría a todas las demás antes del
 * mediodía; con este reparto, quien consume es quien paga su propio límite.
 *
 * El secret se guarda cifrado (`Libraries\Crypto`), como los tokens de Docketwise.
 */
class AddUscisToSettings extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('cmp_lfl_settings')) {
            return;
        }

        $nuevas = [];
        if (! $this->db->fieldExists('uscis_client_id', 'cmp_lfl_settings')) {
            $nuevas['uscis_client_id'] = ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true];
        }
        if (! $this->db->fieldExists('uscis_client_secret', 'cmp_lfl_settings')) {
            // Cifrado en reposo: `enc:v1:` ocupa más que el valor en claro.
            $nuevas['uscis_client_secret'] = ['type' => 'TEXT', 'null' => true];
        }
        if (! $this->db->fieldExists('uscis_mode', 'cmp_lfl_settings')) {
            // `simulated` por defecto: una firma que acaba de pegar sus credenciales
            // debe poder recorrer la pantalla sin gastar cupo real.
            $nuevas['uscis_mode'] = ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'simulated'];
        }

        if ($nuevas !== []) {
            $this->forge->addColumn('cmp_lfl_settings', $nuevas);
            $this->db->resetDataCache();
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('cmp_lfl_settings')) {
            return;
        }
        foreach (['uscis_client_id', 'uscis_client_secret', 'uscis_mode'] as $col) {
            if ($this->db->fieldExists($col, 'cmp_lfl_settings')) {
                $this->forge->dropColumn('cmp_lfl_settings', $col);
            }
        }
        $this->db->resetDataCache();
    }
}
