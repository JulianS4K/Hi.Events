<?php

namespace HiEvents\Models;

class TicketTransfer extends BaseModel
{
    protected $casts = [
        'transferred_at' => 'datetime',
    ];
}
