<x-mail::message>
# {{ __('Failed sign-ins are spiking') }}

{{ __(':failures failed sign-ins in the last :minutes minutes, with :logins different logins from :ips different IP addresses.', [
    'failures' => $failures,
    'minutes' => $minutes,
    'logins' => $logins,
    'ips' => $ips,
]) }}

<x-mail::button :url="$url">
{{ __('Open the session list') }}
</x-mail::button>
</x-mail::message>
