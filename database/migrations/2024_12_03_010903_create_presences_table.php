<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePresencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id(); // Bigint unsigned primary key
            $table->unsignedBigInteger('user_id'); // Foreign key
            $table->date('presence_date'); // Presence date
            $table->timestamp('created_at')->nullable(); // Timestamp for created_at
            $table->timestamp('updated_at')->nullable(); // Timestamp for updated_at
            $table->date('date'); // Extra date column
            $table->time('time'); // Time column
            $table->enum('status', ['hadir', 'izin', 'sakit']); // Enum for status

            // Indexes
            $table->index('user_id'); // Index for user_id
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('presences');
    }
}