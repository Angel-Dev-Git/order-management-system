<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    public $fillable = [
        'user_id',
        'order_id',
        'previous_status',
        'new_status',
        'remarks',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
