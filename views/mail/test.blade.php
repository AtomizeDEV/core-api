<x-mail-layout>
<h2 style="font-size: 18px; font-weight: 600;">
@if($currentHour < 12)
    Good Morning, {{ $user->name }}!
@elseif($currentHour < 18)
    Good Afternoon, {{ $user->name }}!
@else
    Good Evening, {{ $user->name }}!
@endif
</h2>

🎉 This is a test email from Fleetbase to confirm that your mail configuration works.
</x-mail-layout>