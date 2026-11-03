<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** URL base de la cuenta para SMS (necesaria para Infobip; Twilio usa host fijo). Idempotente. */
class AddSmsEndpointToChannels extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('company_channels') && !$this->db->fieldExists('sms_endpoint', 'company_channels')) {
            $this->forge->addColumn('company_channels', [
                'sms_endpoint' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'sms_provider'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('sms_endpoint', 'company_channels')) {
            $this->forge->dropColumn('company_channels', 'sms_endpoint');
        }
    }
}
