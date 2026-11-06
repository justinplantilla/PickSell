<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An extra delivery address in a buyer's address book (the profile address is the default). */
class BuyerAddress extends Model
{
    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'contact_no',
        'province', 'municipality', 'barangay', 'municipality_code', 'barangay_code', 'street', 'house_no',
        'delivery_lat', 'delivery_lng',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
