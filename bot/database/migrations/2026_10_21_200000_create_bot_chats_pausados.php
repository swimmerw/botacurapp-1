<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chats en que el bot no responde (se apaga por conversación, no para todo WhatsApp). */
class CreateBotChatsPausados extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('bot_chats_pausados')) {
            Schema::create('bot_chats_pausados', function (Blueprint $t) {
                $t->increments('id');
                $t->string('telefono', 20)->unique();     // solo dígitos, con 56
                $t->string('nombre', 120)->nullable();
                $t->unsignedInteger('pausado_por')->nullable();
                $t->string('motivo', 191)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('bot_chats_pausados');
    }
}
