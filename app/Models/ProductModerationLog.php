<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One Admin moderation action on a product (archive, restore, feature, unfeature). */
class ProductModerationLog extends Model
{
    public const ARCHIVED = 'archived';
    public const RESTORED = 'restored';
    public const FEATURED = 'featured';
    public const UNFEATURED = 'unfeatured';

    /** Actions that change the product's availability status. */
    public const STATUS_ACTIONS = [self::ARCHIVED, self::RESTORED];

    protected $fillable = ['product_id', 'admin_id', 'action', 'from_status', 'to_status', 'reason'];

    public function product() { return $this->belongsTo(Product::class); }
    public function admin()   { return $this->belongsTo(User::class, 'admin_id'); }
}
