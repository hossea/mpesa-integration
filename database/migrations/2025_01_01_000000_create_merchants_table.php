<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantsTable extends Migration
{
public function up()
{
Schema::create('merchants', function (Blueprint $table) {
$table->id();
$table->string('name');
$table->string('shortcode')->nullable();
$table->string('consumer_key')->nullable();
$table->string('consumer_secret')->nullable();
$table->string('passkey')->nullable();
$table->text('security_credential')->nullable(); // encrypted
$table->json('meta')->nullable();
$table->timestamps();
});
}
public function down()
{
Schema::dropIfExists('merchants');
}
}


