<x-row-actions :edit-url="route('whatsapp-receivers.edit', $receiver)"
    :delete-url="route('whatsapp-receivers.destroy', $receiver)"
    edit-permission="app_setting.edit" delete-permission="app_setting.edit"
    :confirm="'Stop sending the opening reports to ' . $receiver->name . '?'" />
