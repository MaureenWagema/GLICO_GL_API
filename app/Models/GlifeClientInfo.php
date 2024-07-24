<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlifeClientInfo extends Model
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $connection = 'britam_db'; // Specify the database connection
    protected $table = 'glifeclientinfo';

    public function getTokenableId()
    {
        return $this->Id; // Use the "Id" column as the tokenable ID
    }
}
