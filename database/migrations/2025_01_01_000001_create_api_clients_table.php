<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApiClientsTable extends Migration
{
public function up()
{
Schema::create('api_clients', function (Blueprint $table) {
$table->id();
$table->string('name');
$table->string('api_key')->unique();
$table->text('allowed_domains')->nullable();
$table->json('allowed_ips')->nullable();
$table->boolean('is_active')->default(true);
$table->timestamps();
});
}

public function down()
{
Schema::dropIfExists('api_clients');
}
}
