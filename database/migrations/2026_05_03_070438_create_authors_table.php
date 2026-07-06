<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliation_id')->constrained('affiliations')->onDelete('cascade');
            $table->string('name');
            $table->integer('scopus_hindex')->default(0);      // C5: Kinerja Penulis Global
            $table->integer('sinta_score_author')->default(0); // C6: Kinerja Penulis Nasional
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('authors');
    }
};
