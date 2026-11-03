<?php

namespace Components\Contabilidad\Models;

use CodeIgniter\Model;

class AttachmentModel extends Model
{
    protected $table         = 'cmp_cont_attachments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['owner_type', 'owner_id', 'original_name', 'mimetype', 'size', 'file_path', 'uploaded_by'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $updatedField  = ''; // solo created_at
}
