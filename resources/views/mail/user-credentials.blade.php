<x-mail::message>
# {{ $isReset ? 'Your Password Has Been Reset' : 'Welcome to ' . config('app.name') }}

Hi {{ $user->full_name }},

@if ($isReset)
An administrator has reset your password. Use the credentials below to log in, then change your password immediately.
@else
Your account has been created. Use the credentials below to log in for the first time. You will be required to change your password after logging in.
@endif

**Email:** {{ $user->email }}
**Temporary Password:** {{ $password }}

<x-mail::button :url="config('app.url')">
Log In Now
</x-mail::button>

For security, do not share these credentials with anyone.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
