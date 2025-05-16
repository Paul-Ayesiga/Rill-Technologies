<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // banner, floating, top, etc.
            $table->string('position')->nullable(); // left, right, top, bottom, etc.
            $table->string('image_url')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('ad_id')->unique(); // Unique identifier for tracking
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->json('pages')->nullable(); // Which pages to show this ad on
            $table->json('settings')->nullable(); // Additional settings like delay, colors, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
