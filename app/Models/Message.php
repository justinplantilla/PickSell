<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['sender_id', 'receiver_id', 'body', 'read', 'product_id'];

    /** Starts every message MessagingService::sellerAutoReply sends, which is how it finds its earlier ones. */
    public const AUTO_REPLY_PREFIX = '[Auto-reply]';

    public function sender()   { return $this->belongsTo(User::class, 'sender_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'receiver_id'); }
    public function product()  { return $this->belongsTo(Product::class); }
}
