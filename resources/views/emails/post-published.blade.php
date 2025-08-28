@component('mail::message')
@if($success)
# Post Published Successfully! 🎉

Your post "**{{ $postTitle }}**" has been published on **{{ ucfirst($platform) }}**.
@else
# Post Publishing Failed ❌

Your post "**{{ $postTitle }}**" failed to publish on **{{ ucfirst($platform) }}**.
@endif

@component('mail::panel')
**Post Title:** {{ $postTitle }}  
**Platform:** {{ ucfirst($platform) }}  
**Mode:** {{ ucfirst($mode) }}  
**Time:** {{ $publishedAt }}  
@if($success)
**Status:** ✅ Published Successfully
@else
**Status:** ❌ Publishing Failed  
**Error:** {{ $error }}
@endif
@endcomponent

@if($success && $postUrl !== '#')
@component('mail::button', ['url' => $postUrl])
View Post on {{ ucfirst($platform) }}
@endcomponent
@endif

Thanks for using {{ config('app.name') }}!

@component('mail::subcopy')
This is an automated notification from your Social Media Marketing Platform.  
Platform mode: {{ ucfirst($mode) }} | Timestamp: {{ now()->toISOString() }}
@endcomponent
@endcomponent