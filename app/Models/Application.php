<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = ['name', 'download_link', 'file_src', 'os', 'how_to_use', 'description', 'yourube_link', 'is_active'];
}
