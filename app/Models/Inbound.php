<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{
    use HasFactory;
    protected $guarded = ['id','proxy_id'];
    protected $fillable = [
        'name', 
        'data', 
        'is_active',
        'port',
        'protocol',
        'settings',
        'stream_settings',
        'tag',
        'client_stats'
    ];

    protected $casts = [
        'client_stats' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the Inbound
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function proxy()
    {
        return $this->belongsTo(Proxy::class, 'proxy_id');
    }

    /**
     * Get parsed data as array
     */
    public function getParsedDataAttribute()
    {
        if (is_string($this->data)) {
            return json_decode($this->data, true) ?: [];
        }
        return $this->data ?: [];
    }

    /**
     * Get parsed settings as array
     */
    public function getParsedSettingsAttribute()
    {
        if (is_string($this->settings)) {
            return json_decode($this->settings, true) ?: [];
        }
        return $this->settings ?: [];
    }

    /**
     * Get parsed stream settings as array
     */
    public function getParsedStreamSettingsAttribute()
    {
        if (is_string($this->stream_settings)) {
            return json_decode($this->stream_settings, true) ?: [];
        }
        return $this->stream_settings ?: [];
    }

    /**
     * Check if inbound supports specific protocol
     */
    public function supportsProtocol($protocol): bool
    {
        return strtolower($this->protocol) === strtolower($protocol);
    }

    /**
     * Get inbound ID from data or direct field
     */
    public function getInboundId()
    {
        $data = $this->parsed_data;
        if (isset($data['id'])) {
            return $data['id'];
        }
        if (is_numeric($this->data)) {
            return (int) $this->data;
        }
        return null;
    }

    /**
     * Get server host from proxy
     */
    public function getServerHost()
    {
        if ($this->proxy && $this->proxy->pannel) {
            $url = $this->proxy->pannel->user_link;
            if (empty($url)) {
                $url = $this->proxy->pannel->admin_url;
            }
            return parse_url($url, PHP_URL_HOST);
        }
        return null;
    }

    /**
     * Get server port
     */
    public function getServerPort()
    {
        return $this->port ?: 443;
    }

    /**
     * Check if inbound is valid for user creation
     */
    public function isValidForUser(): bool
    {
        return $this->is_active && 
               $this->getInboundId() !== null && 
               !empty($this->protocol);
    }
}
