<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('authors')->onDelete('cascade');
            $table->string('title');
            $table->year('year');                               // C4: Kemutakhiran Tahun
            $table->integer('citation_count')->default(0);      // C3: Jumlah Sitasi
            $table->string('doi')->nullable();
            $table->enum('scopus_quartile', ['Q1', 'Q2', 'Q3', 'Q4'])->nullable(); // C1: Kualitas Global
            $table->enum('sinta_accreditation', ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'])->nullable(); // C2: Kualitas Nasional
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('publications');
    }
};
