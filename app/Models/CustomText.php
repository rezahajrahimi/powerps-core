<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomText extends Model
{
    use HasFactory;
    protected $table = 'custom_texts';
    protected $fillable = ['key', 'default_text', 'custom_text', 'description'];

    public function getText($key, $variables = [])
    {
        $record = $this->where('key', $key)->first();
        if ($record === null) {
            throw new \RuntimeException("Custom text key not found: {$key}");
        }

        $text = $record->custom_text ?? $record->default_text ?? '';

        return $this->replaceVariables($text, $variables);
    }

    private function replaceVariables($text, $variables)
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }

    public function setText($key, $text)
    {
        $this->where('key', $key)->update(['custom_text' => $text]);
    }
    public function getDefaultText($key)
    {
        $record = $this->where('key', $key)->first();
        if ($record === null) {
            throw new \RuntimeException("Custom text key not found: {$key}");
        }

        return $record->default_text;
    }

}
