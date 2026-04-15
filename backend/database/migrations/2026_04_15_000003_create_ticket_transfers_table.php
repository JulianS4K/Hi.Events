<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_attendee_id');
            $table->unsignedBigInteger('new_attendee_id')->nullable();
            $table->unsignedBigInteger('event_id');
            $table->string('from_email');
            $table->string('to_identifier');          // email address or phone number
            $table->string('to_identifier_type', 10); // 'email' or 'phone'
            $table->string('token', 64)->unique();
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->index('original_attendee_id');
            $table->index('event_id');
            $table->index('token');

            $table->foreign('original_attendee_id')
                ->references('id')->on('attendees')
                ->onDelete('cascade');

            $table->foreign('event_id')
                ->references('id')->on('events')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
    }
};
