<?php

declare(strict_types=1);

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coming_soon_stories', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('section')->default(ComingSoonStorySectionEnum::COMING_SOON->value);
            $table->text('teaser')->nullable();
            $table->string('category')->nullable();
            $table->string('rating')->nullable();
            $table->string('author')->nullable();
            $table->string('series_label')->nullable();
            $table->string('image_position')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['section', 'is_active', 'position']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coming_soon_stories');
    }
};
