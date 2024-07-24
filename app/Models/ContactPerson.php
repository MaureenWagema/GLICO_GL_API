<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactPerson extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $connection = 'britam_db'; // Specify the database connection
    protected $table = 'contactpersoninfo';
}
