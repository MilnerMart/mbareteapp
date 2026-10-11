<?php

namespace App\ServiceApi;

interface MessagingApi {

    const channelEmail = 'email', channelSms = 'sms', channelPush = 'push', channelWhatsapp = 'whatsapp';

    static function supportedChannels(): array;

    function sendMessage(string $channel, string $to, string $message, array $options = []): array;
}
