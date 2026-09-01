<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InboundTemplate extends Model
{
    use HasFactory;
    
    protected $guarded = ['id'];
    protected $fillable = [
        'pannel_id',
        'name',
        'description',
        'inbound_config',
        'protocol',
        'port',
        'stream_settings',
        'settings',
        'is_active',
        'created_by',
        'listen',
        'server_info',
        'config_type', // v2ray, hysteria2, custom, etc.
        'dns_info',
        'routing_info',
        'remarks'
    ];

    protected $casts = [
        'inbound_config' => 'array',
        'stream_settings' => 'array',
        'settings' => 'array',
        'server_info' => 'array',
        'dns_info' => 'array',
        'routing_info' => 'array',
        'is_active' => 'boolean',
        'port' => 'integer'
    ];

    /**
     * Get the panel that owns the template
     */
    public function panel()
    {
        return $this->belongsTo(Pannel::class, 'pannel_id');
    }

    /**
     * Get the user who created this template
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get parsed inbound configuration
     */
    public function getParsedConfigAttribute()
    {
        if (is_string($this->inbound_config)) {
            return json_decode($this->inbound_config, true) ?: [];
        }
        return $this->inbound_config ?: [];
    }

    /**
     * Get parsed stream settings
     */
    public function getParsedStreamSettingsAttribute()
    {
        if (is_string($this->stream_settings)) {
            return json_decode($this->stream_settings, true) ?: [];
        }
        return $this->stream_settings ?: [];
    }

    /**
     * Get parsed settings
     */
    public function getParsedSettingsAttribute()
    {
        if (is_string($this->settings)) {
            return json_decode($this->settings, true) ?: [];
        }
        return $this->settings ?: [];
    }

    /**
     * Check if template supports specific protocol
     */
    public function supportsProtocol($protocol): bool
    {
        return strtolower($this->protocol) === strtolower($protocol);
    }

    /**
     * Get template as inbound configuration for Sanaei
     */
    public function toInboundConfig(): array
    {
        return [
            'id' => $this->id,
            'protocol' => $this->protocol,
            'port' => $this->port,
            'settings' => $this->parsed_settings,
            'streamSettings' => $this->parsed_stream_settings,
            'tag' => $this->name,
            'remark' => $this->description
        ];
    }

    /**
     * Validate template configuration
     */
    public function isValid(): bool
    {
        return !empty($this->protocol) && 
               !empty($this->port) && 
               $this->port > 0 && 
               $this->port <= 65535 &&
               !empty($this->inbound_config);
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific protocol
     */
    public function scopeProtocol($query, $protocol)
    {
        return $query->where('protocol', strtolower($protocol));
    }

    /**
     * Scope for specific panel
     */
    public function scopeForPanel($query, $panelId)
    {
        return $query->where('pannel_id', $panelId);
    }
}
