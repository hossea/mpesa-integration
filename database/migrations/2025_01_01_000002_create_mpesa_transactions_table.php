<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMpesaTransactionsTable extends Migration
{
public function up()
{
Schema::create('mpesa_transactions', function (Blueprint $table) {
$table->id();
$table->unsignedBigInteger('merchant_id')->nullable();
$table->string('type'); // stk, c2b, b2c, b2b
$table->string('transaction_id')->nullable();
$table->string('checkout_request_id')->nullable()->index();
$table->string('merchant_request_id')->nullable();
$table->string('phone')->nullable();
$table->decimal('amount', 18, 2)->nullable();
$table->json('request_payload')->nullable();
$table->json('response_payload')->nullable();
$table->json('callback_payload')->nullable();
$table->string('status')->default('pending');
$table->timestamps();

$table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
});
}

public function down()
{
Schema::dropIfExists('mpesa_transactions');
}
}
