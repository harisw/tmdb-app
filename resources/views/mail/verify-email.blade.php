@extends('mail.layout')

@section('title', 'Confirm your email for Chill')

@section('preheader', 'One tap to confirm your email, then tell us what you love watching.')

@section('headline')
Confirm your email<br>and you&rsquo;re in.
@endsection

@section('lede')
Tap the button to confirm <span style="font-weight: 600;">{{ $email }}</span>.
Then pick a few titles you love, and Chill will start suggesting what to watch next.
@endsection

@section('action', 'Confirm my email')

@section('disclaimer')
Someone signed up for Chill with this address. If that wasn&rsquo;t you, ignore this email and the account stays inactive.
@endsection
