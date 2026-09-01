<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Verta;

class Product extends Model
{
    use HasFactory;
    protected $guarded = ['id', 'product_categories_id', 'account_id'];
    protected $fillable = ['product_categories_id', 'configs', 'subscription_link', 'panel_link', 'isActive', 'account_id', 'remark','deactive_by_admin'];


    public function getProductByID($id)
    {
        return Product::find($id);
    }
    public function getProdouctPanelByID($id)
    {
        return Product::find($id)->product_category_and_panel;
    }
    /**
     * Get the user that owns the Product
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product_category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_categories_id');
    }
    public function product_category_and_panel()
    {
        return $this->belongsTo(ProductCategory::class, 'product_categories_id')
            ->with([
                'pannel' => function ($query) {
                    $query->select('id', 'type','location', 'user_link');
                },
            ])
            ->orderBy('id', 'desc');
    }
    public function user()
    {
        return $this->belongsTo(BotUser::class, 'account_id', 'account_id');
    }
    /**
     * Get all of the comments for the Product
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transaction()
    {
        return $this->hasMany(Order::class, 'product_id');
    }
    public function cron_log()
    {
        return $this->hasMany(CronLog::class, 'product_id', 'id');
    }
    public function reserved_config()
    {
        return $this->hasMany(ReserverdConfig::class, 'product_id', 'id');
    }
    public function getCreatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }
    public function getUpdatedAtAttribute($value)
    {
        return verta(verta($value))->formatDifference();
    }

    public function resolveMarzbanPanelUsername(): string
    {
        $configs = json_decode($this->configs ?? '', true) ?? [];
        $username = $configs['username'] ?? null;
        if (is_string($username) && trim($username) !== '') {
            return trim($username);
        }

        return trim((string) ($this->remark ?? ''));
    }

}
