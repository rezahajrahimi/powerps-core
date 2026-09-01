<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proxy extends Model
{
    use HasFactory;
    protected $guarded = ['id','pannel_id'];
    protected $fillable = ['pannel_id', 'type', 'is_active'];
    /**
     * Get the user that owns the Proxy
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pannel()
    {
        return $this->belongsTo(Pannel::class, 'pannel_id');
    }
    /**
     * Get all of the comments for the Proxy
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function inbounds()
    {
        return $this->hasMany(Inbound::class, 'proxy_id');
    }

}
