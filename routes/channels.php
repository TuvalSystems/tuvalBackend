<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.GpUsuario.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('approvals-reservations', fn($user) => true);
Broadcast::channel('refresh-authorized-reservations', fn($user) => true);
Broadcast::channel('refresh-list-collection', fn($user) => true);
Broadcast::channel('collection-invoices', fn($user) => true);
