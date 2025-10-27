<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkflowRoleAssign extends Model
{
      use HasFactory;

      protected $fillable= [
        'workflow_id',
        'step_id',
        'role_id',
        'approval_logic',
        'specific_user',
        'user_id',
        'remark'
      ];
}
