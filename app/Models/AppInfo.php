<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppInfo extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'version',
        'image',
        'primary_color',
        'secondary_color',
        'background_color',
        'panel_title',
        'footer_text',
        'show_powerps_credit',
    ];
    protected $casts = [
        'version' => 'string',
        'image' => 'string',
        'show_powerps_credit' => 'boolean',
    ];
    public function version(): string
    {
        return $this->version;
    }
    public function image(): string
    {
        return $this->image;
    }
    public function versions(): array
    {
        return explode('.', $this->version);
    }
    public function getAppInfo(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'image' => $this->image,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'background_color' => $this->background_color,
            'panel_title' => $this->panel_title,
            'footer_text' => $this->footer_text,
            'show_powerps_credit' => $this->show_powerps_credit ?? true,
        ];
    }
    public function setAppInfo(array $data): void
    {
        $this->name = $data['name'] ?? $this->name;
        $this->version = $data['version'] ?? $this->version;
        $this->image = $data['image'] ?? $this->image;
        $this->primary_color = $data['primary_color'] ?? $this->primary_color;
        $this->secondary_color = $data['secondary_color'] ?? $this->secondary_color;
        $this->background_color = $data['background_color'] ?? $this->background_color;
        $this->panel_title = $data['panel_title'] ?? $this->panel_title;
        $this->footer_text = $data['footer_text'] ?? $this->footer_text;
        if (array_key_exists('show_powerps_credit', $data)) {
            $this->show_powerps_credit = (bool) $data['show_powerps_credit'];
        }
        $this->save();
    }

}
